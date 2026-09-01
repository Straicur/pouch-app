<?php

declare(strict_types = 1);

namespace App\Services\Category;

use App\Entity\Category;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\ConflictException\ConflictException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Services\Pouch\CurrentPouchResolverInterface;
use Override;

class CategoryService implements CategoryServiceInterface
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly ItemRepository $itemRepository,
        private readonly CurrentPouchResolverInterface $currentPouchResolver,
    ) {}

    #[Override]
    public function list(): array
    {
        return $this->categoryRepository->findAllOrderedByName();
    }

    #[Override]
    public function getById(int $id): Category
    {
        // findOneBy(), not find(): find() checks Doctrine's identity map
        // *before* running any SQL, bypassing PouchFilter entirely for an
        // id already loaded elsewhere in this request — findOneBy() always
        // executes the (filtered) query. Another pouch's category is
        // filtered out at the SQL level and so looks exactly like a missing
        // one — never 403.
        $category = $this->categoryRepository->findOneBy(['id' => $id]);
        if (null === $category) {
            throw new NotFoundException(message: 'category.not_found');
        }

        return $category;
    }

    #[Override]
    public function create(string $name, ?int $parentId): Category
    {
        $parent = $this->resolveParent($parentId);
        $this->assertMaxDepth($parent);

        $category = new Category($name, $this->currentPouchResolver->resolve(), $parent);
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
        $this->assertMaxDepth($parent);

        // A category with its own children can't become a subcategory
        // itself — that would put its children at depth 3, past the
        // "kategoria główna + jedna podkategoria" limit assertMaxDepth()
        // enforces for $parent alone.
        if (null !== $parent && [] !== $category->getChildren()->toArray()) {
            throw new BadRequestException(message: 'category.max_depth');
        }

        $category->setParent($parent);
        $this->categoryRepository->save($category);

        return $category;
    }

    #[Override]
    public function delete(int $id): void
    {
        $category = $this->getById($id);

        // Blocks the delete outright while it or any descendant still holds
        // ANY item — including one already trashed but not yet purged: just
        // removing the row and letting the DB cascade (ON DELETE CASCADE)
        // would skip ItemGarbageCollector::purgeTrash(), the only place that
        // deletes storage objects from S3/MinIO, orphaning them in the
        // bucket forever. Trash/move/wait-for-GC first, then delete the
        // truly-empty category.
        if ($this->itemRepository->existsInCategories($this->collectSubtreeIds($category))) {
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

    // Kategorie mogą mieć co najwyżej jeden poziom zagnieżdżenia (kategoria
    // główna + jej bezpośrednie podkategorie) — podkategoria nie może mieć
    // własnej podkategorii. $parent tu to już *rozwiązany* nowy
    // rodzic (po resolveParent()), więc "$parent ma rodzica" znaczy "$parent
    // sam jest podkategorią".
    private function assertMaxDepth(?Category $parent): void
    {
        if (null !== $parent && null !== $parent->getParent()) {
            throw new BadRequestException(message: 'category.max_depth');
        }
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
