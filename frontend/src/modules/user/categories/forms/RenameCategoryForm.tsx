import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { z } from "zod";
import i18n from "../../../../libs/i18n";
import type { Category } from "../../../../store/api/categoryApi";
import { useRenameCategoryMutation } from "../../../../store/api/categoryApi";
import { Button } from "../../../../ui/catalyst/button";
import { DialogActions, DialogBody } from "../../../../ui/catalyst/dialog";
import { ErrorMessage, Field, Label } from "../../../../ui/catalyst/form/fieldset";
import { Input } from "../../../../ui/catalyst/form/input";

const renameCategoryFormSchema = z.object({
  name: z.string().min(1, i18n.t("validation.categoryNameRequired")).max(255, i18n.t("validation.maxLength255")),
});

type RenameCategoryFormValues = z.infer<typeof renameCategoryFormSchema>;

interface RenameCategoryFormProps {
  category: Category;
  onSuccess: () => void;
}

export function RenameCategoryForm({ category, onSuccess }: RenameCategoryFormProps) {
  const { t } = useTranslation();
  const [renameCategory, { isLoading }] = useRenameCategoryMutation();
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<RenameCategoryFormValues>({
    resolver: zodResolver(renameCategoryFormSchema),
    defaultValues: { name: category.name },
  });

  const onSubmit = async (values: RenameCategoryFormValues) => {
    try {
      await renameCategory({ id: category.id, name: values.name }).unwrap();
      onSuccess();
    } catch {
      setError("root", { message: t("categories.renameError") });
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
          {isLoading ? t("categories.renaming") : t("categories.renameSubmit")}
        </Button>
      </DialogActions>
    </form>
  );
}
