import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import { useListCategoriesQuery } from "../../../../store/api/categoryApi";
import { useListTagsQuery } from "../../../../store/api/tagApi";
import type { ItemListParams } from "../../../../store/types/item";
import { Button } from "../../../../ui/catalyst/button";
import { Checkbox, CheckboxField } from "../../../../ui/catalyst/form/checkbox";
import { Field, Label } from "../../../../ui/catalyst/form/fieldset";
import { Input } from "../../../../ui/catalyst/form/input";
import { MultiSelect } from "../../../../ui/catalyst/form/multi-select";

interface ItemFiltersProps {
  filters: ItemListParams;
  onChange: (patch: Partial<ItemListParams>) => void;
}

const MIN_QUERY_LENGTH = 2;
const SEARCH_DEBOUNCE_MS = 300;

// Free-text `q` (name > page title > note content > OCR/description,
// ranked — see backend's ItemRepository::searchMatchingIds()) plus three
// hard filters a ranked text search can't express well: category and tag
// (both "matches any" of the selected multi-select values, no ranking — see
// backend's ItemListFilter) and favorite.
export function ItemFilters({ filters, onChange }: ItemFiltersProps) {
  const { t } = useTranslation();
  const { data: categories } = useListCategoriesQuery();
  const { data: knownTags } = useListTagsQuery();

  const [draftQuery, setDraftQuery] = useState(filters.q ?? "");

  // Search-as-you-type, but only once there's enough of a word to be a
  // meaningful prefix (see backend's buildPrefixTsQuery()) — debounced so
  // every keystroke doesn't fire its own request. `onChange` (ItemsPage's
  // handleFiltersChange) is a stable useCallback that patches via
  // setFilters' functional updater, so it never needs the current `filters`
  // here — reading `filters.q` in this effect would mean re-running the
  // debounce (and dropping in-flight keystrokes) on every unrelated filter
  // change too.
  useEffect(() => {
    const trimmed = draftQuery.trim();
    const timeoutId = window.setTimeout(() => {
      onChange({ q: trimmed.length >= MIN_QUERY_LENGTH ? trimmed : undefined });
    }, SEARCH_DEBOUNCE_MS);

    return () => window.clearTimeout(timeoutId);
  }, [draftQuery, onChange]);

  const hasActiveFilters =
    true === filters.favorite ||
    (filters.tags?.length ?? 0) > 0 ||
    (filters.categoryIds?.length ?? 0) > 0 ||
    undefined !== filters.q;

  const clearFilters = () => {
    setDraftQuery("");
    onChange({ categoryIds: undefined, tags: undefined, favorite: undefined, q: undefined });
  };

  return (
    <div className="flex flex-wrap items-end gap-4">
      <Field className="min-w-[240px] flex-1">
        <Label>{t("items.searchPlaceholder")}</Label>
        <Input
          type="search"
          value={draftQuery}
          onChange={(event) => setDraftQuery(event.target.value)}
          placeholder={t("items.searchPlaceholder")}
        />
      </Field>

      <Field className="w-48">
        <Label>{t("items.categoryFilterLabel")}</Label>
        <MultiSelect
          value={(filters.categoryIds ?? []).map(String)}
          onChange={(ids) => onChange({ categoryIds: ids.length > 0 ? ids.map(Number) : undefined })}
          options={(categories ?? []).map((category) => ({ value: String(category.id), label: category.name }))}
          placeholder={t("items.categoryFilterLabel")}
        />
      </Field>

      <Field className="w-48">
        <Label>{t("items.tagFilterLabel")}</Label>
        <MultiSelect
          value={filters.tags ?? []}
          onChange={(tags) => onChange({ tags: tags.length > 0 ? tags : undefined })}
          options={(knownTags ?? []).map((tag) => ({ value: tag, label: tag }))}
          placeholder={t("items.tagFilterLabel")}
        />
      </Field>

      <CheckboxField className="mb-2.5">
        <Checkbox
          checked={true === filters.favorite}
          onChange={(checked) => onChange({ favorite: checked ? true : undefined })}
        />
        <Label>{t("items.favoritesOnly")}</Label>
      </CheckboxField>

      {hasActiveFilters && (
        <Button variant="outline" size="small" onClick={clearFilters}>
          {t("items.clearFilters")}
        </Button>
      )}
    </div>
  );
}
