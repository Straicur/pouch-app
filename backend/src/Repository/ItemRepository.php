<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\Item;
use App\Enum\ItemType;
use App\Services\Item\ValueObject\ItemListFilter;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function count;
use function implode;
use function is_numeric;
use function is_scalar;
use function is_string;
use function preg_match_all;
use function str_replace;

/**
 * @extends ServiceEntityRepository<Item>
 */
class ItemRepository extends ServiceEntityRepository
{
    /**
     * Wrap a ts_headline() match in these instead of its default `<b>...</b>`
     * — Private Use Area codepoints, never present in real content, that
     * ItemCard splits on to render highlighted segments as plain React text
     * nodes. ts_headline() returns the original document text verbatim
     * around them (Postgres doesn't escape/tokenize it), so treating that
     * output as HTML would be a stored-XSS hole for anyone who can plant
     * item content another account then searches for (OCR text, a note, a
     * scraped page).
     */
    public const string SNIPPET_HIGHLIGHT_START = "\u{E000}";

    public const string SNIPPET_HIGHLIGHT_END = "\u{E001}";

    /**
     * Same field list/order as search_vector's own weighting (Version20260901200000)
     * — kept as one constant so the exact search, the typo-tolerant fallback,
     * and snippet highlighting all read exactly the same text.
     */
    private const string DOCUMENT_EXPRESSION = "coalesce(i.name, '') || ' ' || coalesce(i.page_title, '') || ' ' || coalesce(i.note_content, '') || ' ' || coalesce(i.extracted_text, '') || ' ' || coalesce(i.page_description, '') || ' ' || coalesce(i.url, '')";

    /**
     * Below this, a trigram "match" is noise, not a plausible typo — high
     * enough to keep two-letter words from matching almost everything.
     */
    private const float FUZZY_SIMILARITY_THRESHOLD = 0.2;

    private const int FUZZY_MATCH_LIMIT = 50;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    public function save(Item $item): void
    {
        $this->getEntityManager()->persist($item);
        $this->getEntityManager()->flush();
    }

    public function remove(Item $item): void
    {
        $this->getEntityManager()->remove($item);
        $this->getEntityManager()->flush();
    }

    /**
     * Every item in the pouch, any state (trashed or not) — an admin's
     * self-service "delete my whole pouch" wipes everything immediately,
     * not just what GC would eventually get to. Explicit `i.pouch = :pouchId`
     * rather than relying on PouchFilter, since this is exactly the kind of
     * operation that must be scoped correctly regardless of whether the
     * filter happens to be active for the calling route.
     *
     * @return list<Item>
     */
    public function findAllInPouch(int $pouchId): array
    {
        /** @var list<Item> $result */
        $result = $this->createQueryBuilder('i')
            ->where('i.pouch = :pouchId')
            ->setParameter('pouchId', $pouchId)
            ->getQuery()
            ->getResult();

        return $result;
    }

    // Scoped to the current pouch by PouchFilter — both call sites
    // (ItemService::assertNotDuplicate()/assertNotDuplicateOfAnotherItem())
    // only ever run for a normal, session-authenticated upload.
    public function findByContentHash(string $contentHash): ?Item
    {
        return $this->findOneBy(['contentHash' => $contentHash, 'trashedAt' => null]);
    }

    /**
     * Category/favorite/tags narrow the query directly (DQL, joining the
     * `tags` association); free-text search doesn't — tsvector has no
     * Doctrine DBAL type, so it's resolved via one raw SQL query first
     * (searchMatchingIds()) and the resulting ids are then filtered in here
     * too, so every criterion — including the text search — applies
     * together, not as two separate result sets ANDed in PHP.
     *
     * $pouchId: see findFilteredPage()'s own comment — matters for
     * searchMatchingIds() specifically, not just this method's own WHERE.
     *
     * @return list<Item>
     */
    public function findFiltered(ItemListFilter $filter, ?int $pouchId = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.trashedAt IS NULL');

        if (null !== $pouchId) {
            $qb->andWhere('i.pouch = :pouchId')
                ->setParameter('pouchId', $pouchId);
        }

        if ([] !== $filter->categoryIds) {
            $qb->andWhere('i.category IN (:categoryIds)')
                ->setParameter('categoryIds', $filter->categoryIds);
        }

        if ($filter->favoriteOnly) {
            $qb->andWhere('i.isFavorite = true');
        }

        if ([] !== $filter->tags) {
            $qb->join('i.tags', 't')
                ->andWhere('t.name IN (:tagNames)')
                ->setParameter('tagNames', $filter->tags)
                ->distinct();
        }

        $rankedIds = null;
        if (null !== $filter->query) {
            $rankedIds = $this->searchMatchingIds($filter->query, $pouchId);
            if ([] === $rankedIds) {
                return [];
            }

            $qb->andWhere('i.id IN (:matchingIds)')
                ->setParameter('matchingIds', $rankedIds);
        }

        $qb->orderBy('i.createdAt', 'DESC')->addOrderBy('i.id', 'DESC');

        /** @var list<Item> $items */
        $items = $qb->getQuery()->getResult();

        if (null === $rankedIds) {
            return $items;
        }

        // Re-sort by search relevance (ts_rank, computed in searchMatchingIds)
        // instead of the createdAt ordering the query above still carries.
        $itemsById = [];
        foreach ($items as $item) {
            $itemsById[$item->getId()] = $item;
        }

        return array_values(array_filter(array_map(
            static fn (int $id): ?Item => $itemsById[$id] ?? null,
            $rankedIds,
        )));
    }

