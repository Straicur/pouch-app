import * as Headless from "@headlessui/react";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import { ApiEndpoints } from "../../../../libs/apiEndpoints";
import { toastUtil } from "../../../../libs/toastUtil";
import {
  useGetCategoryExportTokenMutation,
  useSetCategoryKeyMutation,
  useUnlockCategoryMutation,
} from "../../../../store/api/accessKeyApi";
import type { Category } from "../../../../store/api/categoryApi";
import { Button } from "../../../../ui/catalyst/button";
import { Dialog, DialogTitle } from "../../../../ui/catalyst/dialog";
import { triggerDownload } from "../../../../utils/triggerDownload";
import { AccessKeyPanel } from "../../shared/AccessKeyPanel";
import { CategoryForm } from "../forms/CategoryForm";
import { MoveCategoryForm } from "../forms/MoveCategoryForm";
import { RenameCategoryForm } from "../forms/RenameCategoryForm";

interface CategoryRowProps {
  category: Category;
  subcategories?: Category[];
}

// Klucz dostępu zwinięty domyślnie (Headless.Disclosure) — większość
// kategorii nie ma ustawionego klucza w ogóle, więc pokazywanie go zawsze
// dominowałoby kartę kategorii bez potrzeby. "Dodaj podkategorię" tylko na
// kategorii głównej (subcategories !== undefined) — limit głębokości 2
// (CategoryService) wymuszony też wizualnie, nie tylko na backendzie.
export function CategoryRow({ category, subcategories }: CategoryRowProps) {
  const { t } = useTranslation();
  const [unlockCategory] = useUnlockCategoryMutation();
  const [setCategoryKey] = useSetCategoryKeyMutation();
  const [getExportToken, { isLoading: isPreparingExport }] = useGetCategoryExportTokenMutation();
  const [isAddSubcategoryOpen, setIsAddSubcategoryOpen] = useState(false);
  const [isRenameOpen, setIsRenameOpen] = useState(false);
  const [isMoveOpen, setIsMoveOpen] = useState(false);
  const isRoot = undefined !== subcategories;

  // A plain navigation can't set the X-Pouch-Access-Grants header, so this
  // first exchanges whatever grants the session currently holds for a
  // short-lived, opaque token (a normal AJAX POST, where the header works
  // fine) — see triggerDownload.ts's own doc comment.
  const handleExport = async () => {
    try {
      const { token } = await getExportToken(category.id).unwrap();
      triggerDownload(`${ApiEndpoints.CATEGORY_EXPORT(category.id)}?token=${encodeURIComponent(token)}`);
    } catch {
      toastUtil.showToast(t("categories.exportError"), "error");
    }
  };

  return (
    <li className="rounded-lg ring-1 ring-zinc-950/10 dark:ring-white/10">
      <div className="flex flex-col gap-3 p-4">
        <div className="flex items-center justify-between gap-2">
          <span className="font-medium text-zinc-950 dark:text-white">{category.name}</span>
          <div className="flex items-center gap-2">
            {isRoot && (
              <Button variant="outline" size="small" onClick={() => setIsAddSubcategoryOpen(true)}>
                {t("categories.addSubcategoryButton")}
              </Button>
            )}
            <Button variant="outline" size="small" onClick={handleExport} disabled={isPreparingExport}>
              {isPreparingExport ? t("categories.exportPreparing") : t("categories.exportButton")}
            </Button>
            <Button variant="outline" size="small" onClick={() => setIsRenameOpen(true)}>
              {t("categories.renameButton")}
            </Button>
            <Button variant="outline" size="small" onClick={() => setIsMoveOpen(true)}>
              {t("categories.moveButton")}
            </Button>
          </div>
        </div>

        <Headless.Disclosure>
          <Headless.DisclosureButton className="w-fit text-left text-sm text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
            {t("accessKey.toggleLabel")}
          </Headless.DisclosureButton>
          <Headless.DisclosurePanel className="mt-3">
            <AccessKeyPanel
              hasKey={category.hasAccessKey}
              onUnlock={(key) => unlockCategory({ categoryId: category.id, key }).unwrap()}
              onSetKey={(key) => setCategoryKey({ categoryId: category.id, key }).unwrap()}
            />
          </Headless.DisclosurePanel>
        </Headless.Disclosure>
      </div>

      {undefined !== subcategories && subcategories.length > 0 && (
        <ul className="flex flex-col gap-2 border-t border-zinc-950/10 p-4 pl-8 dark:border-white/10">
          {subcategories.map((child) => (
            <CategoryRow key={child.id} category={child} />
          ))}
        </ul>
      )}

      <Dialog open={isAddSubcategoryOpen} onClose={setIsAddSubcategoryOpen}>
        <DialogTitle>{t("categories.addSubcategoryTitle", { parentName: category.name })}</DialogTitle>
        {/* Keyed on open state, see CategoriesPage's own root-category dialog. */}
        <CategoryForm
          key={String(isAddSubcategoryOpen)}
          parentId={category.id}
          onSuccess={() => setIsAddSubcategoryOpen(false)}
        />
      </Dialog>

      <Dialog open={isRenameOpen} onClose={setIsRenameOpen}>
        <DialogTitle>{t("categories.renameTitle", { name: category.name })}</DialogTitle>
        <RenameCategoryForm key={String(isRenameOpen)} category={category} onSuccess={() => setIsRenameOpen(false)} />
      </Dialog>

      <Dialog open={isMoveOpen} onClose={setIsMoveOpen}>
        <DialogTitle>{t("categories.moveTitle", { name: category.name })}</DialogTitle>
        <MoveCategoryForm key={String(isMoveOpen)} category={category} onSuccess={() => setIsMoveOpen(false)} />
      </Dialog>
    </li>
  );
}
