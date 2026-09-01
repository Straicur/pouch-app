<?php

declare(strict_types = 1);

namespace App\Services\Item;

use App\Entity\Item;
use App\Entity\ItemVersion;
use App\Enum\ItemProcessingStatus;
use App\Enum\ItemType;
use App\Exception\StorageException;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\ConflictException\ConflictException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;
use App\Messenger\ProcessPhotoMessage;
use App\Messenger\ScrapeUrlMessage;
use App\Repository\ItemRepository;
use App\Repository\ItemVersionRepository;
use App\Services\Category\CategoryServiceInterface;
use App\Services\Item\Validator\FileValidator;
use App\Services\Item\Validator\ImageValidator;
use App\Services\Item\Validator\NoteValidator;
use App\Services\Item\Validator\UrlValidator;
use App\Services\Item\ValueObject\ItemLifecycleOptions;
use App\Services\Item\ValueObject\ItemListFilter;
use App\Services\Pouch\CurrentPouchResolverInterface;
use App\Services\Storage\StorageServiceInterface;
use App\Services\Tag\TagServiceInterface;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Override;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

use function count;
use function fclose;
use function fopen;
use function hash_file;
use function is_array;
use function is_numeric;
use function is_resource;
use function ltrim;
use function mb_strlen;
use function mb_substr;
use function pathinfo;
use function sprintf;
use function strtok;
use function strtolower;
use function trim;

use const PATHINFO_EXTENSION;

class ItemService implements ItemServiceInterface
{
    private const string DEFAULT_STORAGE_PREFIX = 'items';

    public function __construct(
        private readonly ItemRepository $itemRepository,
        private readonly ItemVersionRepository $itemVersionRepository,
        private readonly CategoryServiceInterface $categoryService,
        private readonly StorageServiceInterface $storageService,
        private readonly FileValidator $fileValidator,
        private readonly ImageValidator $imageValidator,
        private readonly UrlValidator $urlValidator,
        private readonly NoteValidator $noteValidator,
        private readonly TagServiceInterface $tagService,
        private readonly MessageBusInterface $messageBus,
        private readonly TranslatorInterface $translator,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        // Only for list()/listPage()'s free-text search — searchMatchingIds()
        // runs outside Doctrine's ORM/DQL layer (raw SQL), so PouchFilter
        // can't scope it the way it scopes every other lookup here. Not a
        // reintroduction of manual scoping generally, just the one place it
        // structurally has to stay manual — see
        // ItemRepository::findFilteredPage()'s own comment on $pouchId.
        private readonly CurrentPouchResolverInterface $currentPouchResolver,
    ) {}

    #[Override]
    public function createFile(
        int $categoryId,
        string $tmpPath,
        string $originalFilename,
        string $mimeType,
        int $size,
        ItemLifecycleOptions $options,
        ?string $content = null,
        array $tags = [],
    ): Item {
        $category = $this->categoryService->getById($categoryId);
        $this->fileValidator->assertValid($originalFilename, $mimeType, $size);
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

        if (null !== $content && '' !== trim($content)) {
            $this->noteValidator->assertValid($content);
            $item->setNoteContent($content);
        }

        $item->setTags($this->tagService->resolveTags($tags));

        $this->saveNewItemOrConflict($item);

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
        $this->imageValidator->assertValid($originalFilename, $mimeType, $size);
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

        $this->saveNewItemOrConflict($item);

        $this->messageBus->dispatch(new ProcessPhotoMessage($item->getId()));

        return $item;
    }

    #[Override]
    public function createNote(
        int $categoryId,
        string $content,
        ItemLifecycleOptions $options,
        array $tags = [],
    ): Item {
        $category = $this->categoryService->getById($categoryId);
        $this->noteValidator->assertValid($content);

        $item = new Item(
            category: $category,
            type: ItemType::NOTE,
            name: $options->name ?? $this->deriveNoteName($content),
            keepForever: $options->keepForever,
            expiresAt: $this->resolveExpiresAt($options),
            processingStatus: ItemProcessingStatus::COMPLETED,
        );
        $item->setNoteContent($content);
        $item->setTags($this->tagService->resolveTags($tags));

        $this->itemRepository->save($item);

        return $item;
    }

