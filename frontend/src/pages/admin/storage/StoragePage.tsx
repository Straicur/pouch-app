import { useState } from "react";
import { useTranslation } from "react-i18next";
import { toastUtil } from "../../../libs/toastUtil";
import { usePouchFilter } from "../../../modules/admin/pouchFilter";
import { AdminItemBrowser } from "../../../modules/admin/storage/AdminItemBrowser";
import { useGetStorageReportQuery, useSetStorageLimitMutation } from "../../../store/api/adminApi";
import { Button } from "../../../ui/catalyst/button";
import { Input } from "../../../ui/catalyst/form/input";
import { Heading, Subheading } from "../../../ui/catalyst/heading";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "../../../ui/catalyst/table";

const formatSize = (bytes: number): string => {
  return bytes < 1024 * 1024 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

// "Podgląd zużycia (per typ), globalne limity wagowe" — usage narrows to the
// PouchSwitcher's selected pouch (null = every pouch); limits are always
// system-wide (one config, not per pouch — see AdminController::storage()'s
// own docblock). "Zarządzanie itemami/plikami per pouch" (AdminItemBrowser)
// only renders once a specific pouch is picked — it has no "every pouch" mode.
export function StoragePage() {
  const { t } = useTranslation();
  const { pouchId } = usePouchFilter();
  const { data: report } = useGetStorageReportQuery(pouchId);
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
    <section className="flex flex-col gap-4">
      <Heading variant="page">{t("admin.storage.title")}</Heading>

      <Table>
        <TableHead>
          <TableRow>
            <TableHeader>{t("admin.storage.type")}</TableHeader>
            <TableHeader>{t("admin.storage.totalBytes")}</TableHeader>
            <TableHeader>{t("admin.storage.itemCount")}</TableHeader>
          </TableRow>
        </TableHead>
        <TableBody>
          {report?.byType.map((row) => (
            <TableRow key={row.type}>
              <TableCell>{t(`items.type.${row.type}`)}</TableCell>
              <TableCell>{formatSize(row.totalBytes)}</TableCell>
              <TableCell>{row.itemCount}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
      {undefined !== report && (
        <p className="text-sm text-zinc-600 dark:text-zinc-400">
          {t("admin.storage.archivedVersions", { size: formatSize(report.archivedVersionsBytes) })}
        </p>
      )}

      <Subheading>{t("admin.storage.limitsTitle")}</Subheading>
      <ul className="flex flex-col gap-2">
        {report?.limits.map((limit) => (
          <li key={limit.type} className="flex flex-wrap items-center gap-2 text-sm">
            <span className="w-24 text-zinc-950 dark:text-white">{t(`items.type.${limit.type}`)}</span>
            <span className="text-zinc-500 dark:text-zinc-400">
              {t("admin.storage.currentLimit", { size: formatSize(limit.maxSizeBytes) })}
            </span>
            <Input
              type="number"
              placeholder={t("admin.storage.newLimitPlaceholder")}
              value={limitEdits[limit.type] ?? ""}
              onChange={(event) => setLimitEdits({ ...limitEdits, [limit.type]: event.target.value })}
              className="w-32"
            />
            <Button size="small" variant="outline" onClick={() => handleSaveLimit(limit.type)} disabled={isSavingLimit}>
              {t("admin.storage.saveLimit")}
            </Button>
          </li>
        ))}
      </ul>

      {null !== pouchId ? (
        <AdminItemBrowser pouchId={pouchId} />
      ) : (
        <p className="text-sm text-zinc-500 dark:text-zinc-400">{t("admin.items.selectPouchHint")}</p>
      )}
    </section>
  );
}
