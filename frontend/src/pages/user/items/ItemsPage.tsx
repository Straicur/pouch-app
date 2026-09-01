import { useCallback, useState } from "react";
import { useTranslation } from "react-i18next";
import { LoadingIndicator } from "../../../modules/shared/view/LoadingIndicator";
import { AddItemModal } from "../../../modules/user/items/forms/AddItemModal";
import { ItemFilters } from "../../../modules/user/items/view/ItemFilters";
import { ItemGrid } from "../../../modules/user/items/view/ItemGrid";
import { useListItemsQuery } from "../../../store/api/itemApi";
import type { ItemListParams } from "../../../store/types/item";
import { Button } from "../../../ui/catalyst/button";
import { Heading } from "../../../ui/catalyst/heading";

export function ItemsPage() {
  const { t } = useTranslation();
  const [filters, setFilters] = useState<ItemListParams>({});
  const [page, setPage] = useState(1);
  const [isAddItemOpen, setIsAddItemOpen] = useState(false);
  const { data, isLoading, error } = useListItemsQuery({ ...filters, page });

  // Post-review fix: GET /api/items is paginated now (see itemApi.ts's
  // ItemListResult) — any filter change has to reset back to page 1, or a
  // narrower result set could leave the current page past its own last one.
  // Patches (not a full replacement) via setFilters' functional updater, and
  // wrapped in useCallback for a stable identity — ItemFilters' debounced
  // search effect depends on this reference staying the same across
  // unrelated re-renders (e.g. isLoading flipping), see its own comment.
  const handleFiltersChange = useCallback((patch: Partial<ItemListParams>) => {
    setFilters((current) => ({ ...current, ...patch }));
    setPage(1);
  }, []);

  const totalPages = undefined !== data ? Math.max(1, Math.ceil(data.total / data.pageSize)) : 1;

  // div, not <main> — Część 11's SidebarLayout (UserLayout) already provides one.
  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between gap-2">
        <Heading>{t("nav.items")}</Heading>
        <Button onClick={() => setIsAddItemOpen(true)}>{t("items.addItemButton")}</Button>
      </div>

      <ItemFilters filters={filters} onChange={handleFiltersChange} />

      {isLoading && <LoadingIndicator />}
      {undefined !== error && <p className="text-red-600 dark:text-red-400">{t("items.fetchError")}</p>}
      {undefined !== data && 0 === data.items.length && (
        <p>
          {undefined !== filters.q && "" !== filters.q
            ? t("items.emptySearch", { query: filters.q })
            : t("items.empty")}
        </p>
      )}

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

      <AddItemModal open={isAddItemOpen} onClose={() => setIsAddItemOpen(false)} />
    </div>
  );
}