    #[Override]
    public function updateNoteContent(int $id, string $content): Item
    {
        $item = $this->getById($id);
        if (ItemType::NOTE !== $item->getType()) {
            throw new BadRequestException(message: 'item.not_a_note');
        }

        $this->noteValidator->assertValid($content);
        $item->setNoteContent($content);
        $this->itemRepository->save($item);

        return $item;
    }

    #[Override]
    public function move(int $id, int $categoryId): Item
    {
        $item = $this->getById($id);
        // Pouch-scoped, same as getById() — a category id from another
        // pouch 404s here, so an item can never end up pointing at a
        // category outside its own (denormalized, never-changing) pouch.
        $category = $this->categoryService->getById($categoryId);

        $item->setCategory($category);
        $this->itemRepository->save($item);

        return $item;
    }

    // Scoped to the current pouch by PouchFilter for a normal,
    // session-authenticated request (see PouchFilterListener), so this
    // stays a plain lookup rather than a separate pouch-checking method.
    // PouchFilterListener leaves the filter off for the signed-URL download
    // family and for /api/admin, where this same method is deliberately
    // unscoped instead.
    //
    // findOneBy(), not find(): find() checks Doctrine's identity map
    // *before* running any SQL, bypassing PouchFilter entirely for an id
    // already loaded elsewhere in this request — findOneBy() always
    // executes the (filtered) query.
    #[Override]
    public function getById(int $id): Item
    {
        $item = $this->itemRepository->findOneBy(['id' => $id]);

        if (null === $item || $item->isTrashed()) {
            throw new NotFoundException(message: 'item.not_found');
        }

        return $item;
    }

    #[Override]
    public function list(ItemListFilter $filter): array
    {
        // Explicit pouchId (unlike most lookups here) only because
        // findFiltered() -> searchMatchingIds() sits outside PouchFilter's
        // reach when $filter->query is set — see the constructor's own
        // comment on $currentPouchResolver. Every other criterion is still
        // scoped automatically by PouchFilter regardless.
        return $this->itemRepository->findFiltered($filter, $this->currentPouchResolver->resolve()->getId());
    }

    #[Override]
    public function listPage(ItemListFilter $filter, int $offset, int $limit, array $excludedCategoryIds = []): array
    {
        // Passed through to findFilteredPage() only for its own
        // searchMatchingIds() call (see that method's $pouchId doc comment)
        // — every other criterion here is still scoped by PouchFilter, not
        // this. AdminController's item browser calls the repository
        // directly instead, with its own explicit (possibly null,
        // meaning "all pouches") pouchId — it never goes through this method.
        return $this->itemRepository->findFilteredPage($filter, $offset, $limit, $excludedCategoryIds, $this->currentPouchResolver->resolve()->getId());
    }

    #[Override]
    public function getSearchSnippets(array $itemIds, string $query): array
    {
        return $this->itemRepository->findSnippets($itemIds, $query);
    }

    #[Override]
    public function delete(int $id): void
    {
        $item = $this->getById($id);
        $item->trash(new DateTimeImmutable());

        $this->itemRepository->save($item);
    }

    #[Override]
    public function deleteAsAdmin(int $id): void
    {
        $item = $this->getById($id);
        $item->trash(new DateTimeImmutable());

        $this->itemRepository->save($item);
    }

    #[Override]
    public function setFavorite(int $id, bool $favorite): Item
    {
        $item = $this->getById($id);
        $item->setFavorite($favorite);

        $this->itemRepository->save($item);

        return $item;
    }

    #[Override]
    public function replaceTags(int $id, array $tagNames): Item
    {
        $item = $this->getById($id);
        $item->setTags($this->tagService->resolveTags($tagNames));

        $this->itemRepository->save($item);

        return $item;
    }

