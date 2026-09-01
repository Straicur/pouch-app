import { useState } from "react";
import { useTranslation } from "react-i18next";
import { useNavigate } from "react-router-dom";
import { ExceptionUuid, isApiError } from "../../../libs/apiError";
import { toastUtil } from "../../../libs/toastUtil";
import { ConfirmDialog } from "../../../modules/shared/view/ConfirmDialog";
import { useDeleteAccountMutation, useDeletePouchMutation } from "../../../store/api/accountApi";
import { useWhoAmIQuery } from "../../../store/api/sessionApi";
import { Button } from "../../../ui/catalyst/button";
import { Heading, Subheading } from "../../../ui/catalyst/heading";
import { Text } from "../../../ui/catalyst/text";

// An admin's pouch can't be deleted out from under the other accounts in it
// (backend: PouchDeletionService), so an admin only ever gets the
// whole-pouch option here, never the regular one — see deletePouchErrorMessage.
function deletePouchErrorMessage(error: unknown, t: (key: string) => string): string {
  if (isApiError(error, ExceptionUuid.CONFLICT)) {
    return t("settings.deletePouchConflict");
  }
  if (isApiError(error, ExceptionUuid.BAD_REQUEST)) {
    return t("settings.deletePouchLastAdmin");
  }
  return t("settings.deletePouchError");
}

export function SettingsPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data: currentUser } = useWhoAmIQuery();
  const [deleteAccount, { isLoading: isDeletingAccount }] = useDeleteAccountMutation();
  const [deletePouch, { isLoading: isDeletingPouch }] = useDeletePouchMutation();
  const [isConfirmOpen, setIsConfirmOpen] = useState(false);

  const handleDeleteAccount = async () => {
    try {
      await deleteAccount().unwrap();
      await navigate("/login", { replace: true });
    } catch {
      toastUtil.showToast(t("settings.deleteAccountError"), "error");
      setIsConfirmOpen(false);
    }
  };

  const handleDeletePouch = async () => {
    try {
      await deletePouch().unwrap();
      await navigate("/login", { replace: true });
    } catch (error) {
      toastUtil.showToast(deletePouchErrorMessage(error, t), "error");
      setIsConfirmOpen(false);
    }
  };

  const isAdmin = true === currentUser?.isAdmin;

  return (
    <div className="flex flex-col gap-8">
      <Heading variant="page">{t("settings.title")}</Heading>

      <section className="flex flex-col gap-4">
        <Subheading>{t("settings.dangerZoneTitle")}</Subheading>

        {isAdmin ? (
          <div className="flex flex-col gap-2">
            <Text>{t("settings.deletePouchExplanation")}</Text>
            <div>
              <Button variant="red" onClick={() => setIsConfirmOpen(true)}>
                {t("settings.deletePouchButton")}
              </Button>
            </div>
          </div>
        ) : (
          <div className="flex flex-col gap-2">
            <Text>{t("settings.deleteAccountExplanation")}</Text>
            <div>
              <Button variant="red" onClick={() => setIsConfirmOpen(true)}>
                {t("settings.deleteAccountButton")}
              </Button>
            </div>
          </div>
        )}
      </section>

      <ConfirmDialog
        open={isConfirmOpen}
        title={isAdmin ? t("settings.deletePouchConfirmTitle") : t("settings.deleteAccountConfirmTitle")}
        description={
          isAdmin ? t("settings.deletePouchConfirmDescription") : t("settings.deleteAccountConfirmDescription")
        }
        confirmLabel={isAdmin ? t("settings.deletePouchButton") : t("settings.deleteAccountButton")}
        onConfirm={() => void (isAdmin ? handleDeletePouch() : handleDeleteAccount())}
        onClose={() => setIsConfirmOpen(false)}
        isConfirming={isAdmin ? isDeletingPouch : isDeletingAccount}
      />
    </div>
  );
}
