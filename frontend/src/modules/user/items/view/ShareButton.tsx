import { useState } from "react";
import { useTranslation } from "react-i18next";
import { toastUtil } from "../../../../libs/toastUtil";
import { useGetPublicLinkMutation } from "../../../../store/api/itemApi";
import type { PublicLink } from "../../../../store/types/item";
import { Button } from "../../../../ui/catalyst/button";
import { Input } from "../../../../ui/catalyst/form/input";

interface ShareButtonProps {
  itemId: number;
}

// $viewUrl alone is an item's raw JSON metadata, no HTML — visiting it for a
// FILE/PHOTO would show a blank page full of JSON instead of downloading
// anything, which is what "udostępnij" actually implies for those types. The
// primary, copyable link is $downloadUrl when the item has one (file content
// to actually download); $viewUrl (metadata) is offered as a secondary link,
// and becomes primary itself for types with nothing to download (note/url).
const primaryLink = (link: PublicLink): string => link.downloadUrl ?? link.viewUrl;

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
      <Button size="small" variant="outline" onClick={handleShare} disabled={isLoading}>
        {isLoading ? t("share.generating") : t("share.button")}
      </Button>
    );
  }

  return (
    <div className="flex flex-col gap-2">
      <p className="text-xs text-zinc-500 dark:text-zinc-400">
        {t("share.expiresAt", { date: new Date(link.expiresAt).toLocaleString() })}
      </p>
      <div className="flex items-center gap-2">
        <Input type="text" readOnly value={primaryLink(link)} onFocus={(event) => event.target.select()} />
        <Button size="small" onClick={() => handleCopy(primaryLink(link))}>
          {t("share.copyButton")}
        </Button>
      </div>
      {/* $viewUrl (metadata) only shown separately when it isn't already the
          primary link above (i.e. there's an actual downloadUrl too). */}
      {null !== link.downloadUrl && (
        <button
          type="button"
          onClick={() => handleCopy(link.viewUrl)}
          className="w-fit text-xs text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
        >
          {t("share.copyMetadataLink")}
        </button>
      )}
    </div>
  );
}
