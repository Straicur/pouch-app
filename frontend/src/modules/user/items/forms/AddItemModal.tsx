import { zodResolver } from "@hookform/resolvers/zod";
import { type ChangeEvent, useEffect, useRef, useState } from "react";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { z } from "zod";
import { getApiErrorBody } from "../../../../libs/apiError";
import i18n from "../../../../libs/i18n";
import { useListCategoriesQuery } from "../../../../store/api/categoryApi";
import {
  useCreateFileMutation,
  useCreateNoteMutation,
  useCreatePhotoMutation,
  useCreateUrlMutation,
} from "../../../../store/api/itemApi";
import { Button } from "../../../../ui/catalyst/button";
import { Dialog, DialogActions, DialogBody, DialogTitle } from "../../../../ui/catalyst/dialog";
import { ErrorMessage, Field, Label } from "../../../../ui/catalyst/form/fieldset";
import { Input } from "../../../../ui/catalyst/form/input";
import { Select } from "../../../../ui/catalyst/form/select";
import { Textarea } from "../../../../ui/catalyst/form/textarea";
import { TagsInput } from "../view/TagsInput";
import { LifecycleFieldsInput, lifecycleFieldsSchema, toLifecyclePayload } from "./lifecycleFields";

type ItemKind = "file" | "photo" | "url" | "note";

function requireCustomExpiresAt(data: { lifecycleMode?: string; customExpiresAt?: string }, ctx: z.RefinementCtx) {
  if ("custom" === data.lifecycleMode && (undefined === data.customExpiresAt || "" === data.customExpiresAt)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ["customExpiresAt"],
      message: i18n.t("validation.expiresAtRequired"),
    });
  }
}

// Jeden modal zamiast dwóch osobnych, zawsze widocznych formularzy
// (NoteForm/FileUploadForm) — przełącznik typu na górze pokazuje tylko pola
// właściwe dla wybranego typu. Domyślny wybór lifecycle ("default") to teraz
// "przechowuj zawsze" (patrz lifecycleFields.tsx), nie backendowy fallback
// 1 dnia.
const noteSchema = z
  .object({
    categoryId: z.coerce.number().int().positive(i18n.t("validation.selectCategory")),
    name: z.string().max(255, i18n.t("validation.maxLength255")).optional(),
    content: z.string().min(1, i18n.t("validation.noteContentRequired")),
    ...lifecycleFieldsSchema,
  })
  .superRefine(requireCustomExpiresAt);

const fileSchema = z
  .object({
    categoryId: z.coerce.number().int().positive(i18n.t("validation.selectCategory")),
    name: z.string().max(255, i18n.t("validation.maxLength255")).optional(),
    content: z.string().optional(),
    ...lifecycleFieldsSchema,
  })
  .superRefine(requireCustomExpiresAt);

const fileFieldSchema = z.instanceof(File, { message: i18n.t("validation.selectFile") });

// No `content` field — ItemService::createPhoto() doesn't take one (see
// CreatePhotoRequest's own comment on why).
const photoSchema = z
  .object({
    categoryId: z.coerce.number().int().positive(i18n.t("validation.selectCategory")),
    name: z.string().max(255, i18n.t("validation.maxLength255")).optional(),
    ...lifecycleFieldsSchema,
  })
  .superRefine(requireCustomExpiresAt);

const urlSchema = z
  .object({
    categoryId: z.coerce.number().int().positive(i18n.t("validation.selectCategory")),
    url: z
      .string()
      .min(1, i18n.t("validation.urlRequired"))
      .url(i18n.t("validation.invalidUrl"))
      .max(2048, i18n.t("validation.maxLength255")),
    name: z.string().max(255, i18n.t("validation.maxLength255")).optional(),
    ...lifecycleFieldsSchema,
  })
  .superRefine(requireCustomExpiresAt);

type NoteFormInput = z.input<typeof noteSchema>;
type NoteFormValues = z.output<typeof noteSchema>;
type FileFormInput = z.input<typeof fileSchema>;
type FileFormValues = z.output<typeof fileSchema>;
type PhotoFormInput = z.input<typeof photoSchema>;
type PhotoFormValues = z.output<typeof photoSchema>;
type UrlFormInput = z.input<typeof urlSchema>;
type UrlFormValues = z.output<typeof urlSchema>;

interface AddItemModalProps {
  open: boolean;
  onClose: () => void;
}

function useCategoryOptions() {
  const { data: categories } = useListCategoriesQuery();

  return categories ?? [];
}

interface NoteFieldsProps {
  onDone: () => void;
}

