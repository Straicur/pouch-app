<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use function array_diff;
use function array_map;
use function array_values;

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
     * @return list<Tag>
     */
    public function findAllOrderedByName(): array
    {
        return array_values($this->findBy([], ['name' => 'ASC']));
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
