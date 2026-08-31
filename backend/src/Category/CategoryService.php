<?php

declare(strict_types = 1);

namespace App\Category;

use App\Entity\Category;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\ConflictException\ConflictException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use Override;

class CategoryService implements CategoryServiceInterface
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly ItemRepository $itemRepository,
    ) {}

    #[Override]
    public function list(): array
    {
        return $this->categoryRepository->findAllOrderedByName();
    }

    #[Override]
    public function getById(int $id): Category
    {
        $category = $this->categoryRepository->find($id);

        if (null === $category) {
            throw new NotFoundException(message: 'category.not_found');
        }

        return $category;
    }

    #[Override]
    public function create(string $name, ?int $parentId): Category
    {
        $parent = $this->resolveParent($parentId);

        $category = new Category($name, $parent);
        $this->categoryRepository->save($category);

        return $category;
    }

    #[Override]
    public function rename(int $id, string $name): Category
    {
        $category = $this->getById($id);
        $category->setName($name);

        $this->categoryRepository->save($category);

        return $category;
    }

    #[Override]
    public function move(int $id, ?int $parentId): Category
    {
        $category = $this->getById($id);
        $parent = $this->resolveParent($parentId);

        $this->assertNoCycle($category, $parent);

        $category->setParent($parent);
        $this->categoryRepository->save($category);

        return $category;
    }

    #[Override]
    public function delete(int $id): void
    {
        $category = $this->getById($id);

        // Post-review fix: used to just remove the row and let the DB cascade
        // to descendants (ON DELETE CASCADE) — ItemGarbageCollector::
        // purgeTrash() (the only place that deletes storage objects from
        // S3/MinIO) never got a chance to see items wiped out that way, so
        // their files were orphaned in the bucket forever. Option (a) from
        // the roadmap: block the delete outright while it or any descendant
        // still holds an active item — trash (or move) them out first, so
        // GC gets its normal chance to purge their storage, then delete the
        // now-empty category.
        if ($this->itemRepository->existsActiveInCategories($this->collectSubtreeIds($category))) {
            throw new ConflictException(message: 'category.not_empty');
        }

        // Descendants are removed by the DB (parent join column is ON DELETE CASCADE).
        $this->categoryRepository->remove($category);
    }

    /**
     * @return list<int>
     */
    private function collectSubtreeIds(Category $category): array
    {
        $ids = [$category->getId()];

        foreach ($category->getChildren() as $child) {
            array_push($ids, ...$this->collectSubtreeIds($child));
        }

        return $ids;
    }

    private function resolveParent(?int $parentId): ?Category
    {
        if (null === $parentId) {
            return null;
        }

        return $this->getById($parentId);
    }

    private function assertNoCycle(Category $category, ?Category $newParent): void
    {
        $ancestor = $newParent;

        while (null !== $ancestor) {
            if ($ancestor->getId() === $category->getId()) {
                throw new BadRequestException(message: 'category.move_cycle');
            }

            $ancestor = $ancestor->getParent();
        }
    }
}
