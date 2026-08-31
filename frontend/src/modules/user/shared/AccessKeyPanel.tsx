import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { z } from "zod";
import { ExceptionUuid, getApiErrorBody, isApiError } from "../../../lib/apiError";
import { toastUtil } from "../../../lib/toastUtil";

interface AccessKeyPanelProps {
  onUnlock: (key: string) => Promise<unknown>;
  onSetKey: (key: string | null) => Promise<unknown>;
}

// Defined at module scope, like LoginPage — see locales/pl.ts's header.
const unlockKeySchema = z.object({ key: z.string().min(1, "Podaj klucz") });
const setKeySchema = z.object({ key: z.string().min(1, "Podaj nowy klucz") });

type UnlockKeyValues = z.infer<typeof unlockKeySchema>;
type SetKeyValues = z.infer<typeof setKeySchema>;

// Part 7 — shared by categories (CategoriesPage) and items (ItemCard), since
// both have exactly the same two actions: submit a key to unlock, or
// set/change/remove the resource's own key. Always offered, never gated
// behind detecting "is this locked" client-side — nothing in
// CategoryResponseDTO/ItemResponseDTO says whether a key is set (see
// docs/codestyle/FRONTEND.md's warning against branching on `detail` text
// instead of `context.uuid`), so the structured response to *attempting* the
// action is the only reliable signal there is.
export function AccessKeyPanel({ onUnlock, onSetKey }: AccessKeyPanelProps) {
  const { t } = useTranslation();

  const unlockForm = useForm<UnlockKeyValues>({ resolver: zodResolver(unlockKeySchema) });
  const setKeyForm = useForm<SetKeyValues>({ resolver: zodResolver(setKeySchema) });

  const handleUnlock = async (values: UnlockKeyValues) => {
    try {
      await onUnlock(values.key);
      unlockForm.reset({ key: "" });
      toastUtil.showToast(t("accessKey.unlockSuccess"), "success");
    } catch (error) {
      toastUtil.showToast(unlockErrorMessage(error, t), "error");
    }
  };

  const handleSetKey = async (values: SetKeyValues) => {
    try {
      await onSetKey(values.key);
      setKeyForm.reset({ key: "" });
      toastUtil.showToast(t("accessKey.setKeySuccess"), "success");
    } catch (error) {
      toastUtil.showToast(setKeyErrorMessage(error, t), "error");
    }
  };

  const handleRemoveKey = async () => {
    try {
      await onSetKey(null);
      toastUtil.showToast(t("accessKey.removeKeySuccess"), "success");
    } catch (error) {
      toastUtil.showToast(setKeyErrorMessage(error, t), "error");
    }
  };

  return (
    <div className="access-key-panel">
      <p className="access-key-panel-title">{t("accessKey.sectionTitle")}</p>

      <form className="access-key-unlock-form" onSubmit={unlockForm.handleSubmit(handleUnlock)} noValidate>
        <input
          type="password"
          placeholder={t("accessKey.unlockPlaceholder")}
          aria-label={t("accessKey.unlockPlaceholder")}
          {...unlockForm.register("key")}
        />
        <button type="submit" disabled={unlockForm.formState.isSubmitting}>
          {unlockForm.formState.isSubmitting ? t("accessKey.unlocking") : t("accessKey.unlockButton")}
        </button>
        {undefined !== unlockForm.formState.errors.key && (
          <p className="field-error">{unlockForm.formState.errors.key.message}</p>
        )}
      </form>

      <form className="access-key-set-form" onSubmit={setKeyForm.handleSubmit(handleSetKey)} noValidate>
        <input
          type="password"
          placeholder={t("accessKey.setKeyPlaceholder")}
          aria-label={t("accessKey.setKeyPlaceholder")}
          {...setKeyForm.register("key")}
        />
        <button type="submit" disabled={setKeyForm.formState.isSubmitting}>
          {setKeyForm.formState.isSubmitting ? t("accessKey.settingKey") : t("accessKey.setKeyButton")}
        </button>
        <button type="button" onClick={handleRemoveKey} disabled={setKeyForm.formState.isSubmitting}>
          {t("accessKey.removeKeyButton")}
        </button>
        {undefined !== setKeyForm.formState.errors.key && (
          <p className="field-error">{setKeyForm.formState.errors.key.message}</p>
        )}
      </form>
    </div>
  );
}

function unlockErrorMessage(error: unknown, t: (key: string, options?: Record<string, unknown>) => string): string {
  if (isApiError(error, ExceptionUuid.UNAUTHORIZED)) {
    return t("accessKey.wrongKey");
  }

  if (isApiError(error, ExceptionUuid.BAD_REQUEST)) {
    return t("accessKey.notProtected");
  }

  if (isApiError(error, ExceptionUuid.TOO_MANY_REQUESTS)) {
    const retryAfter = getApiErrorBody(error)?.context.retryAfter;
    return t("accessKey.rateLimited", { seconds: retryAfter ?? "?" });
  }

  return t("accessKey.unlockError");
}

function setKeyErrorMessage(error: unknown, t: (key: string, options?: Record<string, unknown>) => string): string {
  return isApiError(error, ExceptionUuid.FORBIDDEN) ? t("accessKey.setKeyForbidden") : t("accessKey.setKeyError");
}
