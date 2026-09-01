<?php

declare(strict_types = 1);

namespace App\Services\Category;

use App\Entity\Category;
use App\Entity\Item;
use App\Enum\ItemType;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Security\AccessKey\AccessKeyGuardInterface;
use App\Services\Item\ValueObject\ItemListFilter;
use App\Services\Storage\StorageServiceInterface;
use Override;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Throwable;
use ZipArchive;

use function array_filter;
use function implode;
use function preg_replace;
use function strrpos;
use function substr;
use function sys_get_temp_dir;
use function tempnam;
use function trim;
use function unlink;

class CategoryExportService implements CategoryExportServiceInterface
{
    public function __construct(
        private readonly CategoryServiceInterface $categoryService,
        private readonly CategoryRepository $categoryRepository,
        private readonly ItemRepository $itemRepository,
        private readonly StorageServiceInterface $storageService,
        private readonly AccessKeyGuardInterface $accessKeyGuard,
    ) {}

    #[Override]
    public function buildZip(int $categoryId, Request $request): string
    {
        return $this->buildZipFromRoots([$this->categoryService->getById($categoryId)], $request, bypassLocks: false);
    }

    #[Override]
    public function buildFullBackupZip(Request $request, ?int $pouchId = null): string
    {
        return $this->buildZipFromRoots($this->categoryRepository->findRootCategories($pouchId), $request, bypassLocks: true);
    }

