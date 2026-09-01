import { useState } from "react";
import { useTranslation } from "react-i18next";
import { ApiEndpoints } from "../../../libs/apiEndpoints";
import { usePouchFilter } from "../../../modules/admin/pouchFilter";
import { ConfirmDialog } from "../../../modules/shared/view/ConfirmDialog";
import { Button } from "../../../ui/catalyst/button";
import { Heading } from "../../../ui/catalyst/heading";
import { triggerDownload } from "../../../utils/triggerDownload";

// "Eksport/backup całości jako ZIP", zawężone do PouchSwitchera wybranej
// pouch (pominięte pouchId = backup wszystkiego, tak jak wcześniej).
export function BackupPage() {
  const { t } = useTranslation();
  const { pouchId } = usePouchFilter();
  const [isConfirmOpen, setIsConfirmOpen] = useState(false);

  const handleBackup = () => {
    const url = null !== pouchId ? `${ApiEndpoints.ADMIN_BACKUP}?pouchId=${pouchId}` : ApiEndpoints.ADMIN_BACKUP;
    triggerDownload(url);
    setIsConfirmOpen(false);
  };

  return (
    <section className="flex flex-col gap-4">
      <Heading variant="page">{t("admin.backup.title")}</Heading>
      <Button onClick={() => setIsConfirmOpen(true)} className="w-fit">
        {t("admin.backup.button")}
      </Button>

      <ConfirmDialog
        open={isConfirmOpen}
        title={t("admin.backup.confirmTitle")}
        description={t("admin.backup.confirmDescription")}
        confirmLabel={t("admin.backup.button")}
        onConfirm={handleBackup}
        onClose={() => setIsConfirmOpen(false)}
      />
    </section>
  );
}