function NoteFields({ onDone }: NoteFieldsProps) {
  const { t } = useTranslation();
  const categories = useCategoryOptions();
  const [createNote, { isLoading }] = useCreateNoteMutation();
  const [tags, setTags] = useState<string[]>([]);
  const {
    register,
    handleSubmit,
    setValue,
    setError,
    watch,
    formState: { errors },
  } = useForm<NoteFormInput, unknown, NoteFormValues>({
    resolver: zodResolver(noteSchema),
    defaultValues: { name: "", content: "", lifecycleMode: "default" },
  });
  const lifecycleMode = watch("lifecycleMode") ?? "default";

  useEffect(() => {
    if (categories.length > 0) {
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
        tags,
        ...toLifecyclePayload(values),
      }).unwrap();
      onDone();
    } catch {
      setError("root", { message: t("notes.createError") });
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate>
      <DialogBody>
        <div className="flex flex-col gap-4">
          <Field>
            <Label>{t("notes.categoryLabel")}</Label>
            <Select {...register("categoryId", { valueAsNumber: true })}>
              {categories.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.name}
                </option>
              ))}
            </Select>
            {undefined !== errors.categoryId && <ErrorMessage>{errors.categoryId.message}</ErrorMessage>}
          </Field>

          <Field>
            <Label>{t("notes.nameLabel")}</Label>
            <Input {...register("name")} />
            {undefined !== errors.name && <ErrorMessage>{errors.name.message}</ErrorMessage>}
          </Field>

          <Field>
            <Label>{t("notes.contentLabel")}</Label>
            <Textarea rows={6} {...register("content")} />
            {undefined !== errors.content && <ErrorMessage>{errors.content.message}</ErrorMessage>}
          </Field>

          <Field>
            <Label>{t("tags.editTags")}</Label>
            <TagsInput value={tags} onChange={setTags} />
          </Field>

          <LifecycleFieldsInput idPrefix="add-item-note" register={register} errors={errors} mode={lifecycleMode} />

          {undefined !== errors.root && <ErrorMessage>{errors.root.message}</ErrorMessage>}
        </div>
      </DialogBody>
      <DialogActions>
        <Button type="submit" disabled={isLoading}>
          {isLoading ? t("notes.submitting") : t("notes.submit")}
        </Button>
      </DialogActions>
    </form>
  );
}

interface FileFieldsProps {
  onDone: () => void;
}

function FileFields({ onDone }: FileFieldsProps) {
  const { t } = useTranslation();
  const categories = useCategoryOptions();
  const [createFile, { isLoading }] = useCreateFileMutation();
  const [file, setFile] = useState<File | null>(null);
  const [fileError, setFileError] = useState<string | null>(null);
  const [tags, setTags] = useState<string[]>([]);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const {
    register,
    handleSubmit,
    setValue,
    setError,
    watch,
    formState: { errors },
  } = useForm<FileFormInput, unknown, FileFormValues>({
    resolver: zodResolver(fileSchema),
    defaultValues: { lifecycleMode: "default" },
  });
  const lifecycleMode = watch("lifecycleMode") ?? "default";

  useEffect(() => {
    if (categories.length > 0) {
      setValue("categoryId", categories[0].id);
    }
  }, [categories, setValue]);

  const handleFileChange = (event: ChangeEvent<HTMLInputElement>) => {
    setFile(event.target.files?.[0] ?? null);
    setFileError(null);
  };

  const onSubmit = async (values: FileFormValues) => {
    const parsedFile = fileFieldSchema.safeParse(file);
    if (!parsedFile.success) {
      setFileError(parsedFile.error.issues[0]?.message ?? t("upload.createError"));
      return;
    }

    setFileError(null);

    try {
      const name = values.name?.trim();
      const content = values.content?.trim();
      await createFile({
        categoryId: values.categoryId,
        file: parsedFile.data,
        name: "" === name ? undefined : name,
        content: "" === content ? undefined : content,
        tags,
        ...toLifecyclePayload(values),
      }).unwrap();
      if (null !== fileInputRef.current) {
        fileInputRef.current.value = "";
      }
      onDone();
    } catch (submitError) {
      const detail = getApiErrorBody(submitError)?.detail;
      setError("root", { message: detail ?? t("upload.createError") });
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate>
      <DialogBody>
        <div className="flex flex-col gap-4">
          <Field>
            <Label>{t("upload.categoryLabel")}</Label>
            <Select {...register("categoryId", { valueAsNumber: true })}>
              {categories.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.name}
                </option>
              ))}
            </Select>
            {undefined !== errors.categoryId && <ErrorMessage>{errors.categoryId.message}</ErrorMessage>}
          </Field>

          <Field>
            <Label>{t("upload.fileLabel")}</Label>
            {/* Natywny <input type="file"> ukryty — widoczny jest tylko
                przycisk, klika w niego programowo (fileInputRef). */}
            <div className="flex items-center gap-3">
              <Button type="button" variant="outline" size="small" onClick={() => fileInputRef.current?.click()}>
                {t("upload.chooseFileButton")}
              </Button>
              <input type="file" ref={fileInputRef} onChange={handleFileChange} className="hidden" />
              {null !== file ? (
                <span className="truncate text-sm text-zinc-700 dark:text-zinc-300">{file.name}</span>
              ) : (
                <span className="text-xs text-red-600 dark:text-red-400">{t("upload.noFileChosen")}</span>
              )}
            </div>
            {null !== fileError && <ErrorMessage>{fileError}</ErrorMessage>}
          </Field>

          <Field>
            <Label>{t("upload.nameLabel")}</Label>
            <Input {...register("name")} />
            {undefined !== errors.name && <ErrorMessage>{errors.name.message}</ErrorMessage>}
          </Field>

          <Field>
            <Label>{t("upload.contentLabel")}</Label>
            <Textarea rows={4} {...register("content")} />
            {undefined !== errors.content && <ErrorMessage>{errors.content.message}</ErrorMessage>}
          </Field>

          <Field>
            <Label>{t("tags.editTags")}</Label>
            <TagsInput value={tags} onChange={setTags} />
          </Field>

          <LifecycleFieldsInput idPrefix="add-item-file" register={register} errors={errors} mode={lifecycleMode} />

          {undefined !== errors.root && <ErrorMessage>{errors.root.message}</ErrorMessage>}
        </div>
      </DialogBody>
      <DialogActions>
        <Button type="submit" disabled={isLoading}>
          {isLoading ? t("upload.submitting") : t("upload.submit")}
        </Button>
      </DialogActions>
    </form>
  );
}

