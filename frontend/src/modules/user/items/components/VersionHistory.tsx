import { type ChangeEvent, useState } from "react";
import { useTranslation } from "react-i18next";
import { getApiErrorBody } from "../../../../lib/apiError";
import { toastUtil } from "../../../../lib/toastUtil";
import {
  useGetVersionDownloadLinkMutation,
  useListVersionsQuery,
  useOverwriteFileMutation,
} from "../../../../store/api/itemApi";

interface VersionHistoryProps {
  itemId: number;
}

const formatSize = (bytes: number): string => {
  return bytes < 1024 ? `${bytes} B` : `${(bytes / 1024).toFixed(1)} KB`;
};

// Part 8 — FILE items only (ItemService::overwriteFile() rejects any other
// type). The current content is already shown by ItemCard itself; this is
// purely the "what it used to be" history, per GET .../versions' own doc.
export function VersionHistory({ itemId }: VersionHistoryProps) {
  const { t } = useTranslation();
  const { data: versions } = useListVersionsQuery(itemId);
  const [overwriteFile, { isLoading: isUploading }] = useOverwriteFileMutation();
  const [getVersionDownloadLink] = useGetVersionDownloadLinkMutation();
  const [isExpanded, setIsExpanded] = useState(false);

  const handleUpload = async (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (undefined === file) {
      return;
    }

    try {
      await overwriteFile({ id: itemId, file }).unwrap();
      toastUtil.showToast(t("versions.overwriteSuccess"), "success");
    } catch (error) {
      const detail = getApiErrorBody(error)?.detail;
      toastUtil.showToast(detail ?? t("versions.overwriteError"), "error");
    } finally {
      event.target.value = "";
    }
  };

  const handleDownload = async (version: number) => {
    try {
      const link = await getVersionDownloadLink({ id: itemId, version }).unwrap();
      // Post-review fix: see ItemCard's DownloadButton — window.open("",
      // "_blank", "noreferrer") never actually worked ("noreferrer" implies
      // "noopener", which makes window.open()'s return value always null,
      // so the tab could never be pointed at the link). Same-tab navigation
      // instead — the signed URL responds with Content-Disposition:
      // attachment, so this downloads without leaving the app.
      window.location.assign(link.url);
    } catch {
      toastUtil.showToast(t("versions.downloadError"), "error");
    }
  };

  return (
    <div className="version-history">
      <button type="button" className="version-history-toggle" onClick={() => setIsExpanded(!isExpanded)}>
        {t("versions.toggle", { count: versions?.length ?? 0 })}
      </button>

      {isExpanded && (
        <div className="version-history-body">
          <label className="version-history-upload">
            {isUploading ? t("versions.uploading") : t("versions.uploadNewLabel")}
            <input type="file" onChange={handleUpload} disabled={isUploading} />
          </label>

          {undefined !== versions && versions.length > 0 && (
            <ul className="version-history-list">
              {versions.map((version) => (
                <li key={version.version}>
                  <span>
                    {t("versions.entryLabel", { version: version.version, filename: version.originalFilename })}
                  </span>
                  <span className="version-history-size">{formatSize(version.size)}</span>
                  <button type="button" onClick={() => handleDownload(version.version)}>
                    {t("versions.download")}
                  </button>
                </li>
              ))}
            </ul>
          )}

          {undefined !== versions && 0 === versions.length && <p>{t("versions.empty")}</p>}
        </div>
      )}
    </div>
  );
}