    /**
     * @param list<Category> $roots each becomes its own top-level folder
     */
    private function buildZipFromRoots(array $roots, Request $request, bool $bypassLocks): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'pouch-export-');
        if (false === $zipPath) {
            throw new RuntimeException('Could not create a temporary file for the export archive');
        }

        // Post-review fix: everything from here on used to be able to throw
        // (open() failing, a storage download inside addCategoryToZip()
        // failing, close() failing) without $zipPath — already sitting on
        // disk by this point — ever being cleaned up; only the caller's own
        // eventual (successful-path-only) unlink() covered it. A repeatedly
        // failing export/backup could quietly fill up the temp directory.
        // The controllers now guarantee cleanup of whatever this method
        // *returns*, but they never get a path to clean up if this throws
        // instead — so the guarantee has to live here too.
        try {
            $zip = new ZipArchive();
            if (true !== $zip->open($zipPath, ZipArchive::OVERWRITE)) {
                throw new RuntimeException('Could not open the temporary export archive for writing');
            }

            // Every FILE/PHOTO item needs its own local copy (ZipArchive::
            // addFile() reads lazily from disk at close() time, not eagerly)
            // — tracked here so they can all be cleaned up together once the
            // archive is sealed.
            $tmpItemFiles = [];

            try {
                $usedRootNames = [];
                foreach ($roots as $root) {
                    $this->addCategoryToZip($zip, $root, '', $request, $tmpItemFiles, $bypassLocks, $usedRootNames);
                }

                if (false === $zip->close()) {
                    throw new RuntimeException('Could not finalize the export archive');
                }
            } finally {
                foreach ($tmpItemFiles as $tmpItemFile) {
                    @unlink($tmpItemFile);
                }
            }

            return $zipPath;
        } catch (Throwable $exception) {
            @unlink($zipPath);

            throw $exception;
        }
    }

    /**
     * @param list<string>        $tmpItemFiles
     * @param array<string, true> $usedSiblingNames names already taken among $category's siblings at this level
     *                                              (i.e. under the same parent, or among the roots) — nothing
     *                                              stops two sibling categories sharing a name (CategoryService
     *                                              doesn't enforce uniqueness), so without this two "Dokumenty"
     *                                              categories would silently merge into one ZIP directory
     */
    private function addCategoryToZip(ZipArchive $zip, Category $category, string $basePath, Request $request, array &$tmpItemFiles, bool $bypassLocks, array &$usedSiblingNames): void
    {
        $dirName = $this->uniqueEntryName('', $category->getName(), $usedSiblingNames);
        $dirPath = $basePath . $dirName . '/';
        // Ensures even an empty (sub)category still shows up in the archive
        // — "z zachowaniem struktury" means the tree, not just the leaves.
        $zip->addEmptyDir(rtrim($dirPath, '/'));

        $usedNames = [];

        foreach ($this->itemRepository->findFiltered(new ItemListFilter(categoryIds: [$category->getId()])) as $item) {
            if (false === $bypassLocks && false === $this->accessKeyGuard->isItemUnlocked($item, $request)) {
                continue;
            }

            $this->addItemToZip($zip, $item, $dirPath, $usedNames, $tmpItemFiles);
        }

        $usedChildNames = [];
        foreach ($category->getChildren() as $child) {
            $this->addCategoryToZip($zip, $child, $dirPath, $request, $tmpItemFiles, $bypassLocks, $usedChildNames);
        }
    }

    /**
     * @param array<string, true> $usedNames
     * @param list<string>        $tmpItemFiles
     */
    private function addItemToZip(ZipArchive $zip, Item $item, string $dirPath, array &$usedNames, array &$tmpItemFiles): void
    {
        match ($item->getType()) {
            ItemType::FILE, ItemType::PHOTO => $this->addPrimaryFileToZip($zip, $item, $dirPath, $usedNames, $tmpItemFiles),
            ItemType::NOTE => $zip->addFromString(
                $this->uniqueEntryName($dirPath, $item->getName() . '.md', $usedNames),
                $item->getNoteContent() ?? '',
            ),
            ItemType::URL => $zip->addFromString(
                $this->uniqueEntryName($dirPath, $item->getName() . '.txt', $usedNames),
                $this->urlItemContent($item),
            ),
        };
    }

    /**
     * @param array<string, true> $usedNames
     * @param list<string>        $tmpItemFiles
     */
    private function addPrimaryFileToZip(ZipArchive $zip, Item $item, string $dirPath, array &$usedNames, array &$tmpItemFiles): void
    {
        $storageKey = $item->getStorageKey();
        if (null === $storageKey) {
            // Shouldn't happen for a FILE/PHOTO item (createFile()/createPhoto()
            // always set it), but a still-PENDING photo has nothing to add yet.
            return;
        }

        $localPath = tempnam(sys_get_temp_dir(), 'pouch-export-item-');
        if (false === $localPath) {
            throw new RuntimeException('Could not create a temporary file for item #' . $item->getId());
        }

        // Tracked before the download, not after — downloadToPath() can
        // throw partway through (see testFailedExportDoesNotLeaveATempFileBehind),
        // and $localPath already exists on disk by this point regardless.
        $tmpItemFiles[] = $localPath;
        $this->storageService->downloadToPath($storageKey, $localPath);

        $entryName = $this->uniqueEntryName($dirPath, $item->getOriginalFilename() ?? $item->getName(), $usedNames);
        $zip->addFile($localPath, $entryName);
    }

    private function urlItemContent(Item $item): string
    {
        $lines = array_filter(
            [$item->getUrl(), $item->getPageTitle(), $item->getPageDescription()],
            static fn (?string $line): bool => null !== $line && '' !== $line,
        );

        return implode("\n\n", $lines);
    }

    /**
     * @param array<string, true> $usedNames
     */
    private function uniqueEntryName(string $dirPath, string $desiredName, array &$usedNames): string
    {
        $sanitized = $this->sanitize($desiredName);
        $candidate = $sanitized;

        for ($suffix = 2; isset($usedNames[$candidate]); ++$suffix) {
            $candidate = $this->withSuffix($sanitized, $suffix);
        }

        $usedNames[$candidate] = true;

        return $dirPath . $candidate;
    }

    private function withSuffix(string $name, int $suffix): string
    {
        $dotPosition = strrpos($name, '.');
        if (false === $dotPosition) {
            return $name . ' (' . $suffix . ')';
        }

        return substr($name, 0, $dotPosition) . ' (' . $suffix . ')' . substr($name, $dotPosition);
    }

    /**
     * Zip entries are plain strings, not filesystem paths — strip whatever
     * would otherwise be read as a directory separator (or a control
     * character no archive tool would thank us for) out of a category/item
     * name before it becomes part of one.
     */
    private function sanitize(string $name): string
    {
        $sanitized = trim((string) preg_replace('#[/\\\\\x00-\x1F]+#', '_', $name));

        return '' === $sanitized ? 'untitled' : $sanitized;
    }
}
