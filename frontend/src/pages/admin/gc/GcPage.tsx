import { useTranslation } from "react-i18next";
import { toastUtil } from "../../../libs/toastUtil";
import { useListGcRunsQuery, useRunGcMutation } from "../../../store/api/adminApi";

// Part 10: "podgląd automatycznego czyszczenia + ręczne 'Run GC Now' + logi".
export function GcPage() {
  const { t } = useTranslation();
  const { data: runs } = useListGcRunsQuery(10);
  const [runGc, { isLoading }] = useRunGcMutation();

  const handleRun = async () => {
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
    <section className="admin-section">
      <h1>{t("admin.gc.title")}</h1>
      <button type="button" onClick={handleRun} disabled={isLoading}>
        {isLoading ? t("admin.gc.running") : t("admin.gc.runButton")}
      </button>

      <table className="admin-table">
        <thead>
          <tr>
            <th>{t("admin.gc.runAt")}</th>
            <th>{t("admin.gc.trigger")}</th>
            <th>{t("admin.gc.expiredCount")}</th>
            <th>{t("admin.gc.purgedCount")}</th>
          </tr>
        </thead>
        <tbody>
          {runs?.map((run) => (
            <tr key={run.id}>
              <td>{new Date(run.runAt).toLocaleString()}</td>
              <td>{t(`admin.gc.triggerLabel.${run.trigger}`)}</td>
              <td>{run.expiredCount}</td>
              <td>{run.purgedCount}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  );
}
