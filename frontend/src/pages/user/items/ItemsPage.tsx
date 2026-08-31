import { useState } from "react";
import { useTranslation } from "react-i18next";
import { FileUploadForm } from "../../../modules/user/items/forms/FileUploadForm";
import { NoteForm } from "../../../modules/user/items/forms/NoteForm";
import { UnlockItemForm } from "../../../modules/user/items/forms/UnlockItemForm";
import { ItemCard } from "../../../modules/user/items/view/ItemCard";
import { ItemFilters } from "../../../modules/user/items/view/ItemFilters";
import { useListItemsQuery } from "../../../store/api/itemApi";
import type { ItemListParams } from "../../../store/types/item";

export function ItemsPage() {
  const { t } = useTranslation();
  const [filters, setFilters] = useState<ItemListParams>({});
  const [page, setPage] = useState(1);
  const { data, isLoading, error } = useListItemsQuery({ ...filters, page });

  // Post-review fix: GET /api/items is paginated now (see itemApi.ts's
  // ItemListResult) — any filter change has to reset back to page 1, or a
  // narrower result set could leave the current page past its own last one.
  const handleFiltersChange = (next: ItemListParams) => {
    setFilters(next);
    setPage(1);
  };

  const totalPages = undefined !== data ? Math.max(1, Math.ceil(data.total / data.pageSize)) : 1;

  // div, not <main> — Część 11's SidebarLayout (UserLayout) already provides one.
  return (
    <div className="items-page">
      <h1>{t("nav.items")}</h1>

      <NoteForm />
      <FileUploadForm />
      <UnlockItemForm />

      <ItemFilters filters={filters} onChange={handleFiltersChange} />

      {isLoading && <p>{t("common.loading")}</p>}
      {undefined !== error && <p className="form-error">{t("items.fetchError")}</p>}
      {undefined !== data && 0 === data.items.length && <p>{t("items.empty")}</p>}

      {undefined !== data && data.items.length > 0 && (
        <div className="item-list">
          {data.items.map((item) => (
            <ItemCard key={item.id} item={item} />
          ))}
        </div>
      )}

      {undefined !== data && totalPages > 1 && (
        <div className="item-list-pager">
          <button type="button" onClick={() => setPage((current) => current - 1)} disabled={page <= 1}>
            {t("items.pagerPrevious")}
          </button>
          <span>{t("items.pagerStatus", { page, totalPages })}</span>
          <button type="button" onClick={() => setPage((current) => current + 1)} disabled={page >= totalPages}>
            {t("items.pagerNext")}
          </button>
        </div>
      )}
    </div>
  );
}