    /**
     * Paginated counterpart of findFiltered() — that method stays as-is
     * (unpaginated) for callers that genuinely need the whole set
     * (CategoryExportService's ZIP walk); a full active-item response is
     * fine for a handful of items, a real problem at any real collection size.
     *
     * Without free-text search, the DB itself does the LIMIT/OFFSET. With
     * it, ranking needs to see every match before a page can be sliced off
     * it (searchMatchingIds() already computes the full ranked list) — the
     * slicing happens in PHP there instead, same as findFiltered()'s own
     * re-sort.
     *
     * $excludedCategoryIds: category locks excluded *before* COUNT/OFFSET/
     * LIMIT run — ItemController::list() computes what's locked first
     * (AccessKeyGuard::lockedCategoryIds()) and passes it in here, so a page
     * can never come back empty while unlocked items exist on the next one,
     * and $total never counts a hidden item. An item locked only by its
     * *own* key (category unlocked) is deliberately *not* excluded here — it
     * still appears in the page, redacted to a locked summary by
     * ItemController::list(), rather than disappearing entirely.
     *
     * $pouchId: scopes to one pouch when given. Optional — omit it to query
     * across pouches (AdminController's cross-pouch item browser does, when
     * no pouch is picked). Also threaded into searchMatchingIds() itself —
     * that raw SQL sits outside Doctrine's ORM/DQL layer, so PouchFilter
     * can't scope it the way it scopes this method's own query builder;
     * without $pouchId reaching it too, an exact match in another pouch
     * would (a) still get filtered out of the final result correctly by the
     * WHERE below, but (b) wrongly count as "found something", suppressing
     * the typo-tolerant fallback for a pouch that actually had no match at all.
     *
     * @param list<int> $excludedCategoryIds
     *
     * @return array{items: list<Item>, total: int}
     */
    public function findFilteredPage(
        ItemListFilter $filter,
        int $offset,
        int $limit,
        array $excludedCategoryIds = [],
        ?int $pouchId = null,
    ): array {
        $qb = $this->createQueryBuilder('i')
            ->where('i.trashedAt IS NULL');

        if (null !== $pouchId) {
            $qb->andWhere('i.pouch = :pouchId')
                ->setParameter('pouchId', $pouchId);
        }

        if ([] !== $filter->categoryIds) {
            $qb->andWhere('i.category IN (:categoryIds)')
                ->setParameter('categoryIds', $filter->categoryIds);
        }

        if ($filter->favoriteOnly) {
            $qb->andWhere('i.isFavorite = true');
        }

        if ([] !== $filter->tags) {
            $qb->join('i.tags', 't')
                ->andWhere('t.name IN (:tagNames)')
                ->setParameter('tagNames', $filter->tags)
                ->distinct();
        }

        if ([] !== $excludedCategoryIds) {
            $qb->andWhere('i.category NOT IN (:excludedCategoryIds)')
                ->setParameter('excludedCategoryIds', $excludedCategoryIds);
        }

        if (null === $filter->query) {
            $total = (clone $qb)->select('COUNT(DISTINCT i.id)')->getQuery()->getSingleScalarResult();

            /** @var list<Item> $items */
            $items = $qb->orderBy('i.createdAt', 'DESC')
                ->addOrderBy('i.id', 'DESC')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();

            return ['items' => $items, 'total' => is_numeric($total) ? (int) $total : 0];
        }

        $rankedIds = $this->searchMatchingIds($filter->query, $pouchId);
        if ([] === $rankedIds) {
            return ['items' => [], 'total' => 0];
        }

        /** @var list<Item> $matched */
        $matched = $qb->andWhere('i.id IN (:matchingIds)')
            ->setParameter('matchingIds', $rankedIds)
            ->getQuery()
            ->getResult();

        $itemsById = [];
        foreach ($matched as $item) {
            $itemsById[$item->getId()] = $item;
        }

        // Every id in $rankedIds that also survived the category/favorite/
        // tags filters above, still in relevance order.
        $orderedIds = array_values(array_filter($rankedIds, static fn (int $id): bool => isset($itemsById[$id])));

        // Not array_map() over array_slice($orderedIds, ...) — PHPStan can't
        // see through that chain that every id it'd produce is guaranteed to
        // be a key of $itemsById (true by construction: $orderedIds was
        // already filtered to ids isset() in it), so it flags the offset
        // access as possibly undefined. An explicit isset() per id here says
        // the same thing in a way it can verify, and builds an already-list
        // array directly — no separate array_values() call needed after.
        $items = [];
        foreach (array_slice($orderedIds, $offset, $limit) as $id) {
            if (isset($itemsById[$id])) {
                $items[] = $itemsById[$id];
            }
        }

        return [
            'items' => $items,
            'total' => count($orderedIds),
        ];
    }

