import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { z } from "zod";
import { ExceptionUuid, getApiErrorBody, isApiError } from "../../../libs/apiError";
import i18n from "../../../libs/i18n";
import { toastUtil } from "../../../libs/toastUtil";
import { Button } from "../../../ui/catalyst/button";
import { ErrorMessage } from "../../../ui/catalyst/form/fieldset";
import { Input } from "../../../ui/catalyst/form/input";

interface AccessKeyPanelProps {
  // Czy ten zasób ma już ustawiony klucz: steruje, czy pokazujemy
  // "Ustaw klucz" (nie ma) czy "Zmień klucz"/"Usuń klucz" (ma) — zamiast
  // zawsze oferować oba warianty naraz (patrz
  // ItemResponseDTO::$hasAccessKey / CategoryResponseDTO::$hasAccessKey).
  hasKey: boolean;
  // Kategorie zostają odblokowywalne wprost z tego panelu (lista kategorii
  // pokazuje zablokowane kategorie po nazwie, tak jak zawsze). Itemy — nie:
  // zablokowany item ma własne, osobne miejsce do odblokowania na liście
  // (LockedItemCard); zanim ktoś w ogóle zobaczy ten panel dla itemu (w
  // ItemDetailsModal), musi już być odblokowany, więc pole "odblokuj" tu nie
  // ma sensu. Domyślnie true (kategorie).
  showUnlock?: boolean;
  onUnlock: (key: string) => Promise<unknown>;
  onSetKey: (key: string | null) => Promise<unknown>;
}

// Defined at module scope, like LoginPage — messages go through the imported
// i18n instance's t() directly; see locales/pl.ts's header.
const unlockKeySchema = z.object({ key: z.string().min(1, i18n.t("validation.keyRequired")) });
const setKeySchema = z.object({ key: z.string().min(1, i18n.t("validation.newKeyRequired")) });

type UnlockKeyValues = z.infer<typeof unlockKeySchema>;
type SetKeyValues = z.infer<typeof setKeySchema>;

// Part 7 — shared by categories (CategoryRow) and items (ItemDetailsModal),
// since both have the same underlying actions: submit a key to unlock, or
// set/change/remove the resource's own key.
export function AccessKeyPanel({ hasKey, showUnlock = true, onUnlock, onSetKey }: AccessKeyPanelProps) {
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
    <div className="flex flex-col gap-3">
      <p className="text-sm font-medium text-zinc-950 dark:text-white">{t("accessKey.sectionTitle")}</p>

      {hasKey && showUnlock && (
        <form className="flex items-center gap-2" onSubmit={unlockForm.handleSubmit(handleUnlock)} noValidate>
          <Input
            type="password"
            placeholder={t("accessKey.unlockPlaceholder")}
            aria-label={t("accessKey.unlockPlaceholder")}
            {...unlockForm.register("key")}
          />
          <Button type="submit" size="small" disabled={unlockForm.formState.isSubmitting}>
            {unlockForm.formState.isSubmitting ? t("accessKey.unlocking") : t("accessKey.unlockButton")}
          </Button>
          {undefined !== unlockForm.formState.errors.key && (
            <ErrorMessage>{unlockForm.formState.errors.key.message}</ErrorMessage>
          )}
        </form>
      )}

      <form className="flex items-center gap-2" onSubmit={setKeyForm.handleSubmit(handleSetKey)} noValidate>
        <Input
          type="password"
          placeholder={hasKey ? t("accessKey.changeKeyPlaceholder") : t("accessKey.setKeyPlaceholder")}
          aria-label={hasKey ? t("accessKey.changeKeyPlaceholder") : t("accessKey.setKeyPlaceholder")}
          {...setKeyForm.register("key")}
        />
        <Button type="submit" size="small" variant="outline" disabled={setKeyForm.formState.isSubmitting}>
          {setKeyForm.formState.isSubmitting
            ? t("accessKey.settingKey")
            : hasKey
              ? t("accessKey.changeKeyButton")
              : t("accessKey.setKeyButton")}
        </Button>
        {hasKey && (
          <Button
            type="button"
            size="small"
            variant="outline"
            onClick={handleRemoveKey}
            disabled={setKeyForm.formState.isSubmitting}
          >
            {t("accessKey.removeKeyButton")}
          </Button>
        )}
        {undefined !== setKeyForm.formState.errors.key && (
          <ErrorMessage>{setKeyForm.formState.errors.key.message}</ErrorMessage>
        )}
      </form>
    </div>
  );
}

export function unlockErrorMessage(
  error: unknown,
  t: (key: string, options?: Record<string, unknown>) => string,
): string {
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
