import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import ReactMarkdown from "react-markdown";
import { toastUtil } from "../../../../lib/toastUtil";
import { useSetItemKeyMutation, useUnlockItemMutation } from "../../../../store/api/accessKeyApi";
import {
  type Item,
  useGetItemDownloadLinkMutation,
  useGetItemThumbnailLinkMutation,
  useMarkFavoriteMutation,
  useUnmarkFavoriteMutation,
  useUpdateNoteMutation,
  useUpdateTagsMutation,
} from "../../../../store/api/itemApi";
import { AccessKeyPanel } from "../../shared/AccessKeyPanel";
import { ShareButton } from "./ShareButton";
import { VersionHistory } from "./VersionHistory";

interface ItemCardProps {
  item: Item;
}

function useItemThumbnailUrl(item: Item): string | null {
  const [getThumbnailLink] = useGetItemThumbnailLinkMutation();
  const [thumbnailUrl, setThumbnailUrl] = useState<string | null>(null);

  useEffect(() => {
    setThumbnailUrl(null);

    if (!item.hasThumbnail) {
      return;
    }

    let cancelled = false;

    getThumbnailLink(item.id)
      .unwrap()
      .then((link) => {
        if (!cancelled) {
          setThumbnailUrl(link.url);
        }
      })
      .catch(() => undefined);

    return () => {
      cancelled = true;
    };
  }, [item.id, item.hasThumbnail, getThumbnailLink]);

  return thumbnailUrl;
}

interface NoteCardBodyProps {
  item: Item;
}

// "Edycja po fakcie" — the note-specific bit no other item type needs.
function NoteCardBody({ item }: NoteCardBodyProps) {
  const { t } = useTranslation();
  const [isEditing, setIsEditing] = useState(false);
  const [draft, setDraft] = useState(item.noteContent ?? "");
  const [updateNote, { isLoading }] = useUpdateNoteMutation();
  const [error, setError] = useState<string | null>(null);

  const startEditing = () => {
    setDraft(item.noteContent ?? "");
    setError(null);
    setIsEditing(true);
  };

  const handleSave = async () => {
    setError(null);

    try {
      await updateNote({ id: item.id, content: draft }).unwrap();
      setIsEditing(false);
    } catch {
      setError(t("notes.updateError"));
    }
  };

  if (isEditing) {
    return (
      <div className="item-card-note-edit">
        <textarea value={draft} onChange={(event) => setDraft(event.target.value)} rows={6} />
        {null !== error && <p className="form-error">{error}</p>}
        <div className="item-card-note-actions">
          <button type="button" onClick={handleSave} disabled={isLoading}>
            {isLoading ? t("notes.saving") : t("notes.save")}
          </button>
          <button type="button" onClick={() => setIsEditing(false)} disabled={isLoading}>
            {t("notes.cancel")}
          </button>
        </div>
      </div>
    );
  }

  return (
    <>
      <div className="item-card-note-preview">
        <ReactMarkdown>{item.noteContent ?? ""}</ReactMarkdown>
      </div>
      <button type="button" onClick={startEditing}>
        {t("notes.edit")}
      </button>
    </>
  );
}

interface FavoriteButtonProps {
  item: Item;
}

function FavoriteButton({ item }: FavoriteButtonProps) {
  const { t } = useTranslation();
  const [markFavorite] = useMarkFavoriteMutation();
  const [unmarkFavorite] = useUnmarkFavoriteMutation();

  const toggle = () => {
    void (item.favorite ? unmarkFavorite(item.id) : markFavorite(item.id));
  };

  return (
    <button
      type="button"
      className="item-card-favorite"
      onClick={toggle}
      aria-label={item.favorite ? t("tags.unmarkFavorite") : t("tags.markFavorite")}
      aria-pressed={item.favorite}
    >
      {item.favorite ? "★" : "☆"}
    </button>
  );
}

interface TagEditorProps {
  item: Item;
}

