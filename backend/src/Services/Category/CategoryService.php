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
        return $this->categoryRepository->findAllForPouchOrderedByName($this->currentPouchResolver->resolve());
    }

    #[Override]
    public function getById(int $id): Category
    {
        $category = $this->categoryRepository->find($id);
        $currentPouchId = $this->currentPouchResolver->resolve()->getId();

        // Another pouch's category looks exactly like a missing one — never 403.
        if (null === $category || $category->getPouch()->getId() !== $currentPouchId) {
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

        // Post-review fix: used to just remove the row and let the DB cascade
        // to descendants (ON DELETE CASCADE) — ItemGarbageCollector::
        // purgeTrash() (the only place that deletes storage objects from
        // S3/MinIO) never got a chance to see items wiped out that way, so
        // their files were orphaned in the bucket forever. Option (a) from
        // the roadmap: block the delete outright while it or any descendant
        // still holds ANY item — including one already trashed but not yet
        // purged (an earlier version of this check only looked at active
        // items, which still let a category full of trashed-but-unpurged
        // items through). Trash/move/wait-for-GC first, then delete the
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

    // Część 13: kategorie mogą mieć co najwyżej jeden poziom zagnieżdżenia
    // (kategoria główna + jej bezpośrednie podkategorie) — podkategoria nie
    // może mieć własnej podkategorii. $parent tu to już *rozwiązany* nowy
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