interface PhotoFieldsProps {
  onDone: () => void;
}

// PRODUCT.md: "dodawanie zdjęcia wprost z aparatu" — `capture="environment"`
// hints mobile browsers to open the camera directly instead of just a file
// picker (desktop browsers that don't support it just ignore it, falling
// back to a normal file picker, so this doesn't need its own branch).
function PhotoFields({ onDone }: PhotoFieldsProps) {
  const { t } = useTranslation();
  const categories = useCategoryOptions();
  const [createPhoto, { isLoading }] = useCreatePhotoMutation();
  const [file, setFile] = useState<File | null>(null);
  const [fileError, setFileError] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const {
    register,
    handleSubmit,
    setValue,
    setError,
    watch,
    formState: { errors },
  } = useForm<PhotoFormInput, unknown, PhotoFormValues>({
    resolver: zodResolver(photoSchema),
    defaultValues: { lifecycleMode: "default" },
  });
  const lifecycleMode = watch("lifecycleMode") ?? "default";

  useEffect(() => {
    if (categories.length > 0) {
      setValue("categoryId", categories[0].id);
    }
  }, [categories, setValue]);

  const handleFileChange = (event: ChangeEvent<HTMLInputElement>) => {
    setFile(event.target.files?.[0] ?? null);
    setFileError(null);
  };

  const onSubmit = async (values: PhotoFormValues) => {
    const parsedFile = fileFieldSchema.safeParse(file);
    if (!parsedFile.success) {
      setFileError(parsedFile.error.issues[0]?.message ?? t("upload.createPhotoError"));
      return;
    }

    setFileError(null);

    try {
      const name = values.name?.trim();
      await createPhoto({
        categoryId: values.categoryId,
        file: parsedFile.data,
        name: "" === name ? undefined : name,
        ...toLifecyclePayload(values),
      }).unwrap();
      if (null !== fileInputRef.current) {
        fileInputRef.current.value = "";
      }
      onDone();
    } catch (submitError) {
      const detail = getApiErrorBody(submitError)?.detail;
      setError("root", { message: detail ?? t("upload.createPhotoError") });
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate>
      <DialogBody>
        <div className="flex flex-col gap-4">
          <Field>
            <Label>{t("upload.categoryLabel")}</Label>
            <Select {...register("categoryId", { valueAsNumber: true })}>
              {categories.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.name}
                </option>
              ))}
            </Select>
            {undefined !== errors.categoryId && <ErrorMessage>{errors.categoryId.message}</ErrorMessage>}
          </Field>

          <Field>
            <Label>{t("upload.fileLabel")}</Label>
            <div className="flex items-center gap-3">
              <Button type="button" variant="outline" size="small" onClick={() => fileInputRef.current?.click()}>
                {t("upload.choosePhotoButton")}
              </Button>
              <input
                type="file"
                accept="image/*"
                capture="environment"
                ref={fileInputRef}
                onChange={handleFileChange}
                className="hidden"
              />
              {null !== file ? (
                <span className="truncate text-sm text-zinc-700 dark:text-zinc-300">{file.name}</span>
              ) : (
                <span className="text-xs text-red-600 dark:text-red-400">{t("upload.noFileChosen")}</span>
              )}
            </div>
            {null !== fileError && <ErrorMessage>{fileError}</ErrorMessage>}
          </Field>

          <Field>
            <Label>{t("upload.nameLabel")}</Label>
            <Input {...register("name")} />
            {undefined !== errors.name && <ErrorMessage>{errors.name.message}</ErrorMessage>}
          </Field>

          <LifecycleFieldsInput idPrefix="add-item-photo" register={register} errors={errors} mode={lifecycleMode} />

          {undefined !== errors.root && <ErrorMessage>{errors.root.message}</ErrorMessage>}
        </div>
      </DialogBody>
      <DialogActions>
        <Button type="submit" disabled={isLoading}>
          {isLoading ? t("upload.submitting") : t("upload.submitPhoto")}
        </Button>
      </DialogActions>
    </form>
  );
}

