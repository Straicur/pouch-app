import { useTranslation } from "react-i18next";
import { ApiEndpoints } from "../../../../libs/apiEndpoints";
import { toastUtil } from "../../../../libs/toastUtil";
import {
  useGetCategoryExportTokenMutation,
  useSetCategoryKeyMutation,
  useUnlockCategoryMutation,
} from "../../../../store/api/accessKeyApi";
import type { Category } from "../../../../store/api/categoryApi";
import { triggerDownload } from "../../../../utils/triggerDownload";
import { AccessKeyPanel } from "../../shared/AccessKeyPanel";

interface CategoryRowProps {
  category: Category;
  categoriesById: Map<number, Category>;
}

export function CategoryRow({ category, categoriesById }: CategoryRowProps) {
  const { t } = useTranslation();
  const [unlockCategory] = useUnlockCategoryMutation();
  const [setCategoryKey] = useSetCategoryKeyMutation();
  const [getExportToken, { isLoading: isPreparingExport }] = useGetCategoryExportTokenMutation();
  const parentName = null !== category.parentId ? (categoriesById.get(category.parentId)?.name ?? null) : null;

  // Post-review fix: a plain navigation can't set the X-Pouch-Access-Grants
  // header, so this first exchanges whatever grants the session currently
  // holds for a short-lived, opaque token (a normal AJAX POST, where the
  // header works fine) — see triggerDownload.ts's own doc comment.
  const handleExport = async () => {
    try {
      const { token } = await getExportToken(category.id).unwrap();
      triggerDownload(`${ApiEndpoints.CATEGORY_EXPORT(category.id)}?token=${encodeURIComponent(token)}`);
    } catch {
      toastUtil.showToast(t("categories.exportError"), "error");
    }
  };

  return (
    <li className="category-row">
      <div className="category-row-header">
        <span className="category-row-name">{category.name}</span>
        {null !== parentName && <span className="category-row-parent">{t("categories.parentOf", { parentName })}</span>}
        <button type="button" onClick={handleExport} disabled={isPreparingExport}>
          {isPreparingExport ? t("categories.exportPreparing") : t("categories.exportButton")}
        </button>
      </div>

      <AccessKeyPanel
        onUnlock={(key) => unlockCategory({ categoryId: category.id, key }).unwrap()}
        onSetKey={(key) => setCategoryKey({ categoryId: category.id, key }).unwrap()}
      />
    </li>
  );
}
