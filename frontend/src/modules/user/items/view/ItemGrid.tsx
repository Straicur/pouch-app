import type { ItemSummary } from "../../../../store/types/item";
import { ItemCard } from "./ItemCard";
import { LockedItemCard } from "./LockedItemCard";

interface ItemGridProps {
  items: ItemSummary[];
}

// Wydzielone z ItemsPage — używane też przez HomePage, żeby karty itemów
// wyglądały i zachowywały się identycznie w obu miejscach.
export function ItemGrid({ items }: ItemGridProps) {
  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {items.map((item) =>
        item.locked ? <LockedItemCard key={item.id} item={item} /> : <ItemCard key={item.id} item={item} />,
      )}
    </div>
  );
}
