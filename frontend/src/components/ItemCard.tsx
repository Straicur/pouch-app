import { useEffect, useState } from "react";
import { type Item, useGetItemThumbnailLinkMutation } from "../store/api/itemApi";

interface ItemCardProps {
  item: Item;
}

const TYPE_LABELS: Record<Item["type"], string> = {
  file: "Plik",
  url: "Link",
  photo: "Zdjęcie",
};

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

export function ItemCard({ item }: ItemCardProps) {
  const thumbnailUrl = useItemThumbnailUrl(item);
  const title = "url" === item.type ? (item.pageTitle ?? item.name) : item.name;
  const description = "url" === item.type ? item.pageDescription : null;

  return (
    <article className="item-card">
      {null !== thumbnailUrl && <img src={thumbnailUrl} alt="" className="item-card-thumbnail" />}
      <div className="item-card-body">
        <p className="item-card-type">{TYPE_LABELS[item.type]}</p>
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
        {"pending" === item.processingStatus && <p className="item-card-status">Przetwarzanie…</p>}
        {"failed" === item.processingStatus && (
          <p className="item-card-status item-card-status-error">
            Błąd przetwarzania{null !== item.processingError ? `: ${item.processingError}` : ""}
          </p>
        )}
      </div>
    </article>
  );
}
