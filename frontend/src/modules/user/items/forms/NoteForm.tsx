import { zodResolver } from "@hookform/resolvers/zod";
import { useEffect } from "react";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { z } from "zod";
import i18n from "../../../../libs/i18n";
import { useListCategoriesQuery } from "../../../../store/api/categoryApi";
import { useCreateNoteMutation } from "../../../../store/api/itemApi";
import { LifecycleFieldsInput, lifecycleFieldsSchema, toLifecyclePayload } from "./lifecycleFields";

// Defined at module scope (zod needs the schema before any component/hook
// runs), so — like LoginPage — these messages go through the imported i18n
// instance's t() directly instead of the hook; see locales/pl.ts's header.
const noteFormSchema = z
  .object({
    categoryId: z.coerce.number().int().positive(i18n.t("validation.selectCategory")),
    name: z.string().max(255, i18n.t("validation.maxLength255")).optional(),
    content: z.string().min(1, i18n.t("validation.noteContentRequired")),
    ...lifecycleFieldsSchema,
  })
  .superRefine((data, ctx) => {
    if ("custom" === data.lifecycleMode && (undefined === data.customExpiresAt || "" === data.customExpiresAt)) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["customExpiresAt"],
        message: i18n.t("validation.expiresAtRequired"),
      });
    }
  });

// z.coerce.number()'s input type (unknown, before coercion) differs from its
// output type (number, after) — useForm needs both: the input shape for
// register()/the <select>'s raw value, the output shape for what onSubmit
// actually receives.
type NoteFormInput = z.input<typeof noteFormSchema>;
type NoteFormValues = z.output<typeof noteFormSchema>;

export function NoteForm() {
  const { t } = useTranslation();
  const { data: categories } = useListCategoriesQuery();
  const [createNote, { isLoading }] = useCreateNoteMutation();
  const {
    register,
    handleSubmit,
    reset,
    setValue,
    setError,
    watch,
    formState: { errors },
  } = useForm<NoteFormInput, unknown, NoteFormValues>({
    resolver: zodResolver(noteFormSchema),
    defaultValues: { name: "", content: "", lifecycleMode: "default" },
  });
  const lifecycleMode = watch("lifecycleMode") ?? "default";

  useEffect(() => {
    if (undefined !== categories && categories.length > 0) {
      setValue("categoryId", categories[0].id);
    }
  }, [categories, setValue]);

  const onSubmit = async (values: NoteFormValues) => {
    try {
      const name = values.name?.trim();
      await createNote({
        categoryId: values.categoryId,
        content: values.content,
        name: "" === name ? undefined : name,
        ...toLifecyclePayload(values),
      }).unwrap();
      reset({ categoryId: values.categoryId, name: "", content: "", lifecycleMode: "default" });
    } catch {
      setError("root", { message: t("notes.createError") });
    }
  };

  return (
    <form className="note-form" onSubmit={handleSubmit(onSubmit)} noValidate>
      <h2>{t("notes.addTitle")}</h2>

      <label htmlFor="note-category">{t("notes.categoryLabel")}</label>
      <select id="note-category" {...register("categoryId", { valueAsNumber: true })}>
        {categories?.map((category) => (
          <option key={category.id} value={category.id}>
            {category.name}
          </option>
        ))}
      </select>
      {undefined !== errors.categoryId && <p className="field-error">{errors.categoryId.message}</p>}

      <label htmlFor="note-name">{t("notes.nameLabel")}</label>
      <input id="note-name" type="text" {...register("name")} />
      {undefined !== errors.name && <p className="field-error">{errors.name.message}</p>}

      <label htmlFor="note-content">{t("notes.contentLabel")}</label>
      <textarea id="note-content" rows={6} {...register("content")} />
      {undefined !== errors.content && <p className="field-error">{errors.content.message}</p>}

      <LifecycleFieldsInput idPrefix="note" register={register} errors={errors} mode={lifecycleMode} />

      {undefined !== errors.root && <p className="form-error">{errors.root.message}</p>}

      <button type="submit" disabled={isLoading}>
        {isLoading ? t("notes.submitting") : t("notes.submit")}
      </button>
    </form>
  );
}
