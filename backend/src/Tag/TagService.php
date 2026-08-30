<?php

declare(strict_types = 1);

namespace App\Tag;

use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\Repository\TagRepository;
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
    ) {}

    #[Override]
    public function listAll(): array
    {
        return $this->tagRepository->findAllOrderedByName();
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

        return $this->tagRepository->findOrCreateByNames(array_keys($normalized));
    }
}
