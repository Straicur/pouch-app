import clsx from "clsx";
import type { KeyboardEvent, MouseEvent } from "react";
import { useTranslation } from "react-i18next";
import { useMarkFavoriteMutation, useUnmarkFavoriteMutation } from "../../../../store/api/itemApi";

interface FavoriteStarProps {
  itemId: number;
  favorite: boolean;
  className?: string;
}

// Gwiazdka w rogu karty (ItemCard) i w nagłówku modalu detali
// (ItemDetailsModal) — świeci się, gdy item jest ulubiony, klik przełącza.
// stopPropagation() matters na karcie: cała karta jest klikalna (otwiera
// modal), gwiazdka musi przechwycić klik dla siebie, nie dla karty pod spodem.
// <span role="button">, nie <button>: na karcie ta gwiazdka siedzi wewnątrz
// innego <button> (całej karty) — zagnieżdżanie <button> w <button> to
// niepoprawny HTML, więc tu jest to świadomy wyjątek od useSemanticElements,
// z ręcznie dopisanym tabIndex/onKeyDown zamiast dostać je za darmo.
export function FavoriteStar({ itemId, favorite, className }: FavoriteStarProps) {
  const { t } = useTranslation();
  const [markFavorite] = useMarkFavoriteMutation();
  const [unmarkFavorite] = useUnmarkFavoriteMutation();
  const label = favorite ? t("tags.unmarkFavorite") : t("tags.markFavorite");

  const toggle = () => {
    void (favorite ? unmarkFavorite(itemId) : markFavorite(itemId));
  };

  const handleClick = (event: MouseEvent) => {
    event.stopPropagation();
    toggle();
  };

  const handleKeyDown = (event: KeyboardEvent) => {
    if ("Enter" === event.key || " " === event.key) {
      event.preventDefault();
      event.stopPropagation();
      toggle();
    }
  };

  return (
    // biome-ignore lint/a11y/useSemanticElements: nested inside ItemCard's own <button> — can't be a <button> itself
    <span
      role="button"
      tabIndex={0}
      onClick={handleClick}
      onKeyDown={handleKeyDown}
      aria-label={label}
      aria-pressed={favorite}
      className={clsx(
        "cursor-pointer text-xl leading-none",
        favorite
          ? "text-amber-400 hover:text-amber-500"
          : "text-zinc-300 hover:text-zinc-400 dark:text-zinc-600 dark:hover:text-zinc-500",
        className,
      )}
    >
      {favorite ? "★" : "☆"}
    </span>
  );
}
