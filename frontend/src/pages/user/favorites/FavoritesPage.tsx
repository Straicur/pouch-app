import { useState } from "react";
import { useTranslation } from "react-i18next";
import { LoadingIndicator } from "../../../modules/shared/view/LoadingIndicator";
import { ItemGrid } from "../../../modules/user/items/view/ItemGrid";
import { useListItemsQuery } from "../../../store/api/itemApi";
import { Button } from "../../../ui/catalyst/button";
import { Heading } from "../../../ui/catalyst/heading";

// Część 14: lista samych ulubionych, osobna od głównej listy z wyszukiwarką
// (ItemsPage) — bez filtrów, tylko `favorite: true` + paginacja. Backend
// sortuje po createdAt DESC domyślnie (ItemRepository::findFilteredPage()),
// więc "od najnowszego do najstarszego" nie wymaga niczego dodatkowego.
const FAVORITES_PAGE_SIZE = 24;

export function FavoritesPage() {
  const { t } = useTranslation();
  const [page, setPage] = useState(1);
  const { data, isLoading, error } = useListItemsQuery({ favorite: true, page, pageSize: FAVORITES_PAGE_SIZE });

  const totalPages = undefined !== data ? Math.max(1, Math.ceil(data.total / data.pageSize)) : 1;

  return (
    <div className="flex flex-col gap-4">
      <Heading variant="page">{t("nav.favorites")}</Heading>

      {isLoading && <LoadingIndicator />}
      {undefined !== error && <p className="text-red-600 dark:text-red-400">{t("items.fetchError")}</p>}
      {undefined !== data && 0 === data.items.length && <p>{t("favorites.empty")}</p>}

      {undefined !== data && data.items.length > 0 && <ItemGrid items={data.items} />}

      {undefined !== data && totalPages > 1 && (
        <div className="flex items-center justify-center gap-3">
          <Button variant="outline" size="small" onClick={() => setPage((current) => current - 1)} disabled={page <= 1}>
            {t("items.pagerPrevious")}
          </Button>
          <span className="text-sm text-zinc-600 dark:text-zinc-400">
            {t("items.pagerStatus", { page, totalPages })}
          </span>
          <Button
            variant="outline"
            size="small"
            onClick={() => setPage((current) => current + 1)}
            disabled={page >= totalPages}
          >
            {t("items.pagerNext")}
          </Button>
        </div>
      )}
    </div>
  );
}
