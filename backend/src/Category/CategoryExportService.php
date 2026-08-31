<?php

declare(strict_types = 1);

namespace App\Category;

use App\Entity\Category;
use App\Entity\Item;
use App\Enum\ItemType;
use App\Item\ItemListFilter;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Security\AccessKey\AccessKeyGuardInterface;
use App\Storage\StorageServiceInterface;
use Override;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
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
        return $this->buildZipFromRoots([$this->categoryService->getById($categoryId)], $request);
    }

    #[Override]
    public function buildFullBackupZip(Request $request): string
    {
        return $this->buildZipFromRoots($this->categoryRepository->findRootCategories(), $request);
    }

    /**
     * @param list<Category> $roots each becomes its own top-level folder
     */
    private function buildZipFromRoots(array $roots, Request $request): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'pouch-export-');
        if (false === $zipPath) {
            throw new RuntimeException('Could not create a temporary file for the export archive');
        }

        $zip = new ZipArchive();
        if (true !== $zip->open($zipPath, ZipArchive::OVERWRITE)) {
            throw new RuntimeException('Could not open the temporary export archive for writing');
        }

        // Every FILE/PHOTO item needs its own local copy (ZipArchive::addFile()
        // reads lazily from disk at close() time, not eagerly) — tracked here
        // so they can all be cleaned up together once the archive is sealed.
        $tmpItemFiles = [];

        try {
            foreach ($roots as $root) {
                $this->addCategoryToZip($zip, $root, '', $request, $tmpItemFiles);
            }

            $zip->close();
        } finally {
            foreach ($tmpItemFiles as $tmpItemFile) {
                @unlink($tmpItemFile);
            }
        }

        return $zipPath;
    }

    /**
     * @param list<string> $tmpItemFiles
     */
    private function addCategoryToZip(ZipArchive $zip, Category $category, string $basePath, Request $request, array &$tmpItemFiles): void
    {
        $dirPath = $basePath . $this->sanitize($category->getName()) . '/';
        // Ensures even an empty (sub)category still shows up in the archive
        // — "z zachowaniem struktury" means the tree, not just the leaves.
        $zip->addEmptyDir(rtrim($dirPath, '/'));

        $usedNames = [];

        foreach ($this->itemRepository->findFiltered(new ItemListFilter(categoryId: $category->getId())) as $item) {
            if (false === $this->accessKeyGuard->isItemUnlocked($item, $request)) {
                continue;
            }

            $this->addItemToZip($zip, $item, $dirPath, $usedNames, $tmpItemFiles);
        }

        foreach ($category->getChildren() as $child) {
            $this->addCategoryToZip($zip, $child, $dirPath, $request, $tmpItemFiles);
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

        $this->storageService->downloadToPath($storageKey, $localPath);
        $tmpItemFiles[] = $localPath;

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