    /**
     * Newest-trashed-first — no filters (category/tags/favorite/search) here,
     * unlike findFilteredPage(): the trash is a flat "what's about to be
     * purged" view, not something worth narrowing further.
     *
     * @param list<int> $excludedCategoryIds
     *
     * @return array{items: list<Item>, total: int}
     */
    public function findTrashedPage(int $offset, int $limit, array $excludedCategoryIds = [], ?int $pouchId = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.trashedAt IS NOT NULL');

        if (null !== $pouchId) {
            $qb->andWhere('i.pouch = :pouchId')
                ->setParameter('pouchId', $pouchId);
        }

        if ([] !== $excludedCategoryIds) {
            $qb->andWhere('i.category NOT IN (:excludedCategoryIds)')
                ->setParameter('excludedCategoryIds', $excludedCategoryIds);
        }

        $total = (clone $qb)->select('COUNT(i.id)')->getQuery()->getSingleScalarResult();

        /** @var list<Item> $items */
        $items = $qb->orderBy('i.trashedAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => is_numeric($total) ? (int) $total : 0];
    }

    /**
     * A short excerpt per item, matched fragment wrapped in
     * SNIPPET_HIGHLIGHT_START/END, for whichever of $itemIds actually got a
     * *text* match — computed only for a page's worth of ids (the caller's
     * job), never the full result set, since ts_headline() re-runs full text
     * matching against each document instead of reading search_vector.
     *
     * An id in $itemIds that matched only by tag (search_vector itself
     * doesn't contain $query) or only via the typo-tolerant trigram fallback
     * (search_vector by definition doesn't contain $query either — that's
     * why the fallback ran at all) simply doesn't come back in the result:
     * ts_headline() has nothing to legitimately highlight there, and
     * returning an arbitrary, unhighlighted excerpt of the document's start
     * instead would look like a match explanation without being one.
     *
     * Same field list/order as search_vector's own weighting (name >
     * page_title > note_content > extracted_text/page_description/url) —
     * ts_headline() picks its fragment from wherever $query actually landed
     * in that combined text.
     *
     * @param list<int> $itemIds
     *
     * @return array<int, string> keyed by item id
     */
    public function findSnippets(array $itemIds, string $query): array
    {
        if ([] === $itemIds) {
            return [];
        }

        $document = self::DOCUMENT_EXPRESSION;
        $sql = <<<SQL
            SELECT i.item_id, ts_headline(
                'simple',
                {$document},
                to_tsquery('simple', :tsQuery),
                :options
            ) AS snippet
            FROM item i
            WHERE i.item_id IN (:itemIds)
              AND i.search_vector @@ to_tsquery('simple', :tsQuery)
            SQL;

        $rows = $this->getEntityManager()->getConnection()->fetchAllKeyValue($sql, [
            'tsQuery' => $this->buildPrefixTsQuery($query),
            'options' => 'StartSel=' . self::SNIPPET_HIGHLIGHT_START . ', StopSel=' . self::SNIPPET_HIGHLIGHT_END . ', MaxFragments=1, MaxWords=15, MinWords=5',
            'itemIds' => $itemIds,
        ], [
            'itemIds' => ArrayParameterType::INTEGER,
        ]);

        $snippets = [];
        foreach ($rows as $itemId => $snippet) {
            $snippets[(int) $itemId] = is_string($snippet) ? $snippet : '';
        }

        return $snippets;
    }

    /**
     * Free-text search across name, tags, note content, OCR text and
     * OpenGraph title/description in one query — item.search_vector is a
     * generated column combining the item's own text fields (see the Part 6
     * migration); tag names are matched separately since they live in a
     * joined table a generated column can't reach.
     *
     * Falls back to trigram similarity (pg_trgm) only when this exact,
     * prefix-based match returns nothing — a typo anywhere in the query
     * makes `to_tsquery`'s prefix match fail outright, and re-running that
     * same failing match on every keystroke would be wasted work on the
     * common (correctly spelled) path.
     *
     * $pouchId: this raw SQL sits outside Doctrine's ORM/DQL layer, so
     * PouchFilter can't scope it automatically the way it scopes every
     * other item lookup — omitting it here isn't just a privacy gap (the
     * caller's own DQL requery already filters the *result* correctly), it
     * breaks the "did we find anything" decision this method's caller makes:
     * an exact match that only exists in a *different* pouch would still
     * make this return a non-empty list, wrongly skipping the fuzzy
     * fallback for a pouch that had no match of its own at all. Passing it
     * through to the fallback too keeps FUZZY_MATCH_LIMIT a per-pouch limit
     * instead of one another pouch's matches could fill up entirely. Null
     * (AdminController's cross-pouch browse) keeps both queries unscoped,
     * same as before.
     *
     * @return list<int> item ids, ordered by relevance (best match first)
     */
    private function searchMatchingIds(string $query, ?int $pouchId): array
    {
        $sql = <<<'SQL'
            SELECT i.item_id, ts_rank(i.search_vector, to_tsquery('simple', :tsQuery)) AS rank
            FROM item i
            WHERE i.trashed_at IS NULL
              AND (:pouchId::int IS NULL OR i.pouch_id = :pouchId)
              AND (
                  i.search_vector @@ to_tsquery('simple', :tsQuery)
                  OR EXISTS (
                      SELECT 1 FROM item_tag it
                      JOIN tag t ON t.tag_id = it.tag_id
                      WHERE it.item_id = i.item_id AND t.name ILIKE :likeQuery ESCAPE '\'
                  )
              )
            ORDER BY rank DESC, i.created_at DESC, i.item_id DESC
            SQL;

        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, [
            'tsQuery'   => $this->buildPrefixTsQuery($query),
            'likeQuery' => '%' . $this->escapeLikeWildcards($query) . '%',
            'pouchId'   => $pouchId,
        ]);

        $ids = array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $rows);

