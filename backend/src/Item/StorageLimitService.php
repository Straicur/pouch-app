<?php

declare(strict_types = 1);

namespace App\Item;

use App\Entity\StorageLimit;
use App\Enum\ItemType;
use App\Repository\StorageLimitRepository;
use LogicException;
use Override;

final readonly class StorageLimitService implements StorageLimitServiceInterface
{
    /**
     * The limits FileValidator/ImageValidator shipped with before Part 10 —
     * kept here as the fallback for any type an admin hasn't overridden yet,
     * so a bare install behaves exactly as it did before this table existed.
     *
     * @var array<string, int>
     */
    private const array DEFAULT_MAX_SIZE_BYTES = [
        'file'  => 100 * 1024 * 1024,
        'photo' => 25 * 1024 * 1024,
    ];

    public function __construct(
        private StorageLimitRepository $storageLimitRepository,
    ) {}

    #[Override]
    public function getMaxSizeBytes(ItemType $type): int
    {
        $override = $this->storageLimitRepository->findByType($type)?->getMaxSizeBytes();
        if (null !== $override) {
            return $override;
        }

        return self::DEFAULT_MAX_SIZE_BYTES[$type->value]
            ?? throw new LogicException($type->value . ' has no built-in size default — only FILE/PHOTO are meant to be asked here');
    }

    #[Override]
    public function setMaxSizeBytes(ItemType $type, int $maxSizeBytes): void
    {
        $limit = $this->storageLimitRepository->findByType($type);

        if (null === $limit) {
            $limit = new StorageLimit($type, $maxSizeBytes);
        } else {
            $limit->setMaxSizeBytes($maxSizeBytes);
        }

        $this->storageLimitRepository->save($limit);
    }

    #[Override]
    public function getAllMaxSizeBytes(): array
    {
        $limits = [];
        foreach (self::DEFAULT_MAX_SIZE_BYTES as $type => $default) {
            $limits[$type] = $this->getMaxSizeBytes(ItemType::from($type));
        }

        return $limits;
    }
}
