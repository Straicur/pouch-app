import { zodResolver } from "@hookform/resolvers/zod";
import { type ChangeEvent, useEffect, useRef, useState } from "react";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { z } from "zod";
import { getApiErrorBody } from "../../../../lib/apiError";
import { useListCategoriesQuery } from "../../../../store/api/categoryApi";
import { useCreateFileMutation } from "../../../../store/api/itemApi";

// Defined at module scope, like LoginPage/NoteForm — see locales/pl.ts's header.
const categoryFieldSchema = z.object({
  categoryId: z.coerce.number().int().positive("Wybierz kategorię"),
});

// A native file input's value can't be a controlled/registered RHF field the
// way text inputs are — $file stays in its own state, validated against this
// schema by hand at submit time instead of through the RHF/zod resolver pipeline.
const fileFieldSchema = z.instanceof(File, { message: "Wybierz plik" });

// z.coerce.number()'s input type (unknown, before coercion) differs from its
// output type (number, after) — useForm needs both, see NoteForm's own note.
type CategoryFieldInput = z.input<typeof categoryFieldSchema>;
type CategoryFieldValues = z.output<typeof categoryFieldSchema>;

// The prerequisite Part 8 (versioning) and Part 9 (public links) both need —
// there was no way at all to add a FILE item from this frontend before (only
// NoteForm existed). Mirrors NoteForm's shape/conventions.
export function FileUploadForm() {
  const { t } = useTranslation();
  const { data: categories } = useListCategoriesQuery();
  const [createFile, { isLoading }] = useCreateFileMutation();
  const [file, setFile] = useState<File | null>(null);
  const [fileError, setFileError] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const {
    register,
    handleSubmit,
    setValue,
    setError,
    formState: { errors },
  } = useForm<CategoryFieldInput, unknown, CategoryFieldValues>({ resolver: zodResolver(categoryFieldSchema) });

  useEffect(() => {
    if (undefined !== categories && categories.length > 0) {
      setValue("categoryId", categories[0].id);
    }
  }, [categories, setValue]);

  const handleFileChange = (event: ChangeEvent<HTMLInputElement>) => {
    setFile(event.target.files?.[0] ?? null);
    setFileError(null);
  };

  const onSubmit = async (values: CategoryFieldValues) => {
    const parsedFile = fileFieldSchema.safeParse(file);
    if (!parsedFile.success) {
      setFileError(parsedFile.error.issues[0]?.message ?? t("upload.createError"));
      return;
    }

    setFileError(null);

    try {
      await createFile({ categoryId: values.categoryId, file: parsedFile.data }).unwrap();
      setFile(null);
      if (null !== fileInputRef.current) {
        fileInputRef.current.value = "";
      }
    } catch (submitError) {
      const detail = getApiErrorBody(submitError)?.detail;
      setError("root", { message: detail ?? t("upload.createError") });
    }
  };

  return (
    <form className="file-upload-form" onSubmit={handleSubmit(onSubmit)} noValidate>
      <h2>{t("upload.addTitle")}</h2>

      <label htmlFor="file-upload-category">{t("upload.categoryLabel")}</label>
      <select id="file-upload-category" {...register("categoryId", { valueAsNumber: true })}>
        {categories?.map((category) => (
          <option key={category.id} value={category.id}>
            {category.name}
          </option>
        ))}
      </select>
      {undefined !== errors.categoryId && <p className="field-error">{errors.categoryId.message}</p>}

      <label htmlFor="file-upload-input">{t("upload.fileLabel")}</label>
      <input id="file-upload-input" type="file" ref={fileInputRef} onChange={handleFileChange} />
      {null !== fileError && <p className="field-error">{fileError}</p>}

      {undefined !== errors.root && <p className="form-error">{errors.root.message}</p>}

      <button type="submit" disabled={isLoading}>
        {isLoading ? t("upload.submitting") : t("upload.submit")}
      </button>
    </form>
  );
}
