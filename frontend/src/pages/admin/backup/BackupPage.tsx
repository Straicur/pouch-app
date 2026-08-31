import { useState } from "react";
import { useTranslation } from "react-i18next";
import { ApiEndpoints } from "../../../libs/apiEndpoints";
import { ConfirmDialog } from "../../../modules/shared/view/ConfirmDialog";
import { Button } from "../../../ui/catalyst/button";
import { Heading } from "../../../ui/catalyst/heading";
import { triggerDownload } from "../../../utils/triggerDownload";

// Part 10: "eksport/backup całości jako ZIP".
export function BackupPage() {
  const { t } = useTranslation();
  const [isConfirmOpen, setIsConfirmOpen] = useState(false);

  const handleBackup = () => {
    triggerDownload(ApiEndpoints.ADMIN_BACKUP);
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
