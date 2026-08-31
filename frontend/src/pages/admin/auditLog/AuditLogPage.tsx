import { useTranslation } from "react-i18next";
import { useListAuditLogQuery } from "../../../store/api/adminApi";

// AuditLogEntry["action"] mirrors the backend's snake_case values (see
// AuditLoggerInterface) — pl.ts's own keys stay camelCase (project
// convention), so this bridges the one mismatch ("key_change" -> "keyChange")
// instead of introducing a snake_case key into the translation dictionary.
const auditActionLabelKey = (action: string): string => {
  return "key_change" === action ? "keyChange" : action;
};

// Part 10: "kto/kiedy/skąd (IP) podejrzał/pobrał/usunął/zmienił klucz".
export function AuditLogPage() {
  const { t } = useTranslation();
  const { data: entries } = useListAuditLogQuery({ limit: 50 });

  return (
    <section className="admin-section">
      <h1>{t("admin.auditLog.title")}</h1>
      <table className="admin-table">
        <thead>
          <tr>
            <th>{t("admin.auditLog.when")}</th>
            <th>{t("admin.auditLog.action")}</th>
            <th>{t("admin.auditLog.resource")}</th>
            <th>{t("admin.auditLog.user")}</th>
            <th>{t("admin.auditLog.ip")}</th>
          </tr>
        </thead>
        <tbody>
          {entries?.map((entry) => (
            <tr key={entry.id}>
              <td>{new Date(entry.createdAt).toLocaleString()}</td>
              <td>{t(`admin.auditLog.actionLabel.${auditActionLabelKey(entry.action)}`)}</td>
              <td>
                {t(`admin.auditLog.resourceLabel.${entry.resourceType}`)} #{entry.resourceId}
              </td>
              <td>{entry.userEmail ?? t("admin.auditLog.systemUser")}</td>
              <td>{entry.ip ?? "—"}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  );
}
