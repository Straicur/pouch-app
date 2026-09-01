import { type KeyboardEvent, useState } from "react";
import { useTranslation } from "react-i18next";
import { useListTagsQuery } from "../../../../store/api/tagApi";
import { Badge } from "../../../../ui/catalyst/badge";
import { Input } from "../../../../ui/catalyst/form/input";

interface TagsInputProps {
  value: string[];
  onChange: (tags: string[]) => void;
}

const DATALIST_ID = "tags-input-known-tags";

// Czytelne, klikalne chipy z osobnym polem do dopisania kolejnego tagu
// (zamiast jednej linijki "tagi oddzielone przecinkami") — używane zarówno
// przy dodawaniu itemu (AddItemModal) jak i przy edycji istniejącego
// (ItemDetailsModal).
export function TagsInput({ value, onChange }: TagsInputProps) {
  const { t } = useTranslation();
  const { data: knownTags } = useListTagsQuery();
  const [draft, setDraft] = useState("");

  const addTag = (raw: string) => {
    const tag = raw.trim().toLowerCase();
    if ("" === tag || value.includes(tag)) {
      setDraft("");
      return;
    }

    onChange([...value, tag]);
    setDraft("");
  };

  const removeTag = (tag: string) => {
    onChange(value.filter((existing) => existing !== tag));
  };

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if ("Enter" === event.key || "," === event.key) {
      event.preventDefault();
      addTag(draft);
    } else if ("Backspace" === event.key && "" === draft && value.length > 0) {
      removeTag(value[value.length - 1]);
    }
  };

  return (
    <div className="flex flex-col gap-2">
      <div className="flex flex-wrap items-center gap-2">
        {value.map((tag) => (
          <Badge key={tag} color="blue" className="gap-1.5 py-1 text-sm">
            {tag}
            <button
              type="button"
              onClick={() => removeTag(tag)}
              aria-label={t("tags.removeTag", { tag })}
              className="text-blue-700 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-200"
            >
              ×
            </button>
          </Badge>
        ))}
      </div>
      <Input
        type="text"
        list={DATALIST_ID}
        value={draft}
        onChange={(event) => setDraft(event.target.value)}
        onKeyDown={handleKeyDown}
        onBlur={() => addTag(draft)}
        placeholder={t("tags.addTagPlaceholder")}
      />
      <datalist id={DATALIST_ID}>
        {knownTags?.map((tag) => (
          <option key={tag} value={tag} />
        ))}
      </datalist>
    </div>
  );
}
