import { useState } from "react";
import { useTranslation } from "react-i18next";
import { toastUtil } from "../../../../libs/toastUtil";
import { useGetPublicLinkMutation } from "../../../../store/api/itemApi";
import type { PublicLink } from "../../../../store/types/item";

interface ShareButtonProps {
  itemId: number;
}

// Part 9 — a deliberate, one-click action (product doc: "świadome kliknięcie
// 'udostępnij'"), not something offered automatically on every item; the
// resulting link needs no account at all, which is exactly why generating it
// still does (see ItemController::publicLink()).
export function ShareButton({ itemId }: ShareButtonProps) {
  const { t } = useTranslation();
  const [getPublicLink, { isLoading }] = useGetPublicLinkMutation();
  const [link, setLink] = useState<PublicLink | null>(null);

  const handleShare = async () => {
    try {
      const result = await getPublicLink(itemId).unwrap();
      setLink(result);
    } catch {
      toastUtil.showToast(t("share.error"), "error");
    }
  };

  const handleCopy = async (url: string) => {
    try {
      await navigator.clipboard.writeText(url);
      toastUtil.showToast(t("share.copied"), "success");
    } catch {
      toastUtil.showToast(t("share.copyError"), "error");
    }
  };

  if (null === link) {
    return (
      <button type="button" onClick={handleShare} disabled={isLoading}>
        {isLoading ? t("share.generating") : t("share.button")}
      </button>
    );
  }

  return (
    <div className="share-link">
      <p>{t("share.expiresAt", { date: new Date(link.expiresAt).toLocaleString() })}</p>
      <div className="share-link-row">
        <input type="text" readOnly value={link.viewUrl} onFocus={(event) => event.target.select()} />
        <button type="button" onClick={() => handleCopy(link.viewUrl)}>
          {t("share.copyButton")}
        </button>
      </div>
      {null !== link.downloadUrl && (
        <a href={link.downloadUrl} target="_blank" rel="noreferrer">
          {t("share.downloadLink")}
        </a>
      )}
    </div>
  );
}