    #[Override]
    public function overwriteFile(
        int $itemId,
        string $tmpPath,
        string $originalFilename,
        string $mimeType,
        int $size,
    ): Item {
        $item = $this->getById($itemId);
        if (ItemType::FILE !== $item->getType()) {
            throw new BadRequestException(message: 'item.not_a_file');
        }

        $this->fileValidator->assertValid($originalFilename, $mimeType, $size);
        $contentHash = $this->assertNotDuplicateOfAnotherItem($tmpPath, $item);

        // Upload first — a failed upload here leaves nothing behind to clean
        // up, unlike archiving the version first and only then uploading.
        $newStorageKey = $this->uploadToStorage($tmpPath, $originalFilename);

        // The item's *current* file becomes the archived version — captured
        // before any of its fields are overwritten below.
        $previousVersion = new ItemVersion(
            item: $item,
            version: $this->nextVersionNumber($item),
            originalFilename: $this->currentFileField($item, $item->getOriginalFilename()),
            mimeType: $this->currentFileField($item, $item->getMimeType()),
            size: $item->getSize() ?? throw new RuntimeException(sprintf('Item #%d is a FILE item with no size set', $item->getId())),
            storageKey: $this->currentFileField($item, $item->getStorageKey()),
            contentHash: $this->currentFileField($item, $item->getContentHash()),
        );

        try {
            // Archiving the previous version and pointing the item at the new
            // file must succeed or fail together — one flush() failing after
            // the other already committed would otherwise leave a version on
            // record for a swap that never completed.
            $this->connection->transactional(function () use ($item, $previousVersion, $originalFilename, $mimeType, $size, $newStorageKey, $contentHash): void {
                $this->itemVersionRepository->save($previousVersion);
                $item->setFileData($originalFilename, $mimeType, $size, $newStorageKey, $contentHash);
                $this->itemRepository->save($item);
            });
        } catch (Throwable $exception) {
            // Nothing in the DB ended up referencing the new upload — same
            // compensating-cleanup approach as saveNewItemOrConflict() above.
            try {
                $this->storageService->delete($newStorageKey);
            } catch (StorageException $cleanupException) {
                $this->logger->error(sprintf('Storage cleanup failed for orphaned key "%s": %s', $newStorageKey, $cleanupException->getMessage()));
            }

            throw $exception;
        }

        return $item;
    }

    #[Override]
    public function listVersions(int $itemId): array
    {
        $item = $this->getById($itemId);

        return $this->itemVersionRepository->findByItemOrderedByVersion($item);
    }

    #[Override]
    public function getVersion(int $itemId, int $version): ItemVersion
    {
        $item = $this->getById($itemId);
        $itemVersion = $this->itemVersionRepository->findOneByItemAndVersion($item, $version);

        if (null === $itemVersion) {
            throw new NotFoundException(message: 'item.version_not_found');
        }

        return $itemVersion;
    }

    #[Override]
    public function findExpiringBetween(DateTimeImmutable $from, DateTimeImmutable $until, ?int $pouchId = null): array
    {
        return $this->itemRepository->findExpiringBetween($from, $until, $pouchId);
    }