interface UrlFieldsProps {
  onDone: () => void;
}

function UrlFields({ onDone }: UrlFieldsProps) {
  const { t } = useTranslation();
  const categories = useCategoryOptions();
  const [createUrl, { isLoading }] = useCreateUrlMutation();
  const {
    register,
    handleSubmit,
    setValue,
    setError,
    watch,
    formState: { errors },
  } = useForm<UrlFormInput, unknown, UrlFormValues>({
    resolver: zodResolver(urlSchema),
    defaultValues: { url: "", lifecycleMode: "default" },
  });
  const lifecycleMode = watch("lifecycleMode") ?? "default";

  useEffect(() => {
    if (categories.length > 0) {
      setValue("categoryId", categories[0].id);
    }
  }, [categories, setValue]);

  const onSubmit = async (values: UrlFormValues) => {
    try {
      const name = values.name?.trim();
      await createUrl({
        categoryId: values.categoryId,
        url: values.url,
        name: "" === name ? undefined : name,
        ...toLifecyclePayload(values),
      }).unwrap();
      onDone();
    } catch (submitError) {
      const detail = getApiErrorBody(submitError)?.detail;
      setError("root", { message: detail ?? t("urlItem.createError") });
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate>
      <DialogBody>
        <div className="flex flex-col gap-4">
          <Field>
            <Label>{t("upload.categoryLabel")}</Label>
            <Select {...register("categoryId", { valueAsNumber: true })}>
              {categories.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.name}
                </option>
              ))}
            </Select>
            {undefined !== errors.categoryId && <ErrorMessage>{errors.categoryId.message}</ErrorMessage>}
          </Field>

          <Field>
            <Label>{t("urlItem.urlLabel")}</Label>
            <Input type="url" placeholder={t("urlItem.urlPlaceholder")} {...register("url")} />
            {undefined !== errors.url && <ErrorMessage>{errors.url.message}</ErrorMessage>}
          </Field>

          <Field>
            <Label>{t("urlItem.nameLabel")}</Label>
            <Input {...register("name")} />
            {undefined !== errors.name && <ErrorMessage>{errors.name.message}</ErrorMessage>}
          </Field>

          <LifecycleFieldsInput idPrefix="add-item-url" register={register} errors={errors} mode={lifecycleMode} />

          {undefined !== errors.root && <ErrorMessage>{errors.root.message}</ErrorMessage>}
        </div>
      </DialogBody>
      <DialogActions>
        <Button type="submit" disabled={isLoading}>
          {isLoading ? t("urlItem.submitting") : t("urlItem.submit")}
        </Button>
      </DialogActions>
    </form>
  );
}

export function AddItemModal({ open, onClose }: AddItemModalProps) {
  const { t } = useTranslation();
  const [kind, setKind] = useState<ItemKind>("file");

  return (
    <Dialog open={open} onClose={onClose}>
      <DialogTitle>{t("items.addItemTitle")}</DialogTitle>
      <DialogBody>
        <Field>
          <Label>{t("items.addItemTypeLabel")}</Label>
          <Select value={kind} onChange={(event) => setKind(event.target.value as ItemKind)}>
            <option value="file">{t("items.type.file")}</option>
            <option value="photo">{t("items.type.photo")}</option>
            <option value="url">{t("items.type.url")}</option>
            <option value="note">{t("items.type.note")}</option>
          </Select>
        </Field>
      </DialogBody>
      {/* Keyed on open+kind so a form resets on every re-open instead of
          carrying over a previous submit's typed values — Dialog itself
          stays mounted (its own `open` prop drives the close transition). */}
      {"file" === kind && <FileFields key={`file-${open}`} onDone={onClose} />}
      {"photo" === kind && <PhotoFields key={`photo-${open}`} onDone={onClose} />}
      {"url" === kind && <UrlFields key={`url-${open}`} onDone={onClose} />}
      {"note" === kind && <NoteFields key={`note-${open}`} onDone={onClose} />}
    </Dialog>
  );
}
