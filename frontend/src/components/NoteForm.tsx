import { type FormEvent, useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import { useListCategoriesQuery } from "../store/api/categoryApi";
import { useCreateNoteMutation } from "../store/api/itemApi";

export function NoteForm() {
  const { t } = useTranslation();
  const { data: categories } = useListCategoriesQuery();
  const [createNote, { isLoading }] = useCreateNoteMutation();
  const [categoryId, setCategoryId] = useState<number | null>(null);
  const [name, setName] = useState("");
  const [content, setContent] = useState("");
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (null === categoryId && undefined !== categories && categories.length > 0) {
      setCategoryId(categories[0].id);
    }
  }, [categories, categoryId]);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (null === categoryId) {
      return;
    }

    setError(null);

    try {
      await createNote({ categoryId, content, name: "" === name.trim() ? undefined : name }).unwrap();
      setName("");
      setContent("");
    } catch {
      setError(t("notes.createError"));
    }
  };

  return (
    <form className="note-form" onSubmit={handleSubmit}>
      <h2>{t("notes.addTitle")}</h2>

      <label htmlFor="note-category">{t("notes.categoryLabel")}</label>
      <select
        id="note-category"
        value={categoryId ?? ""}
        onChange={(event) => setCategoryId(Number(event.target.value))}
      >
        {categories?.map((category) => (
          <option key={category.id} value={category.id}>
            {category.name}
          </option>
        ))}
      </select>

      <label htmlFor="note-name">{t("notes.nameLabel")}</label>
      <input id="note-name" type="text" value={name} onChange={(event) => setName(event.target.value)} />

      <label htmlFor="note-content">{t("notes.contentLabel")}</label>
      <textarea
        id="note-content"
        value={content}
        onChange={(event) => setContent(event.target.value)}
        rows={6}
        required
      />

      {null !== error && <p className="form-error">{error}</p>}

      <button type="submit" disabled={isLoading || null === categoryId}>
        {isLoading ? t("notes.submitting") : t("notes.submit")}
      </button>
    </form>
  );
}
