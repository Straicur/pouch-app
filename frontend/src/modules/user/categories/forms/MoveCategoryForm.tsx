import { useState } from "react";
import { useTranslation } from "react-i18next";
import { toastUtil } from "../../../../libs/toastUtil";
import type { Category } from "../../../../store/api/categoryApi";
import { useListCategoriesQuery, useMoveCategoryMutation } from "../../../../store/api/categoryApi";
import { Button } from "../../../../ui/catalyst/button";
import { DialogActions, DialogBody } from "../../../../ui/catalyst/dialog";
import { Field, Label } from "../../../../ui/catalyst/form/fieldset";
import { Select } from "../../../../ui/catalyst/form/select";

const ROOT_OPTION_VALUE = "root";

interface MoveCategoryFormProps {
  category: Category;
  onSuccess: () => void;
}

// Only root categories are offered as a target parent — CategoryService's
// own max-depth-2 limit means a subcategory can never itself hold another
// subcategory. Whether the move is actually allowed (e.g. $category has its
// own children, so it can't become a subcategory itself) is enforced
// server-side, not duplicated here — CategoriesPage's own tree already only
// ever renders two levels deep, so there's no client-side way to know that
// without re-deriving the same subtree logic.
export function MoveCategoryForm({ category, onSuccess }: MoveCategoryFormProps) {
  const { t } = useTranslation();
  const { data: categories } = useListCategoriesQuery();
  const [moveCategory, { isLoading }] = useMoveCategoryMutation();
  const [target, setTarget] = useState(null === category.parentId ? ROOT_OPTION_VALUE : String(category.parentId));

  const rootOptions = (categories ?? []).filter(
    (candidate) => null === candidate.parentId && candidate.id !== category.id,
  );

  const handleSubmit = async () => {
    try {
      await moveCategory({
        id: category.id,
        parentId: ROOT_OPTION_VALUE === target ? null : Number(target),
      }).unwrap();
      onSuccess();
    } catch {
      toastUtil.showToast(t("categories.moveError"), "error");
    }
  };

  return (
    <>
      <DialogBody>
        <Field>
          <Label>{t("categories.moveTargetLabel")}</Label>
          <Select value={target} onChange={(event) => setTarget(event.target.value)}>
            <option value={ROOT_OPTION_VALUE}>{t("categories.moveTargetRoot")}</option>
            {rootOptions.map((root) => (
              <option key={root.id} value={root.id}>
                {root.name}
              </option>
            ))}
          </Select>
        </Field>
      </DialogBody>
      <DialogActions>
        <Button onClick={() => void handleSubmit()} disabled={isLoading}>
          {isLoading ? t("categories.moving") : t("categories.moveSubmit")}
        </Button>
      </DialogActions>
    </>
  );
}
