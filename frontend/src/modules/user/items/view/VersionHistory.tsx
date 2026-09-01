import { type ChangeEvent, useRef } from "react";
import { useTranslation } from "react-i18next";
import { getApiErrorBody } from "../../../../libs/apiError";
import { toastUtil } from "../../../../libs/toastUtil";
import {
  useGetVersionDownloadLinkMutation,
  useListVersionsQuery,
  useOverwriteFileMutation,
} from "../../../../store/api/itemApi";
import { Button } from "../../../../ui/catalyst/button";

interface VersionHistoryProps {
  itemId: number;
}

const formatSize = (bytes: number): string => {
  return bytes < 1024 ? `${bytes} B` : `${(bytes / 1024).toFixed(1)} KB`;
};

// FILE items only (ItemService::overwriteFile() rejects any other type). The
// current content is already shown by ItemDetailsModal itself; this is
// purely the "what it used to be" history, per GET .../versions' own doc.
// Shown directly, no expand/collapse toggle — everything in the details
// modal appears without extra clicking.
export function VersionHistory({ itemId }: VersionHistoryProps) {
  const { t } = useTranslation();
  const { data: versions } = useListVersionsQuery(itemId);
  const [overwriteFile, { isLoading: isUploading }] = useOverwriteFileMutation();
  const [getVersionDownloadLink] = useGetVersionDownloadLinkMutation();
  const fileInputRef = useRef<HTMLInputElement>(null);

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
      // Same-tab navigation, not window.open() (see ItemCard's DownloadButton
      // for why "noreferrer" rules that out) — the signed URL responds with
      // Content-Disposition: attachment, so this downloads without leaving
      // the app.
      window.location.assign(link.url);
    } catch {
      toastUtil.showToast(t("versions.downloadError"), "error");
    }
  };

  return (
    <div className="flex flex-col gap-2">
      <p className="text-sm font-medium text-zinc-950 dark:text-white">
        {t("versions.toggle", { count: versions?.length ?? 0 })}
      </p>

      <Button
        size="small"
        variant="outline"
        className="w-fit"
        onClick={() => fileInputRef.current?.click()}
        disabled={isUploading}
      >
        {isUploading ? t("versions.uploading") : t("versions.uploadNewLabel")}
      </Button>
      <input type="file" ref={fileInputRef} onChange={handleUpload} disabled={isUploading} className="hidden" />

      {undefined !== versions && versions.length > 0 && (
        <ul className="flex flex-col gap-1">
          {versions.map((version) => (
            <li key={version.version} className="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
              <span>{t("versions.entryLabel", { version: version.version, filename: version.originalFilename })}</span>
              <span className="text-xs text-zinc-500 dark:text-zinc-400">{formatSize(version.size)}</span>
              <button
                type="button"
                onClick={() => handleDownload(version.version)}
                className="text-xs text-blue-600 hover:underline dark:text-blue-400"
              >
                {t("versions.download")}
              </button>
            </li>
          ))}
        </ul>
      )}

      {undefined !== versions && 0 === versions.length && (
        <p className="text-sm text-zinc-500 dark:text-zinc-400">{t("versions.empty")}</p>
      )}
    </div>
  );
}
