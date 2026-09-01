<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\Item;
use App\Enum\ItemType;
use App\Services\Item\ValueObject\ItemListFilter;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function count;
use function implode;
use function is_numeric;
use function is_scalar;
use function preg_match_all;
use function str_replace;

/**
 * @extends ServiceEntityRepository<Item>
 */
class ItemRepository extends ServiceEntityRepository
{
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
     * @return list<Item>
     */
    public function findFiltered(ItemListFilter $filter): array
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.trashedAt IS NULL');

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
            $rankedIds = $this->searchMatchingIds($filter->query);
            if ([] === $rankedIds) {
                return [];
            }

            $qb->andWhere('i.id IN (:matchingIds)')
                ->setParameter('matchingIds', $rankedIds);
        }

        $qb->orderBy('i.createdAt', 'DESC');

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
     * across pouches.
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
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();

            return ['items' => $items, 'total' => is_numeric($total) ? (int) $total : 0];
        }

        $rankedIds = $this->searchMatchingIds($filter->query);
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
     * Free-text search across name, tags, note content, OCR text and
     * OpenGraph title/description in one query — item.search_vector is a
     * generated column combining the item's own text fields (see the Part 6
     * migration); tag names are matched separately since they live in a
     * joined table a generated column can't reach.
     *
     * @return list<int> item ids, ordered by relevance (best match first)
     */
    private function searchMatchingIds(string $query): array
    {
        $sql = <<<'SQL'
            SELECT i.item_id, ts_rank(i.search_vector, to_tsquery('simple', :tsQuery)) AS rank
            FROM item i
            WHERE i.trashed_at IS NULL
              AND (
                  i.search_vector @@ to_tsquery('simple', :tsQuery)
                  OR EXISTS (
                      SELECT 1 FROM item_tag it
                      JOIN tag t ON t.tag_id = it.tag_id
                      WHERE it.item_id = i.item_id AND t.name ILIKE :likeQuery ESCAPE '\'
                  )
              )
            ORDER BY rank DESC, i.created_at DESC
            SQL;

        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, [
            'tsQuery'   => $this->buildPrefixTsQuery($query),
            'likeQuery' => '%' . $this->escapeLikeWildcards($query) . '%',
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
     * Post-review fix: CategoryService::delete() calls this before removing a
     * category, across the whole subtree it and its descendants form —
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
