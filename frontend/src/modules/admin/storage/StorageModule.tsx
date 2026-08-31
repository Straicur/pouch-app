import { useState } from "react";
import { useTranslation } from "react-i18next";
import { toastUtil } from "../../../lib/toastUtil";
import { useGetStorageReportQuery, useSetStorageLimitMutation } from "../../../store/api/adminApi";

const formatSize = (bytes: number): string => {
  return bytes < 1024 * 1024 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

// Part 10: "podgląd zużycia (per typ), globalne limity wagowe".
export function StorageModule() {
  const { t } = useTranslation();
  const { data: report } = useGetStorageReportQuery();
  const [setStorageLimit, { isLoading: isSavingLimit }] = useSetStorageLimitMutation();
  const [limitEdits, setLimitEdits] = useState<Record<string, string>>({});

  const handleSaveLimit = async (type: string) => {
    const raw = limitEdits[type];
    const maxSizeBytes = Number(raw);
    if (!raw || !Number.isFinite(maxSizeBytes) || maxSizeBytes <= 0) {
      return;
    }

    try {
      await setStorageLimit({ type, maxSizeBytes }).unwrap();
      toastUtil.showToast(t("admin.storage.limitSaved"), "success");
    } catch {
      toastUtil.showToast(t("admin.storage.limitError"), "error");
    }
  };

  return (
    <section className="admin-section">
      <h1>{t("admin.storage.title")}</h1>

      <table className="admin-table">
        <thead>
          <tr>
            <th>{t("admin.storage.type")}</th>
            <th>{t("admin.storage.totalBytes")}</th>
            <th>{t("admin.storage.itemCount")}</th>
          </tr>
        </thead>
        <tbody>
          {report?.byType.map((row) => (
            <tr key={row.type}>
              <td>{t(`items.type.${row.type}`)}</td>
              <td>{formatSize(row.totalBytes)}</td>
              <td>{row.itemCount}</td>
            </tr>
          ))}
        </tbody>
      </table>
      {undefined !== report && (
        <p>{t("admin.storage.archivedVersions", { size: formatSize(report.archivedVersionsBytes) })}</p>
      )}

      <h2>{t("admin.storage.limitsTitle")}</h2>
      <ul className="admin-limits-list">
        {report?.limits.map((limit) => (
          <li key={limit.type}>
            <span>{t(`items.type.${limit.type}`)}</span>
            <span>{t("admin.storage.currentLimit", { size: formatSize(limit.maxSizeBytes) })}</span>
            <input
              type="number"
              placeholder={t("admin.storage.newLimitPlaceholder")}
              value={limitEdits[limit.type] ?? ""}
              onChange={(event) => setLimitEdits({ ...limitEdits, [limit.type]: event.target.value })}
            />
            <button type="button" onClick={() => handleSaveLimit(limit.type)} disabled={isSavingLimit}>
              {t("admin.storage.saveLimit")}
            </button>
          </li>
        ))}
      </ul>
    </section>
  );
}
