import { useTranslation } from "react-i18next";
import { usePouchFilter } from "../../../modules/admin/pouchFilter";
import { useListAuditLogQuery } from "../../../store/api/adminApi";
import { Heading } from "../../../ui/catalyst/heading";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "../../../ui/catalyst/table";

// AuditLogEntry["action"] mirrors the backend's snake_case values (see
// AuditLoggerInterface) — pl.ts's own keys stay camelCase (project
// convention), so this bridges the one mismatch ("key_change" -> "keyChange")
// instead of introducing a snake_case key into the translation dictionary.
const auditActionLabelKey = (action: string): string => {
  return "key_change" === action ? "keyChange" : action;
};

// "Kto/kiedy/skąd (IP) podejrzał/pobrał/usunął/zmienił klucz", zawężone do
// PouchSwitchera wybranej pouch.
export function AuditLogPage() {
  const { t } = useTranslation();
  const { pouchId } = usePouchFilter();
  const { data: entries } = useListAuditLogQuery({ limit: 50, pouchId });

  return (
    <section className="flex flex-col gap-4">
      <Heading variant="page">{t("admin.auditLog.title")}</Heading>
      <Table>
        <TableHead>
          <TableRow>
            <TableHeader>{t("admin.auditLog.when")}</TableHeader>
            <TableHeader>{t("admin.auditLog.action")}</TableHeader>
            <TableHeader>{t("admin.auditLog.resource")}</TableHeader>
            <TableHeader>{t("admin.auditLog.pouch")}</TableHeader>
            <TableHeader>{t("admin.auditLog.user")}</TableHeader>
            <TableHeader>{t("admin.auditLog.ip")}</TableHeader>
          </TableRow>
        </TableHead>
        <TableBody>
          {entries?.map((entry) => (
            <TableRow key={entry.id}>
              <TableCell>{new Date(entry.createdAt).toLocaleString()}</TableCell>
              <TableCell>{t(`admin.auditLog.actionLabel.${auditActionLabelKey(entry.action)}`)}</TableCell>
              <TableCell>
                {t(`admin.auditLog.resourceLabel.${entry.resourceType}`)} #{entry.resourceId}
              </TableCell>
              <TableCell>{entry.pouchName ?? "—"}</TableCell>
              <TableCell>{entry.userEmail ?? t("admin.auditLog.systemUser")}</TableCell>
              <TableCell>{entry.ip ?? "—"}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </section>
  );
}
