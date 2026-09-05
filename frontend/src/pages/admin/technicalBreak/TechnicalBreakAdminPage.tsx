import { useState } from "react";
import { useTranslation } from "react-i18next";
import { toastUtil } from "../../../libs/toastUtil";
import { ConfirmDialog } from "../../../modules/shared/view/ConfirmDialog";
import {
  useDisableTechnicalBreakMutation,
  useEnableTechnicalBreakMutation,
  useGetTechnicalBreakStatusQuery,
} from "../../../store/api/adminApi";
import { Button } from "../../../ui/catalyst/button";
import { Field, Label } from "../../../ui/catalyst/form/fieldset";
import { Textarea } from "../../../ui/catalyst/form/textarea";
import { Heading } from "../../../ui/catalyst/heading";

// Przełącznik globalnej przerwy technicznej — patrz TechnicalBreakListener
// (backend) dla dokładnych zasad: blokuje każdego zalogowanego non-admina na
// każdym endpoincie, nigdy admina, nigdy requesty bez sesji (login itd.).
export function TechnicalBreakAdminPage() {
  const { t } = useTranslation();
  const { data: status } = useGetTechnicalBreakStatusQuery();
  const [enableTechnicalBreak, { isLoading: isEnabling }] = useEnableTechnicalBreakMutation();
  const [disableTechnicalBreak, { isLoading: isDisabling }] = useDisableTechnicalBreakMutation();
  const [message, setMessage] = useState("");
  const [isConfirmOpen, setIsConfirmOpen] = useState(false);

  const handleEnable = async () => {
    setIsConfirmOpen(false);

    try {
      await enableTechnicalBreak({ message: "" === message.trim() ? undefined : message.trim() }).unwrap();
      toastUtil.showToast(t("admin.technicalBreak.enableSuccess"), "success");
    } catch {
      toastUtil.showToast(t("admin.technicalBreak.enableError"), "error");
    }
  };

  const handleDisable = async () => {
    try {
      await disableTechnicalBreak().unwrap();
      setMessage("");
      toastUtil.showToast(t("admin.technicalBreak.disableSuccess"), "success");
    } catch {
      toastUtil.showToast(t("admin.technicalBreak.disableError"), "error");
    }
  };

  return (
    <section className="flex flex-col gap-4">
      <Heading variant="page">{t("admin.technicalBreak.title")}</Heading>
      <p className="max-w-2xl text-sm text-zinc-600 dark:text-zinc-400">{t("admin.technicalBreak.explanation")}</p>

      <p className="font-medium">
        {true === status?.active && null !== status.since
          ? t("admin.technicalBreak.statusActive", { since: new Date(status.since).toLocaleString() })
          : t("admin.technicalBreak.statusInactive")}
      </p>

      {true === status?.active ? (
        <Button onClick={handleDisable} disabled={isDisabling} className="w-fit">
          {isDisabling ? t("admin.technicalBreak.disabling") : t("admin.technicalBreak.disableButton")}
        </Button>
      ) : (
        <>
          <Field className="max-w-md">
            <Label>{t("admin.technicalBreak.messageLabel")}</Label>
            <Textarea
              rows={3}
              value={message}
              placeholder={t("admin.technicalBreak.messagePlaceholder")}
              onChange={(event) => setMessage(event.target.value)}
            />
          </Field>

          <Button onClick={() => setIsConfirmOpen(true)} disabled={isEnabling} className="w-fit">
            {isEnabling ? t("admin.technicalBreak.enabling") : t("admin.technicalBreak.enableButton")}
          </Button>
        </>
      )}

      <ConfirmDialog
        open={isConfirmOpen}
        title={t("admin.technicalBreak.confirmEnableTitle")}
        description={t("admin.technicalBreak.confirmEnableDescription")}
        confirmLabel={t("admin.technicalBreak.enableButton")}
        onConfirm={handleEnable}
        onClose={() => setIsConfirmOpen(false)}
        isConfirming={isEnabling}
      />
    </section>
  );
}
