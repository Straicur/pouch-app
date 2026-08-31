import { useTranslation } from "react-i18next";
import type { ItemListParams } from "../../../../store/api/itemApi";
import { useListTagsQuery } from "../../../../store/api/tagApi";

interface ItemFiltersProps {
  filters: ItemListParams;
  onChange: (filters: ItemListParams) => void;
}

// One free-text `q` box (name/tags/note/OCR/OpenGraph all at once — see
// ItemRepository::findFiltered on the backend) plus the two structured
// filters (favorite, tag) a text search alone can't express well.
export function ItemFilters({ filters, onChange }: ItemFiltersProps) {
  const { t } = useTranslation();
  const { data: knownTags } = useListTagsQuery();

  const hasActiveFilters = true === filters.favorite || (filters.tags?.length ?? 0) > 0 || undefined !== filters.q;

  return (
    <div className="item-filters">
      <input
        type="search"
        value={filters.q ?? ""}
        onChange={(event) => onChange({ ...filters, q: "" === event.target.value ? undefined : event.target.value })}
        placeholder={t("items.searchPlaceholder")}
        aria-label={t("items.searchPlaceholder")}
      />
      <input
        type="text"
        list="item-filters-known-tags"
        value={filters.tags?.[0] ?? ""}
        onChange={(event) =>
          onChange({ ...filters, tags: "" === event.target.value ? undefined : [event.target.value] })
        }
        placeholder={t("items.tagFilterPlaceholder")}
        aria-label={t("items.tagFilterPlaceholder")}
      />
      <datalist id="item-filters-known-tags">
        {knownTags?.map((tag) => (
          <option key={tag} value={tag} />
        ))}
      </datalist>
      <label className="item-filters-favorite">
        <input
          type="checkbox"
          checked={true === filters.favorite}
          onChange={(event) => onChange({ ...filters, favorite: event.target.checked ? true : undefined })}
        />
        {t("items.favoritesOnly")}
      </label>
      {hasActiveFilters && (
        <button type="button" onClick={() => onChange({ categoryId: filters.categoryId })}>
          {t("items.clearFilters")}
        </button>
      )}
    </div>
  );
}
