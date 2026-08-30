<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\Item;
use App\Item\ItemListFilter;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use function array_filter;
use function array_map;
use function array_values;
use function is_numeric;

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

        if (null !== $filter->categoryId) {
            $qb->andWhere('i.category = :categoryId')
                ->setParameter('categoryId', $filter->categoryId);
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
            SELECT i.item_id, ts_rank(i.search_vector, plainto_tsquery('simple', :query)) AS rank
            FROM item i
            WHERE i.trashed_at IS NULL
              AND (
                  i.search_vector @@ plainto_tsquery('simple', :query)
                  OR EXISTS (
                      SELECT 1 FROM item_tag it
                      JOIN tag t ON t.tag_id = it.tag_id
                      WHERE it.item_id = i.item_id AND t.name ILIKE :likeQuery
                  )
              )
            ORDER BY rank DESC, i.created_at DESC
            SQL;

        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, [
            'query'     => $query,
            'likeQuery' => '%' . $query . '%',
        ]);

        return array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $rows);
    }

    /**
     * @return list<Item>
     */
    public function findOverdueForTrash(DateTimeImmutable $now): array
    {
        /** @var list<Item> $result */
        $result = $this->createQueryBuilder('i')
            ->where('i.trashedAt IS NULL')
            ->andWhere('i.keepForever = false')
            ->andWhere('i.expiresAt IS NOT NULL')
            ->andWhere('i.expiresAt <= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return list<Item>
     */
    public function findOverdueForPurge(DateTimeImmutable $trashedBefore): array
    {
        /** @var list<Item> $result */
        $result = $this->createQueryBuilder('i')
            ->where('i.trashedAt IS NOT NULL')
            ->andWhere('i.trashedAt <= :trashedBefore')
            ->setParameter('trashedBefore', $trashedBefore)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
