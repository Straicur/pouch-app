import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import ReactMarkdown from "react-markdown";
import { type Item, useGetItemThumbnailLinkMutation, useUpdateNoteMutation } from "../store/api/itemApi";

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

export function ItemCard({ item }: ItemCardProps) {
  const { t } = useTranslation();
  const thumbnailUrl = useItemThumbnailUrl(item);
  const title = "url" === item.type ? (item.pageTitle ?? item.name) : item.name;
  const description = "url" === item.type ? item.pageDescription : null;

  return (
    <article className="item-card">
      {null !== thumbnailUrl && <img src={thumbnailUrl} alt="" className="item-card-thumbnail" />}
      <div className="item-card-body">
        <p className="item-card-type">{t(`items.type.${item.type}`)}</p>
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
        {"pending" === item.processingStatus && <p className="item-card-status">{t("items.processing")}</p>}
        {"failed" === item.processingStatus && (
          <p className="item-card-status item-card-status-error">
            {t("items.processingError")}
            {null !== item.processingError ? `: ${item.processingError}` : ""}
          </p>
        )}
      </div>
    </article>
  );
}