    // Deliberately unscoped: admin's cross-pouch bulk extend (AdminController),
    // not reachable from any regular user-facing endpoint.
    #[Override]
    public function extendExpiry(array $itemIds, ItemLifecycleOptions $options): array
    {
        $items = [];

        foreach ($itemIds as $itemId) {
            $item = $this->getById($itemId);
            $item->setLifecycle($options->keepForever, $this->resolveExpiresAt($options));
            $this->itemRepository->save($item);
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @throws BadRequestException
     * @throws ConflictException
     */
    private function assertNotDuplicate(string $tmpPath): string
    {
        $contentHash = hash_file('sha256', $tmpPath);
        if (false === $contentHash) {
            throw new BadRequestException(message: 'item.upload_unreadable');
        }

        $duplicate = $this->itemRepository->findByContentHash($contentHash);
        if (null !== $duplicate) {
            $this->throwDuplicateConflict($duplicate);
        }

        return $contentHash;
    }

    /**
     * Same duplicate check as assertNotDuplicate(), but for overwriting an
     * *existing* item: $item matching its own (about-to-be-archived) content
     * isn't a conflict — only some *other* active item having this content
     * already is.
     *
     * @throws BadRequestException
     * @throws ConflictException
     */
    private function assertNotDuplicateOfAnotherItem(string $tmpPath, Item $item): string
    {
        $contentHash = hash_file('sha256', $tmpPath);
        if (false === $contentHash) {
            throw new BadRequestException(message: 'item.upload_unreadable');
        }

        $duplicate = $this->itemRepository->findByContentHash($contentHash);
        if (null !== $duplicate && $duplicate->getId() !== $item->getId()) {
            $this->throwDuplicateConflict($duplicate);
        }

        return $contentHash;
    }

    private function nextVersionNumber(Item $item): int
    {
        return count($this->itemVersionRepository->findByItemOrderedByVersion($item)) + 1;
    }

    /**
     * Narrows one of Item's nullable file-metadata getters to non-null for a
     * FILE item, where createFile() guarantees it's always set — this should
     * never actually throw.
     */
    private function currentFileField(Item $item, ?string $value): string
    {
        return $value ?? throw new RuntimeException(sprintf('Item #%d is a FILE item with no file data set', $item->getId()));
    }

    /**
     * @throws ConflictException
     */
    private function throwDuplicateConflict(Item $duplicate): never
    {
        throw new ConflictException(
            message: $this->translator->trans('item.duplicate_content', [
                '%id%'   => $duplicate->getId(),
                '%name%' => $duplicate->getName(),
            ], domain: 'exceptions'),
            conflictingItemId: $duplicate->getId(),
        );
    }

    /**
     * Persists a freshly-uploaded item, turning the DB's partial unique index
     * on content_hash into the same ConflictException the pre-check above
     * throws — closing the race where two uploads of the same content both
     * pass that SELECT before either flushes.
     *
     * A failed flush leaves Doctrine's EntityManager closed for further ORM
     * operations, so the follow-up lookup goes through the raw DBAL
     * connection ($this->connection) instead of the (now unusable) repository
     * — and unlike everywhere else in this class, PouchFilter can't scope a
     * raw SQL query, so $item->getPouch() is passed explicitly here.
     *
     * The file itself is already sitting in storage by the time this runs
     * (uploadToStorage() happens before this is called) — a rejected save
     * must not leave it there orphaned with nothing in the DB pointing at it.
     *
     * @throws ConflictException
     */
    private function saveNewItemOrConflict(Item $item): void
    {
        try {
            $this->itemRepository->save($item);
        } catch (UniqueConstraintViolationException) {
            $storageKey = $item->getStorageKey();
            if (null !== $storageKey) {
                try {
                    $this->storageService->delete($storageKey);
                } catch (StorageException $exception) {
                    // Losing this race is already the unlikely case; failing
                    // to clean up after it besides is logged, not fatal —
                    // the 409 below still needs to reach the client either way.
                    $this->logger->error(sprintf('Storage cleanup failed for orphaned key "%s": %s', $storageKey, $exception->getMessage()));
                }
            }

            $row = $this->connection->fetchAssociative(
                'SELECT item_id, name FROM item WHERE content_hash = :hash AND pouch_id = :pouchId AND trashed_at IS NULL',
                ['hash' => $item->getContentHash(), 'pouchId' => $item->getPouch()->getId()],
            );

            $conflictingItemId = is_array($row) && is_numeric($row['item_id'] ?? null) ? (int) $row['item_id'] : null;

            throw new ConflictException(
                message: $this->translator->trans('item.duplicate_content', [
                    '%id%'   => $conflictingItemId ?? '?',
                    '%name%' => is_array($row) ? ($row['name'] ?? '?') : '?',
                ], domain: 'exceptions'),
                conflictingItemId: $conflictingItemId,
            );
        }
    }

    /**
     * @throws BadRequestException
     */
    private function uploadToStorage(string $tmpPath, string $originalFilename): string
    {
        $storageKey = $this->generateStorageKey($originalFilename);
        $stream = fopen($tmpPath, 'r');
        if (false === is_resource($stream)) {
            throw new BadRequestException(message: 'item.upload_unreadable');
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
                throw new BadRequestException(message: 'item.expires_at_future');
            }

            return $options->customExpiresAt;
        }

        if (null !== $options->ttlPreset) {
            return $now->add($options->ttlPreset->toDateInterval());
        }

        // Product doc: default TTL is 1 day when nothing else is specified.
        return $now->add(new DateInterval('P1D'));
    }

    private const int DERIVED_NOTE_NAME_MAX_LENGTH = 80;

    /**
     * No name given for a note → use its first line (trimmed of markdown
     * heading markers), same idea as how most note apps title an untitled note.
     */
    private function deriveNoteName(string $content): string
    {
        $firstLine = trim(strtok(trim($content), "\n") ?: '');
        $firstLine = ltrim($firstLine, "# \t");

        if ('' === $firstLine) {
            return 'Notatka';
        }

        return mb_strlen($firstLine) > self::DERIVED_NOTE_NAME_MAX_LENGTH
            ? mb_substr($firstLine, 0, self::DERIVED_NOTE_NAME_MAX_LENGTH) . '…'
            : $firstLine;
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
