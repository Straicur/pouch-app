import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { z } from "zod";
import i18n from "../../../../libs/i18n";
import { useCreateCategoryMutation } from "../../../../store/api/categoryApi";
import { Button } from "../../../../ui/catalyst/button";
import { DialogActions, DialogBody } from "../../../../ui/catalyst/dialog";
import { ErrorMessage, Field, Label } from "../../../../ui/catalyst/form/fieldset";
import { Input } from "../../../../ui/catalyst/form/input";

// Defined at module scope, like NoteForm — messages go through the imported
// i18n instance's t() directly; see locales/pl.ts's header.
const categoryFormSchema = z.object({
  name: z.string().min(1, i18n.t("validation.categoryNameRequired")).max(255, i18n.t("validation.maxLength255")),
});

type CategoryFormValues = z.infer<typeof categoryFormSchema>;

interface CategoryFormProps {
  // Not a form field — fixed by the caller (null for a root category, a root
  // category's id for a subcategory). Depth (kategoria główna + jedna
  // podkategoria) is enforced structurally this way, not just by validation:
  // there's no field to pick a deeper parent in the first place.
  parentId: number | null;
  onSuccess: () => void;
}

export function CategoryForm({ parentId, onSuccess }: CategoryFormProps) {
  const { t } = useTranslation();
  const [createCategory, { isLoading }] = useCreateCategoryMutation();
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<CategoryFormValues>({ resolver: zodResolver(categoryFormSchema) });

  const onSubmit = async (values: CategoryFormValues) => {
    try {
      await createCategory({ name: values.name, parentId }).unwrap();
      onSuccess();
    } catch {
      setError("root", { message: t("categories.createError") });
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate>
      <DialogBody>
        <Field>
          <Label>{t("categories.nameLabel")}</Label>
          <Input {...register("name")} autoFocus />
          {undefined !== errors.name && <ErrorMessage>{errors.name.message}</ErrorMessage>}
        </Field>
        {undefined !== errors.root && <ErrorMessage>{errors.root.message}</ErrorMessage>}
      </DialogBody>
      <DialogActions>
        <Button type="submit" disabled={isLoading}>
          {isLoading ? t("categories.creating") : t("categories.createSubmit")}
        </Button>
      </DialogActions>
    </form>
  );
}
