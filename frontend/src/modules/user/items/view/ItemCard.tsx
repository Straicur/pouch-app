import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import { useListCategoriesQuery } from "../../../../store/api/categoryApi";
import { useGetItemThumbnailLinkMutation } from "../../../../store/api/itemApi";
import type { ItemBaseLike, ItemSummary } from "../../../../store/types/item";
import { Badge } from "../../../../ui/catalyst/badge";
import { FavoriteStar } from "./FavoriteStar";
import { ItemDetailsModal } from "./ItemDetailsModal";

interface ItemCardProps {
  item: ItemSummary;
}

// Współdzielone przez ItemCard (mała miniatura) i ItemDetailsModal (duży
// podgląd) — działa na obu kształtach (ItemSummary/ItemDetail), stąd
// ItemBaseLike zamiast konkretnego typu.
export function useItemThumbnailUrl(item: ItemBaseLike): string | null {
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

// Część 13: karta na liście jest teraz czysto informacyjna — nazwa, opis,
// tagi, typ, kategoria — i w całości klikalna, otwiera ItemDetailsModal z
// resztą (ulubione, edycja, pobieranie, udostępnianie, historia wersji,
// klucz dostępu), zamiast trzymać to wszystko rozrzucone po karcie.
export function ItemCard({ item }: ItemCardProps) {
  const { t } = useTranslation();
  const [isDetailsOpen, setIsDetailsOpen] = useState(false);
  const thumbnailUrl = useItemThumbnailUrl(item);
  const { data: categories } = useListCategoriesQuery();
  const categoryName = categories?.find((category) => category.id === item.categoryId)?.name ?? null;
  const title = "url" === item.type ? (item.pageTitle ?? item.name) : item.name;
  const description = "url" === item.type ? item.pageDescription : item.noteContent;

  return (
    <>
      {/* FavoriteStar wewnątrz jest <span role="button">, nie <button> — patrz
          jej własny komentarz (zagnieżdżanie <button> w <button> jest
          niepoprawnym HTML-em). */}
      <button
        type="button"
        onClick={() => setIsDetailsOpen(true)}
        className="relative flex flex-col overflow-hidden rounded-lg text-left ring-1 ring-zinc-950/10 hover:ring-zinc-950/20 dark:ring-white/10 dark:hover:ring-white/20"
      >
        <FavoriteStar itemId={item.id} favorite={item.favorite} className="absolute top-2 right-2 z-10" />
        {null !== thumbnailUrl && <img src={thumbnailUrl} alt="" className="aspect-video w-full object-cover" />}
        <div className="flex flex-col gap-2 p-4">
          <h3 className="pr-6 text-base font-semibold text-zinc-950 dark:text-white">{title}</h3>
          {null !== description && (
            <p className="line-clamp-3 text-sm text-zinc-600 dark:text-zinc-400">{description}</p>
          )}
          <div className="flex flex-wrap items-center gap-1.5">
            {item.tags.length > 0 ? (
              item.tags.map((tag) => (
                <Badge key={tag} color="blue">
                  {tag}
                </Badge>
              ))
            ) : (
              <span className="text-xs text-zinc-400 dark:text-zinc-500">{t("tags.noTags")}</span>
            )}
          </div>
          <div className="mt-1 flex items-center justify-between gap-2 text-xs text-zinc-500 dark:text-zinc-400">
            <Badge>{t(`items.type.${item.type}`)}</Badge>
            {null !== categoryName && <span>{categoryName}</span>}
          </div>
          {"failed" === item.processingStatus && (
            <p className="text-xs text-red-600 dark:text-red-400">{t("items.processingError")}</p>
          )}
          {"pending" === item.processingStatus && (
            <p className="text-xs text-zinc-500 dark:text-zinc-400">{t("items.processing")}</p>
          )}
        </div>
      </button>

      <ItemDetailsModal itemId={item.id} open={isDetailsOpen} onClose={() => setIsDetailsOpen(false)} />
    </>
  );
}
