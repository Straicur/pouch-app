import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { z } from "zod";
import { ExceptionUuid, getApiErrorBody, isApiError } from "../../../../libs/apiError";
import i18n from "../../../../libs/i18n";
import { toastUtil } from "../../../../libs/toastUtil";
import { useUnlockItemMutation } from "../../../../store/api/accessKeyApi";

// Defined at module scope, like LoginPage — messages go through the imported
// i18n instance's t() directly; see locales/pl.ts's header.
const unlockItemSchema = z.object({
  itemId: z.coerce.number(i18n.t("validation.itemIdRequired")).int().positive(i18n.t("validation.itemIdInvalid")),
  key: z.string().min(1, i18n.t("validation.keyRequired")),
});

// z.coerce.number()'s input type (unknown, before coercion) differs from its
// output type (number, after) — useForm needs both, see NoteForm's own note.
type UnlockItemFormInput = z.input<typeof unlockItemSchema>;
type UnlockItemFormValues = z.output<typeof unlockItemSchema>;

// Part 7 — a locked item (or one in a locked category) is silently left out
// of GET /api/items (see ItemController::list()'s own docblock), so there's
// no "click the locked item you're already looking at" flow for it the way
// CategoriesPage's AccessKeyPanel has — you have to know its id already
// (e.g. it was shared with you), enter its key here, and it reappears in the
// list below once the grant is stored.
export function UnlockItemForm() {
  const { t } = useTranslation();
  const [unlockItem, { isLoading }] = useUnlockItemMutation();
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<UnlockItemFormInput, unknown, UnlockItemFormValues>({ resolver: zodResolver(unlockItemSchema) });

  const onSubmit = async (values: UnlockItemFormValues) => {
    try {
      await unlockItem({ itemId: values.itemId, key: values.key }).unwrap();
      reset({ itemId: values.itemId, key: "" });
      toastUtil.showToast(t("accessKey.unlockSuccess"), "success");
    } catch (error) {
      const message = isApiError(error, ExceptionUuid.UNAUTHORIZED)
        ? t("accessKey.wrongKey")
        : (getApiErrorBody(error)?.detail ?? t("accessKey.unlockError"));
      toastUtil.showToast(message, "error");
    }
  };

  return (
    <form className="unlock-item-form" onSubmit={handleSubmit(onSubmit)} noValidate>
      <h2>{t("items.unlockTitle")}</h2>
      <input
        type="number"
        min={1}
        placeholder={t("items.unlockIdPlaceholder")}
        aria-label={t("items.unlockIdPlaceholder")}
        {...register("itemId", { valueAsNumber: true })}
      />
      {undefined !== errors.itemId && <p className="field-error">{errors.itemId.message}</p>}

      <input
        type="password"
        placeholder={t("accessKey.unlockPlaceholder")}
        aria-label={t("accessKey.unlockPlaceholder")}
        {...register("key")}
      />
      {undefined !== errors.key && <p className="field-error">{errors.key.message}</p>}

      <button type="submit" disabled={isLoading}>
        {isLoading ? t("accessKey.unlocking") : t("accessKey.unlockButton")}
      </button>
    </form>
  );
}
