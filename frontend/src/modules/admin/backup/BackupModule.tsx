import { useState } from "react";
import { useTranslation } from "react-i18next";
import { ApiEndpoints } from "../../../lib/apiEndpoints";
import { downloadBlob } from "../../../lib/downloadBlob";
import { toastUtil } from "../../../lib/toastUtil";

// Part 10: "eksport/backup całości jako ZIP".
export function BackupModule() {
  const { t } = useTranslation();
  const [isDownloading, setIsDownloading] = useState(false);

  const handleBackup = async () => {
    setIsDownloading(true);

    try {
      await downloadBlob(ApiEndpoints.ADMIN_BACKUP, `pouch-backup-${new Date().toISOString().slice(0, 10)}.zip`);
    } catch {
      toastUtil.showToast(t("admin.backup.error"), "error");
    } finally {
      setIsDownloading(false);
    }
  };

  return (
    <section className="admin-section">
      <h1>{t("admin.backup.title")}</h1>
      <button type="button" onClick={handleBackup} disabled={isDownloading}>
        {isDownloading ? t("admin.backup.downloading") : t("admin.backup.button")}
      </button>
    </section>
  );
}
