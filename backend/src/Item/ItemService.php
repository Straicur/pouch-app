<?php

declare(strict_types = 1);

namespace App\Item;

use App\Category\CategoryServiceInterface;
use App\Entity\Item;
use App\Enum\ItemProcessingStatus;
use App\Enum\ItemType;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\ConflictException\ConflictException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;
use App\Message\ProcessPhotoMessage;
use App\Message\ScrapeUrlMessage;
use App\Repository\ItemRepository;
use App\Storage\StorageServiceInterface;
use DateInterval;
use DateTimeImmutable;
use Override;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

use function fclose;
use function fopen;
use function hash_file;
use function is_resource;
use function pathinfo;
use function sprintf;
use function strtolower;

use const PATHINFO_EXTENSION;

class ItemService implements ItemServiceInterface
{
    private const string DEFAULT_STORAGE_PREFIX = 'items';

    public function __construct(
        private readonly ItemRepository $itemRepository,
        private readonly CategoryServiceInterface $categoryService,
        private readonly StorageServiceInterface $storageService,
        private readonly FileValidator $fileValidator,
        private readonly ImageValidator $imageValidator,
        private readonly UrlValidator $urlValidator,
        private readonly MessageBusInterface $messageBus,
    ) {}

    #[Override]
    public function createFile(
        int $categoryId,
        string $tmpPath,
        string $originalFilename,
        string $mimeType,
        int $size,
        ItemLifecycleOptions $options,
    ): Item {
        $category = $this->categoryService->getById($categoryId);
        $this->fileValidator->assertValid($originalFilename, $size);
        $contentHash = $this->assertNotDuplicate($tmpPath);

        $item = new Item(
            category: $category,
            type: ItemType::FILE,
            name: $options->name ?? $originalFilename,
            keepForever: $options->keepForever,
            expiresAt: $this->resolveExpiresAt($options),
            processingStatus: ItemProcessingStatus::COMPLETED,
        );

        $storageKey = $this->uploadToStorage($tmpPath, $originalFilename);
        $item->setFileData($originalFilename, $mimeType, $size, $storageKey, $contentHash);

        $this->itemRepository->save($item);

        return $item;
    }

    #[Override]
    public function createUrl(
        int $categoryId,
        string $url,
        ItemLifecycleOptions $options,
    ): Item {
        $category = $this->categoryService->getById($categoryId);
        $this->urlValidator->assertValid($url);

        $item = new Item(
            category: $category,
            type: ItemType::URL,
            name: $options->name ?? $url,
            keepForever: $options->keepForever,
            expiresAt: $this->resolveExpiresAt($options),
            processingStatus: ItemProcessingStatus::PENDING,
        );
        $item->setUrl($url);
        $this->itemRepository->save($item);

        $this->messageBus->dispatch(new ScrapeUrlMessage($item->getId()));

        return $item;
    }

    #[Override]
    public function createPhoto(
        int $categoryId,
        string $tmpPath,
        string $originalFilename,
        string $mimeType,
        int $size,
        ItemLifecycleOptions $options,
    ): Item {
        $category = $this->categoryService->getById($categoryId);
        $this->imageValidator->assertValid($originalFilename, $size);
        $contentHash = $this->assertNotDuplicate($tmpPath);

        $item = new Item(
            category: $category,
            type: ItemType::PHOTO,
            name: $options->name ?? $originalFilename,
            keepForever: $options->keepForever,
            expiresAt: $this->resolveExpiresAt($options),
            processingStatus: ItemProcessingStatus::PENDING,
        );

        $storageKey = $this->uploadToStorage($tmpPath, $originalFilename);
        $item->setFileData($originalFilename, $mimeType, $size, $storageKey, $contentHash);

        $this->itemRepository->save($item);

        $this->messageBus->dispatch(new ProcessPhotoMessage($item->getId()));

        return $item;
    }

    #[Override]
    public function getById(int $id): Item
    {
        $item = $this->itemRepository->find($id);

        if (null === $item || $item->isTrashed()) {
            throw new NotFoundException(message: 'Item not found');
        }

        return $item;
    }

    #[Override]
    public function list(?int $categoryId): array
    {
        return $this->itemRepository->findActive($categoryId);
    }

    #[Override]
    public function delete(int $id): void
    {
        $item = $this->getById($id);
        $item->trash(new DateTimeImmutable());
        $this->itemRepository->save($item);
    }

    /**
     * @throws BadRequestException
     * @throws ConflictException
     */
    private function assertNotDuplicate(string $tmpPath): string
    {
        $contentHash = hash_file('sha256', $tmpPath);
        if (false === $contentHash) {
            throw new BadRequestException(message: 'Could not read the uploaded file');
        }

        $duplicate = $this->itemRepository->findByContentHash($contentHash);
        if (null !== $duplicate) {
            throw new ConflictException(
                message: sprintf('Identical content already exists as item #%d ("%s")', $duplicate->getId(), $duplicate->getName()),
                conflictingItemId: $duplicate->getId(),
            );
        }

        return $contentHash;
    }

    /**
     * @throws BadRequestException
     */
    private function uploadToStorage(string $tmpPath, string $originalFilename): string
    {
        $storageKey = $this->generateStorageKey($originalFilename);
        $stream = fopen($tmpPath, 'r');
        if (false === is_resource($stream)) {
            throw new BadRequestException(message: 'Could not read the uploaded file');
        }

        try {
            $this->storageService->upload($storageKey, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $storageKey;
    }

    /**
     * @throws BadRequestException
     */
    private function resolveExpiresAt(ItemLifecycleOptions $options): ?DateTimeImmutable
    {
        if ($options->keepForever) {
            return null;
        }

        $now = new DateTimeImmutable();

        if (null !== $options->customExpiresAt) {
            if ($options->customExpiresAt <= $now) {
                throw new BadRequestException(message: 'expiresAt must be in the future');
            }

            return $options->customExpiresAt;
        }

        if (null !== $options->ttlPreset) {
            return $now->add($options->ttlPreset->toDateInterval());
        }

        // Product doc: default TTL is 1 day when nothing else is specified.
        return $now->add(new DateInterval('P1D'));
    }

    private function generateStorageKey(string $originalFilename): string
    {
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $uuid = Uuid::v4()->toRfc4122();

        if ('' === $extension) {
            return sprintf('%s/%s', self::DEFAULT_STORAGE_PREFIX, $uuid);
        }

        return sprintf('%s/%s.%s', self::DEFAULT_STORAGE_PREFIX, $uuid, $extension);
    }
}
