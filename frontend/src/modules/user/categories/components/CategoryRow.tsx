import { useTranslation } from "react-i18next";
import { ApiEndpoints } from "../../../../lib/apiEndpoints";
import { triggerDownload } from "../../../../lib/triggerDownload";
import { useSetCategoryKeyMutation, useUnlockCategoryMutation } from "../../../../store/api/accessKeyApi";
import type { Category } from "../../../../store/api/categoryApi";
import { AccessKeyPanel } from "../../shared/AccessKeyPanel";

interface CategoryRowProps {
  category: Category;
  categoriesById: Map<number, Category>;
}

export function CategoryRow({ category, categoriesById }: CategoryRowProps) {
  const { t } = useTranslation();
  const [unlockCategory] = useUnlockCategoryMutation();
  const [setCategoryKey] = useSetCategoryKeyMutation();
  const parentName = null !== category.parentId ? (categoriesById.get(category.parentId)?.name ?? null) : null;

  const handleExport = () => {
    triggerDownload(ApiEndpoints.CATEGORY_EXPORT(category.id));
  };

  return (
    <li className="category-row">
      <div className="category-row-header">
        <span className="category-row-name">{category.name}</span>
        {null !== parentName && <span className="category-row-parent">{t("categories.parentOf", { parentName })}</span>}
        <button type="button" onClick={handleExport}>
          {t("categories.exportButton")}
        </button>
      </div>

      <AccessKeyPanel
        onUnlock={(key) => unlockCategory({ categoryId: category.id, key }).unwrap()}
        onSetKey={(key) => setCategoryKey({ categoryId: category.id, key }).unwrap()}
      />
    </li>
  );
}
