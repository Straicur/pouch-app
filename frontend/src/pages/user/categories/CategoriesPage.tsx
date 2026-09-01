import { useState } from "react";
import { useTranslation } from "react-i18next";
import { LoadingIndicator } from "../../../modules/shared/view/LoadingIndicator";
import { CategoryForm } from "../../../modules/user/categories/forms/CategoryForm";
import { CategoryRow } from "../../../modules/user/categories/view/CategoryRow";
import { useListCategoriesQuery } from "../../../store/api/categoryApi";
import { Button } from "../../../ui/catalyst/button";
import { Dialog, DialogTitle } from "../../../ui/catalyst/dialog";
import { Heading } from "../../../ui/catalyst/heading";

// Kategorie w drzewie (kategoria główna + jej bezpośrednie podkategorie —
// CategoryService ogranicza głębokość do tego jednego poziomu), zamiast
// płaskiej listy z dopiskiem "(podkategoria: X)".
export function CategoriesPage() {
  const { t } = useTranslation();
  const { data: categories, isLoading, error } = useListCategoriesQuery();
  const [isAddRootOpen, setIsAddRootOpen] = useState(false);

  const roots = (categories ?? []).filter((category) => null === category.parentId);
  const childrenByParentId = new Map<number, typeof roots>();
  for (const category of categories ?? []) {
    if (null === category.parentId) {
      continue;
    }

    const siblings = childrenByParentId.get(category.parentId) ?? [];
    siblings.push(category);
    childrenByParentId.set(category.parentId, siblings);
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between gap-2">
        <Heading>{t("nav.categories")}</Heading>
        <Button onClick={() => setIsAddRootOpen(true)}>{t("categories.addCategoryButton")}</Button>
      </div>

      {isLoading && <LoadingIndicator />}
      {undefined !== error && <p className="text-red-600 dark:text-red-400">{t("categories.fetchError")}</p>}
      {undefined !== categories && 0 === categories.length && <p>{t("categories.empty")}</p>}

      {undefined !== categories && categories.length > 0 && (
        <ul className="flex flex-col gap-3">
          {roots.map((root) => (
            <CategoryRow key={root.id} category={root} subcategories={childrenByParentId.get(root.id) ?? []} />
          ))}
        </ul>
      )}

      <Dialog open={isAddRootOpen} onClose={setIsAddRootOpen}>
        <DialogTitle>{t("categories.addCategoryTitle")}</DialogTitle>
        {/* Keyed on open state so react-hook-form's internal values reset on
            every re-open, instead of carrying over from a previous submit —
            the dialog itself stays mounted for its close transition. */}
        <CategoryForm key={String(isAddRootOpen)} parentId={null} onSuccess={() => setIsAddRootOpen(false)} />
      </Dialog>
    </div>
  );
}
