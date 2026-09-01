<?php

declare(strict_types = 1);

namespace App\Services\Tag;

use App\Entity\Tag;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\ConflictException\ConflictException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;
use App\Repository\TagRepository;
use App\Services\Pouch\CurrentPouchResolverInterface;
use Override;

use function array_keys;
use function count;
use function mb_strlen;
use function mb_strtolower;
use function trim;

class TagService implements TagServiceInterface
{
    private const int MAX_TAG_LENGTH = 50;

    private const int MAX_TAGS_PER_ITEM = 20;

    public function __construct(
        private readonly TagRepository $tagRepository,
        private readonly CurrentPouchResolverInterface $currentPouchResolver,
    ) {}

    #[Override]
    public function listInUse(): array
    {
        return $this->tagRepository->findInUseOrderedByName();
    }

    #[Override]
    public function listAll(): array
    {
        return $this->tagRepository->findAllOrderedByName();
    }

    #[Override]
    public function getById(int $id): Tag
    {
        // findOneBy(), not find(): find() checks Doctrine's identity map
        // *before* running any SQL, bypassing PouchFilter entirely for an
        // id already loaded elsewhere in this request — findOneBy() always
        // executes the (filtered) query. Another pouch's tag is filtered
        // out at the SQL level and so looks exactly like a missing one.
        $tag = $this->tagRepository->findOneBy(['id' => $id]);
        if (null === $tag) {
            throw new NotFoundException(message: 'tag.not_found');
        }

        return $tag;
    }

    #[Override]
    public function create(string $name): Tag
    {
        $normalized = $this->normalizeName($name);
        $this->assertNameNotTaken($normalized, excludeId: null);

        $tag = new Tag($normalized, $this->currentPouchResolver->resolve());
        $this->tagRepository->save($tag);

        return $tag;
    }

    #[Override]
    public function rename(int $id, string $name): Tag
    {
        $tag = $this->getById($id);
        $normalized = $this->normalizeName($name);
        $this->assertNameNotTaken($normalized, excludeId: $tag->getId());

        $tag->setName($normalized);
        $this->tagRepository->save($tag);

        return $tag;
    }

    #[Override]
    public function delete(int $id): void
    {
        $tag = $this->getById($id);

        // No "still in use" guard, unlike CategoryService::delete() — a tag
        // is a label, not a container: item_tag.tag_id is ON DELETE CASCADE
        // (see Item::$tags), so removing a tag just untags whatever items
        // had it, nothing is orphaned or lost.
        $this->tagRepository->remove($tag);
    }

    #[Override]
    public function resolveTags(array $names): array
    {
        // Keyed by the normalized form so "Work" and "work" collapse into one
        // entry instead of round-tripping to the DB as two different tags.
        $normalized = [];

        foreach ($names as $name) {
            $clean = trim($name);
            if ('' === $clean) {
                continue;
            }

            if (mb_strlen($clean) > self::MAX_TAG_LENGTH) {
                throw new BadRequestException(message: 'tag.too_long');
            }

            $normalized[mb_strtolower($clean)] = true;
        }

        if (count($normalized) > self::MAX_TAGS_PER_ITEM) {
            throw new BadRequestException(message: 'tag.too_many');
        }

        return $this->tagRepository->findOrCreateByNames(array_keys($normalized), $this->currentPouchResolver->resolve());
    }

    private function normalizeName(string $name): string
    {
        $clean = trim($name);
        if ('' === $clean) {
            throw new BadRequestException(message: 'tag.blank');
        }

        if (mb_strlen($clean) > self::MAX_TAG_LENGTH) {
            throw new BadRequestException(message: 'tag.too_long');
        }

        return mb_strtolower($clean);
    }

    private function assertNameNotTaken(string $normalizedName, ?int $excludeId): void
    {
        $existing = $this->tagRepository->findOneBy(['name' => $normalizedName]);
        if (null !== $existing && $existing->getId() !== $excludeId) {
            throw new ConflictException(message: 'tag.name_taken');
        }
    }
}
