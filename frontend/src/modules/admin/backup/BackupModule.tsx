import { useTranslation } from "react-i18next";
import { ApiEndpoints } from "../../../lib/apiEndpoints";
import { triggerDownload } from "../../../lib/triggerDownload";

// Part 10: "eksport/backup całości jako ZIP".
export function BackupModule() {
  const { t } = useTranslation();

  const handleBackup = () => {
    triggerDownload(ApiEndpoints.ADMIN_BACKUP);
  };

  return (
    <section className="admin-section">
      <h1>{t("admin.backup.title")}</h1>
      <button type="button" onClick={handleBackup}>
        {t("admin.backup.button")}
      </button>
    </section>
  );
}