function TagEditor({ item }: TagEditorProps) {
  const { t } = useTranslation();
  const [isEditing, setIsEditing] = useState(false);
  const [draft, setDraft] = useState(item.tags.join(", "));
  const [updateTags, { isLoading }] = useUpdateTagsMutation();
  const [error, setError] = useState<string | null>(null);

  const startEditing = () => {
    setDraft(item.tags.join(", "));
    setError(null);
    setIsEditing(true);
  };

  const handleSave = async () => {
    setError(null);

    const tags = draft
      .split(",")
      .map((tag) => tag.trim())
      .filter((tag) => "" !== tag);

    try {
      await updateTags({ id: item.id, tags }).unwrap();
      setIsEditing(false);
    } catch {
      setError(t("tags.updateError"));
    }
  };

  if (isEditing) {
    return (
      <div className="item-card-tags-edit">
        <input
          type="text"
          value={draft}
          onChange={(event) => setDraft(event.target.value)}
          placeholder={t("tags.tagsPlaceholder")}
        />
        {null !== error && <p className="form-error">{error}</p>}
        <div className="item-card-tags-actions">
          <button type="button" onClick={handleSave} disabled={isLoading}>
            {isLoading ? t("tags.saving") : t("tags.save")}
          </button>
          <button type="button" onClick={() => setIsEditing(false)} disabled={isLoading}>
            {t("tags.cancel")}
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="item-card-tags">
      {item.tags.length > 0 ? (
        item.tags.map((tag) => (
          <span key={tag} className="item-card-tag-chip">
            {tag}
          </span>
        ))
      ) : (
        <span className="item-card-tag-chip item-card-tag-chip-empty">{t("tags.noTags")}</span>
      )}
      <button type="button" className="item-card-tags-edit-button" onClick={startEditing}>
        {t("tags.editTags")}
      </button>
    </div>
  );
}

interface DownloadButtonProps {
  item: Item;
}

// Was missing entirely before — a FILE/PHOTO item had a thumbnail preview
// but no way to actually get the file itself.
function DownloadButton({ item }: DownloadButtonProps) {
  const { t } = useTranslation();
  const [getDownloadLink, { isLoading }] = useGetItemDownloadLinkMutation();

  const handleDownload = async () => {
    try {
      const link = await getDownloadLink(item.id).unwrap();
      // Post-review fix: window.open("", "_blank", "noreferrer") — the
      // previous attempt at dodging the popup-blocker — doesn't actually
      // work: "noreferrer" implies "noopener", and with "noopener" set,
      // window.open()'s return value is always null (there's no window
      // reference to hand back), so `tab.location.href = ...` never ran and
      // the user was left staring at a permanently blank tab. The signed
      // download URL itself responds with Content-Disposition: attachment
      // (see ItemController::download()), so navigating the *current* tab
      // triggers a download without leaving the app — no popup, no
      // blocker, nothing that can silently no-op.
      window.location.assign(link.url);
    } catch {
      toastUtil.showToast(t("items.downloadError"), "error");
    }
  };

  return (
    <button type="button" onClick={handleDownload} disabled={isLoading}>
      {isLoading ? t("items.downloading") : t("items.download")}
    </button>
  );
}

interface ItemAccessKeySectionProps {
  item: Item;
}

// Part 7 — an item's own key, independent of whatever key its category has
// (see AccessKeyGuard) — always offered, same reasoning as AccessKeyPanel's
// own docblock.
function ItemAccessKeySection({ item }: ItemAccessKeySectionProps) {
  const [unlockItem] = useUnlockItemMutation();
  const [setItemKey] = useSetItemKeyMutation();

  return (
    <AccessKeyPanel
      onUnlock={(key) => unlockItem({ itemId: item.id, key }).unwrap()}
      onSetKey={(key) => setItemKey({ itemId: item.id, key }).unwrap()}
    />
  );
}

export function ItemCard({ item }: ItemCardProps) {
  const { t } = useTranslation();
  const thumbnailUrl = useItemThumbnailUrl(item);
  const title = "url" === item.type ? (item.pageTitle ?? item.name) : item.name;
  const description = "url" === item.type ? item.pageDescription : null;

  return (
    <article className="item-card">
      {null !== thumbnailUrl && <img src={thumbnailUrl} alt="" className="item-card-thumbnail" />}
      <div className="item-card-body">
        <div className="item-card-header">
          <p className="item-card-type">{t(`items.type.${item.type}`)}</p>
          <FavoriteButton item={item} />
        </div>
        <h3 className="item-card-title">
          {"url" === item.type && null !== item.url ? (
            <a href={item.url} target="_blank" rel="noreferrer">
              {title}
            </a>
          ) : (
            title
          )}
        </h3>
        {null !== description && <p className="item-card-description">{description}</p>}
        {"note" === item.type && <NoteCardBody item={item} />}
        <TagEditor item={item} />
        {"pending" === item.processingStatus && <p className="item-card-status">{t("items.processing")}</p>}
        {"failed" === item.processingStatus && (
          <p className="item-card-status item-card-status-error">
            {t("items.processingError")}
            {null !== item.processingError ? `: ${item.processingError}` : ""}
          </p>
        )}

        <div className="item-card-actions">
          {/* Downloading a file needs actual file content — restricted to
              file/photo. Sharing a public link works for every item type
              (backend's public-link endpoint has no such restriction). */}
          {("file" === item.type || "photo" === item.type) && <DownloadButton item={item} />}
          <ShareButton itemId={item.id} />
        </div>
        {"file" === item.type && <VersionHistory itemId={item.id} />}

        <ItemAccessKeySection item={item} />
      </div>
    </article>
  );
}
