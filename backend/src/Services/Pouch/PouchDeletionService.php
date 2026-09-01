<?php

declare(strict_types = 1);

namespace App\Services\Pouch;

use App\Entity\Item;
use App\Entity\ItemVersion;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\ConflictException\ConflictException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;
use App\ExceptionManagement\Exceptions\Command\StorageException;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Repository\ItemVersionRepository;
use App\Repository\PouchRepository;
use App\Repository\UserRepository;
use App\Services\Storage\StorageServiceInterface;
use Override;
use Psr\Log\LoggerInterface;

use function array_map;
use function count;
use function sprintf;

class PouchDeletionService implements PouchDeletionServiceInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly ItemRepository $itemRepository,
        private readonly ItemVersionRepository $itemVersionRepository,
        private readonly PouchRepository $pouchRepository,
        private readonly StorageServiceInterface $storageService,
        private readonly LoggerInterface $logger,
    ) {}

    #[Override]
    public function deleteOwnPouch(int $currentUserId): void
    {
        $user = $this->userRepository->find($currentUserId);
        if (null === $user) {
            throw new NotFoundException(message: 'user.not_found');
        }

        $pouch = $user->getPouch();
        $pouchId = $pouch->getId();

        if (count($this->userRepository->findAllInPouch($pouchId)) > 1) {
            throw new ConflictException(message: 'pouch.has_other_users');
        }

        // The one-user-in-this-pouch check above already guarantees $user is
        // alone here — so "the only admin, and someone else out there would
        // be left with none" only matters if the *system* has other accounts
        // at all. A truly solo admin (this pouch is the whole system) is
        // free to wipe everything, admin role included — there's nothing
        // left for anyone to be locked out of.
        if ($this->userRepository->countAdmins() <= 1 && $this->userRepository->countAll() > 1) {
            throw new BadRequestException(message: 'user.cannot_delete_last_admin');
        }

        // Items first, and always with their storage cleaned up before the
        // row goes — item.category_id/category.parent_id both cascade at the
        // DB level (ON DELETE CASCADE), so deleting categories *before*
        // items would silently wipe the item rows out from under this loop
        // with no chance left to ever delete their MinIO objects.
        foreach ($this->itemRepository->findAllInPouch($pouchId) as $item) {
            $this->deleteItemStorage($item);
            $this->itemRepository->remove($item);
        }

        // Root categories cascade to their children at the DB level.
        foreach ($this->categoryRepository->findRootCategories($pouchId) as $category) {
            $this->categoryRepository->remove($category);
        }

        $this->userRepository->remove($user);
        $this->pouchRepository->remove($pouch);
    }

    /**
     * Same "delete whichever storage objects exist, log and skip on
     * failure" approach as ItemGarbageCollector::purgeTrash() — a lingering
     * blob nobody points to any more is an acceptable trade-off next to
     * losing track of it entirely.
     */
    private function deleteItemStorage(Item $item): void
    {
        $versionStorageKeys = array_map(
            static fn (ItemVersion $version): string => $version->getStorageKey(),
            $this->itemVersionRepository->findByItemOrderedByVersion($item),
        );

        foreach ([$item->getStorageKey(), $item->getThumbnailStorageKey(), ...$versionStorageKeys] as $storageKey) {
            if (null === $storageKey) {
                continue;
            }

            try {
                $this->storageService->delete($storageKey);
            } catch (StorageException $exception) {
                $this->logger->error(sprintf('Item #%d: failed to delete storage object "%s" during pouch deletion: %s', $item->getId(), $storageKey, $exception->getMessage()));
            }
        }
    }
}
