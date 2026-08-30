<?php

declare(strict_types = 1);

namespace App\Category;

use App\Entity\Category;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;
use App\Repository\CategoryRepository;
use Override;

class CategoryService implements CategoryServiceInterface
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
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
            throw new NotFoundException(message: 'Category not found');
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
        // Descendants are removed by the DB (parent join column is ON DELETE CASCADE).
        $this->categoryRepository->remove($category);
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
                throw new BadRequestException(message: 'Cannot move a category into itself or one of its own descendants');
            }

            $ancestor = $ancestor->getParent();
        }
    }
}
