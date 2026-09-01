import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { z } from "zod";
import { ExceptionUuid, isApiError } from "../../../../libs/apiError";
import i18n from "../../../../libs/i18n";
import type { TagResource } from "../../../../store/api/tagApi";
import { useCreateTagMutation, useRenameTagMutation } from "../../../../store/api/tagApi";
import { Button } from "../../../../ui/catalyst/button";
import { DialogActions, DialogBody } from "../../../../ui/catalyst/dialog";
import { ErrorMessage, Field, Label } from "../../../../ui/catalyst/form/fieldset";
import { Input } from "../../../../ui/catalyst/form/input";

const tagFormSchema = z.object({
  name: z.string().min(1, i18n.t("validation.tagNameRequired")).max(50, i18n.t("validation.maxLength50")),
});

type TagFormValues = z.infer<typeof tagFormSchema>;

interface TagFormProps {
  // Present for a rename, absent for a create — same component either way,
  // like CategoryForm's parentId prop picks its mode.
  tag?: TagResource;
  onSuccess: () => void;
}

export function TagForm({ tag, onSuccess }: TagFormProps) {
  const { t } = useTranslation();
  const [createTag, { isLoading: isCreating }] = useCreateTagMutation();
  const [renameTag, { isLoading: isRenaming }] = useRenameTagMutation();
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<TagFormValues>({ resolver: zodResolver(tagFormSchema), defaultValues: { name: tag?.name ?? "" } });

  const isLoading = isCreating || isRenaming;

  const onSubmit = async (values: TagFormValues) => {
    try {
      if (undefined !== tag) {
        await renameTag({ id: tag.id, name: values.name }).unwrap();
      } else {
        await createTag(values.name).unwrap();
      }
      onSuccess();
    } catch (error) {
      const message = isApiError(error, ExceptionUuid.CONFLICT) ? t("tags.nameTakenError") : t("tags.saveError");
      setError("root", { message });
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate>
      <DialogBody>
        <Field>
          <Label>{t("tags.nameLabel")}</Label>
          <Input {...register("name")} autoFocus />
          {undefined !== errors.name && <ErrorMessage>{errors.name.message}</ErrorMessage>}
        </Field>
        {undefined !== errors.root && <ErrorMessage>{errors.root.message}</ErrorMessage>}
      </DialogBody>
      <DialogActions>
        <Button type="submit" disabled={isLoading}>
          {undefined !== tag ? t("tags.renameSubmit") : t("tags.createSubmit")}
        </Button>
      </DialogActions>
    </form>
  );
}
