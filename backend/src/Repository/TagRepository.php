<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\Item;
use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use function array_diff;
use function array_map;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * Only tags actually attached to a live (non-trashed) item — a name that
     * lost its last item (replaced away, or the item trashed/deleted) isn't
     * "in use" any more, even though the Tag row itself lingers (tags have no
     * delete-on-orphan cleanup; a row is cheap to keep, a stale autocomplete
     * suggestion isn't).
     *
     * `SELECT t FROM Item i JOIN i.tags t` reads naturally but DQL rejects
     * it outright — a query's root alias (`i`, from Item's FROM) must be
     * among the selected identification variables, a joined-only alias like
     * `t` isn't enough (Doctrine\ORM\Query\Parser::processRootEntityAliasSelected).
     * Making Tag the root and using MEMBER OF in a WHERE EXISTS sidesteps
     * that restriction rather than selecting `i, t` and discarding `i`.
     *
     * @return list<Tag>
     */
    public function findAllOrderedByName(): array
    {
        /** @var list<Tag> $result */
        $result = $this->createQueryBuilder('t')
            ->where('EXISTS (SELECT i.id FROM ' . Item::class . ' i WHERE t MEMBER OF i.tags AND i.trashedAt IS NULL)')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Looks up existing tags by (already normalized — see TagService) name
     * and creates the ones that don't exist yet, so callers never have to
     * care which tags are new.
     *
     * @param list<string> $names
     *
     * @return list<Tag>
     */
    public function findOrCreateByNames(array $names): array
    {
        if ([] === $names) {
            return [];
        }

        /** @var list<Tag> $existing */
        $existing = $this->createQueryBuilder('t')
            ->where('t.name IN (:names)')
            ->setParameter('names', $names)
            ->getQuery()
            ->getResult();

        $existingNames = array_map(static fn (Tag $tag): string => $tag->getName(), $existing);
        $missingNames = array_diff($names, $existingNames);

        $created = [];
        foreach ($missingNames as $name) {
            $tag = new Tag($name);
            $this->getEntityManager()->persist($tag);
            $created[] = $tag;
        }

        if ([] !== $created) {
            $this->getEntityManager()->flush();
        }

        return [...$existing, ...$created];
    }
}
