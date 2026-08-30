import { Link } from "react-router-dom";
import { ItemCard } from "../components/ItemCard";
import { useListItemsQuery } from "../store/api/itemApi";

export function ItemsPage() {
  const { data: items, isLoading, error } = useListItemsQuery(undefined);

  return (
    <main className="items-page">
      <h1>Pouch</h1>
      <Link to="/">Strona główna</Link>

      {isLoading && <p>Ładowanie…</p>}
      {undefined !== error && <p className="form-error">Nie udało się pobrać itemów.</p>}
      {undefined !== items && 0 === items.length && <p>Brak itemów do pokazania.</p>}

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
