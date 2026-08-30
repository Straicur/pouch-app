import { useState } from "react";
import { useTranslation } from "react-i18next";
import { Link } from "react-router-dom";
import { ItemCard } from "../components/ItemCard";
import { ItemFilters } from "../components/ItemFilters";
import { NoteForm } from "../components/NoteForm";
import { type ItemListParams, useListItemsQuery } from "../store/api/itemApi";

export function ItemsPage() {
  const { t } = useTranslation();
  const [filters, setFilters] = useState<ItemListParams>({});
  const { data: items, isLoading, error } = useListItemsQuery(filters);

  return (
    <main className="items-page">
      <h1>{t("common.appName")}</h1>
      <Link to="/">{t("items.homeLink")}</Link>

      <NoteForm />

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
    </main>
  );
}
