import { useState } from "react";
import { useTranslation } from "react-i18next";
import { type ItemListParams, useListItemsQuery } from "../../../store/api/itemApi";
import { FileUploadForm } from "./components/FileUploadForm";
import { ItemCard } from "./components/ItemCard";
import { ItemFilters } from "./components/ItemFilters";
import { NoteForm } from "./components/NoteForm";
import { UnlockItemForm } from "./components/UnlockItemForm";

export function ItemsPage() {
  const { t } = useTranslation();
  const [filters, setFilters] = useState<ItemListParams>({});
  const { data: items, isLoading, error } = useListItemsQuery(filters);

  // div, not <main> — Część 11's SidebarLayout (UserLayout) already provides one.
  return (
    <div className="items-page">
      <h1>{t("nav.items")}</h1>

      <NoteForm />
      <FileUploadForm />
      <UnlockItemForm />

      <ItemFilters filters={filters} onChange={setFilters} />

      {isLoading && <p>{t("common.loading")}</p>}
      {undefined !== error && <p className="form-error">{t("items.fetchError")}</p>}
      {undefined !== items && 0 === items.length && <p>{t("items.empty")}</p>}

      {undefined !== items && items.length > 0 && (
        <div className="item-list">
          {items.map((item) => (
            <ItemCard key={item.id} item={item} />
          ))}
        </div>
      )}
    </div>
  );
}
