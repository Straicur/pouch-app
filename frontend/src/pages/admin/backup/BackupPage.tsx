import { useTranslation } from "react-i18next";
import { ApiEndpoints } from "../../../libs/apiEndpoints";
import { triggerDownload } from "../../../utils/triggerDownload";

// Part 10: "eksport/backup całości jako ZIP".
export function BackupPage() {
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
