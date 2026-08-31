import { useState } from "react";
import { useTranslation } from "react-i18next";
import { toastUtil } from "../../../libs/toastUtil";
import { ConfirmDialog } from "../../../modules/shared/view/ConfirmDialog";
import { useListGcRunsQuery, useRunGcMutation } from "../../../store/api/adminApi";
import { Button } from "../../../ui/catalyst/button";
import { Heading } from "../../../ui/catalyst/heading";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "../../../ui/catalyst/table";

// Part 10: "podgląd automatycznego czyszczenia + ręczne 'Run GC Now' + logi".
// Co robi GC — patrz ItemGarbageCollector::run() (backend): (1) przenosi do
// kosza itemy, którym minęło TTL (expireOverdueItems), (2) trwale kasuje
// (razem z plikami w storage) to, co w koszu siedzi dłużej niż okres
// przechowywania — domyślnie 7 dni (purgeTrash). Odpala się automatycznie
// (harmonogram) i ręcznie tym przyciskiem.
export function GcPage() {
  const { t } = useTranslation();
  const { data: runs } = useListGcRunsQuery(10);
  const [runGc, { isLoading }] = useRunGcMutation();
  const [isConfirmOpen, setIsConfirmOpen] = useState(false);

  const handleRun = async () => {
    setIsConfirmOpen(false);

    try {
      const result = await runGc().unwrap();
      toastUtil.showToast(
        t("admin.gc.runSuccess", { expired: result.expiredCount, purged: result.purgedCount }),
        "success",
      );
    } catch {
      toastUtil.showToast(t("admin.gc.runError"), "error");
    }
  };

  return (
    <section className="flex flex-col gap-4">
      <Heading variant="page">{t("admin.gc.title")}</Heading>
      <p className="text-sm text-zinc-600 dark:text-zinc-400">{t("admin.gc.explanation")}</p>

      <Button onClick={() => setIsConfirmOpen(true)} disabled={isLoading} className="w-fit">
        {isLoading ? t("admin.gc.running") : t("admin.gc.runButton")}
      </Button>

      <Table>
        <TableHead>
          <TableRow>
            <TableHeader>{t("admin.gc.runAt")}</TableHeader>
            <TableHeader>{t("admin.gc.trigger")}</TableHeader>
            <TableHeader>{t("admin.gc.expiredCount")}</TableHeader>
            <TableHeader>{t("admin.gc.purgedCount")}</TableHeader>
          </TableRow>
        </TableHead>
        <TableBody>
          {runs?.map((run) => (
            <TableRow key={run.id}>
              <TableCell>{new Date(run.runAt).toLocaleString()}</TableCell>
              <TableCell>{t(`admin.gc.triggerLabel.${run.trigger}`)}</TableCell>
              <TableCell>{run.expiredCount}</TableCell>
              <TableCell>{run.purgedCount}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      <ConfirmDialog
        open={isConfirmOpen}
        title={t("admin.gc.confirmTitle")}
        description={t("admin.gc.confirmDescription")}
        confirmLabel={t("admin.gc.runButton")}
        onConfirm={handleRun}
        onClose={() => setIsConfirmOpen(false)}
        isConfirming={isLoading}
      />
    </section>
  );
}