        return [] !== $ids ? $ids : $this->searchMatchingIdsFuzzy($query, $pouchId);
    }

    /**
     * @return list<int> item ids, ordered by similarity (closest match first)
     */
    private function searchMatchingIdsFuzzy(string $query, ?int $pouchId): array
    {
        $document = self::DOCUMENT_EXPRESSION;
        $sql = <<<SQL
            SELECT i.item_id
            FROM item i
            WHERE i.trashed_at IS NULL
              AND (:pouchId::int IS NULL OR i.pouch_id = :pouchId)
              AND similarity({$document}, :query) > :threshold
            ORDER BY similarity({$document}, :query) DESC, i.created_at DESC, i.item_id DESC
            LIMIT :limit
            SQL;

        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, [
            'query'     => $query,
            'threshold' => self::FUZZY_SIMILARITY_THRESHOLD,
            'limit'     => self::FUZZY_MATCH_LIMIT,
            'pouchId'   => $pouchId,
        ]);

        return array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $rows);
    }

    /**
     * Each word becomes a `word:*` prefix match, ANDed together — lets
     * search-as-you-type match "doku" against "dokumenty" instead of
     * requiring the whole word (plainto_tsquery()'s tokenizer has no prefix
     * mode). Words are reduced to letters/digits before being placed in the
     * tsquery string, so punctuation in the input (which has meaning in
     * tsquery syntax — `&`, `|`, `:`, `(`...) can't produce anything but a
     * plain `word:*` term.
     */
    private function buildPrefixTsQuery(string $query): string
    {
        preg_match_all('/[\p{L}\p{N}]+/u', $query, $matches);

        return implode(' & ', array_map(static fn (string $word): string => $word . ':*', $matches[0]));
    }

    /**
     * A search term containing a literal "%" or "_" (e.g. "50% off") must
     * match that literal text, not act as a LIKE/ILIKE wildcard — escape
     * both (and the escape character itself) before wrapping the term in the
     * pattern's own "%...%". Paired with the query's `ESCAPE '\'` clause.
     */
    private function escapeLikeWildcards(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @return list<Item>
     */
    public function findOverdueForTrash(DateTimeImmutable $now, ?int $pouchId = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.trashedAt IS NULL')
            ->andWhere('i.keepForever = false')
            ->andWhere('i.expiresAt IS NOT NULL')
            ->andWhere('i.expiresAt <= :now')
            ->setParameter('now', $now);

        if (null !== $pouchId) {
            $qb->andWhere('i.pouch = :pouchId')->setParameter('pouchId', $pouchId);
        }

        /** @var list<Item> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * @return list<Item>
     */
    public function findOverdueForPurge(DateTimeImmutable $trashedBefore, ?int $pouchId = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.trashedAt IS NOT NULL')
            ->andWhere('i.trashedAt <= :trashedBefore')
            ->setParameter('trashedBefore', $trashedBefore);

        if (null !== $pouchId) {
            $qb->andWhere('i.pouch = :pouchId')->setParameter('pouchId', $pouchId);
        }

        /** @var list<Item> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Storage dashboard: total bytes + item count per type, for every type
     * that actually carries a $size (URL/NOTE items don't, and are
     * meaningless as "storage usage") — includes trashed-but-not-yet-purged
     * items on purpose: their storage object is still sitting in MinIO/S3
     * either way, so it's still real usage until GC purges it. $pouchId
     * scopes to one pouch when given.
     *
     * @return array<string, array{totalBytes: int, itemCount: int}> keyed by ItemType value
     */
    public function sumSizeByType(?int $pouchId = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->select('i.type as type', 'SUM(i.size) as totalBytes', 'COUNT(i.id) as itemCount')
            ->where('i.size IS NOT NULL')
            ->groupBy('i.type');

        if (null !== $pouchId) {
            $qb->andWhere('i.pouch = :pouchId')->setParameter('pouchId', $pouchId);
        }

        /** @var list<array{type: mixed, totalBytes: mixed, itemCount: mixed}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        $byType = [];
        foreach ($rows as $row) {
            $rawType = $row['type'];
            $type = match (true) {
                $rawType instanceof ItemType => $rawType->value,
                is_scalar($rawType)          => (string) $rawType,
                default                      => '',
            };
            $totalBytes = is_numeric($row['totalBytes']) ? (int) $row['totalBytes'] : 0;
            $itemCount = is_numeric($row['itemCount']) ? (int) $row['itemCount'] : 0;
            $byType[$type] = ['totalBytes' => $totalBytes, 'itemCount' => $itemCount];
        }

        return $byType;
    }

    /**
     * CategoryService::delete() calls this before removing a category,
     * across the whole subtree it and its descendants form —
     * deleting a category with any item still in it would otherwise cascade
     * at the DB level (ON DELETE CASCADE) without ItemGarbageCollector::
     * purgeTrash() ever getting a chance to see them, orphaning their
     * storage objects in S3/MinIO forever.
     *
     * Deliberately *not* filtered to `trashedAt IS NULL` (an earlier version
     * of this method was) — a trashed-but-not-yet-purged item still has a
     * live storage object sitting in the bucket right up until GC's next run
     * actually deletes it; only after purgeTrash() has run is a category
     * truly safe to remove.
     *
     * @param list<int> $categoryIds
     */
    public function existsInCategories(array $categoryIds): bool
    {
        if ([] === $categoryIds) {
            return false;
        }

        $count = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.category IN (:categoryIds)')
            ->setParameter('categoryIds', $categoryIds)
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();

        return is_numeric($count) && (int) $count > 0;
    }

    /**
     * "Lista itemów wygasających w ciągu najbliższych 24h" — generalized to
     * any window, not just exactly 24h, since the endpoint itself is the one
     * deciding what "soon" means. $pouchId scopes to one pouch when given.
     *
     * @return list<Item>
     */
    public function findExpiringBetween(DateTimeImmutable $from, DateTimeImmutable $until, ?int $pouchId = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.trashedAt IS NULL')
            ->andWhere('i.keepForever = false')
            ->andWhere('i.expiresAt IS NOT NULL')
            ->andWhere('i.expiresAt BETWEEN :from AND :until')
            ->setParameter('from', $from)
            ->setParameter('until', $until)
            ->orderBy('i.expiresAt', 'ASC');

        if (null !== $pouchId) {
            $qb->andWhere('i.pouch = :pouchId')->setParameter('pouchId', $pouchId);
        }

        /** @var list<Item> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
