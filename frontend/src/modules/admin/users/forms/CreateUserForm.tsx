import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { z } from "zod";
import { getApiErrorBody } from "../../../../libs/apiError";
import i18n from "../../../../libs/i18n";
import { useCreateUserMutation, useListPouchesQuery } from "../../../../store/api/userApi";
import type { UserRole } from "../../../../store/types/user";
import { Button } from "../../../../ui/catalyst/button";
import { DialogActions, DialogBody } from "../../../../ui/catalyst/dialog";
import { ErrorMessage, Field, Label } from "../../../../ui/catalyst/form/fieldset";
import { Input } from "../../../../ui/catalyst/form/input";
import { Select } from "../../../../ui/catalyst/form/select";
import { roleLabelKey, USER_ROLES } from "../roleLabels";

type PouchChoice = "existing" | "new";

const createUserSchema = z
  .object({
    email: z.string().email(i18n.t("validation.invalidEmail")),
    role: z.enum(["ROLE_GUEST", "ROLE_USER", "ROLE_ADMIN"]),
    pouchChoice: z.enum(["existing", "new"]),
    pouchId: z.coerce.number().int().positive().optional(),
    newPouchName: z.string().max(255, i18n.t("validation.maxLength255")).optional(),
  })
  .superRefine((data, ctx) => {
    if ("new" === data.pouchChoice && (undefined === data.newPouchName || "" === data.newPouchName.trim())) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["newPouchName"],
        message: i18n.t("admin.users.pouchNameRequired"),
      });
    }
  });

type CreateUserFormInput = z.input<typeof createUserSchema>;
type CreateUserFormValues = z.output<typeof createUserSchema>;

interface CreateUserFormProps {
  onSuccess: (temporaryPassword: string) => void;
}

export function CreateUserForm({ onSuccess }: CreateUserFormProps) {
  const { t } = useTranslation();
  const { data: pouches } = useListPouchesQuery();
  const [createUser, { isLoading }] = useCreateUserMutation();
  const [pouchChoice, setPouchChoice] = useState<PouchChoice>("existing");
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<CreateUserFormInput, unknown, CreateUserFormValues>({
    resolver: zodResolver(createUserSchema),
    defaultValues: { role: "ROLE_USER", pouchChoice: "existing" },
  });

  const onSubmit = async (values: CreateUserFormValues) => {
    try {
      const result = await createUser({
        email: values.email,
        role: values.role as UserRole,
        pouchId: "existing" === values.pouchChoice ? values.pouchId : undefined,
        newPouchName: "new" === values.pouchChoice ? values.newPouchName?.trim() : undefined,
      }).unwrap();
      onSuccess(result.temporaryPassword);
    } catch (submitError) {
      const detail = getApiErrorBody(submitError)?.detail;
      setError("root", { message: detail ?? t("admin.users.createError") });
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate>
      <DialogBody>
        <div className="flex flex-col gap-4">
          <Field>
            <Label>{t("admin.users.emailLabel")}</Label>
            <Input type="email" {...register("email")} autoFocus />
            {undefined !== errors.email && <ErrorMessage>{errors.email.message}</ErrorMessage>}
          </Field>

          <Field>
            <Label>{t("admin.users.roleLabel")}</Label>
            <Select {...register("role")}>
              {USER_ROLES.map((role) => (
                <option key={role} value={role}>
                  {t(roleLabelKey(role))}
                </option>
              ))}
            </Select>
          </Field>

          <Field>
            <Label>{t("admin.users.pouchChoiceLabel")}</Label>
            <Select
              {...register("pouchChoice")}
              onChange={(event) => setPouchChoice(event.target.value as PouchChoice)}
            >
              <option value="existing">{t("admin.users.pouchChoiceExisting")}</option>
              <option value="new">{t("admin.users.pouchChoiceNew")}</option>
            </Select>
          </Field>

          {"existing" === pouchChoice ? (
            <Field>
              <Label>{t("admin.users.pouchLabel")}</Label>
              <Select {...register("pouchId", { valueAsNumber: true })}>
                {(pouches ?? []).map((pouch) => (
                  <option key={pouch.id} value={pouch.id}>
                    {pouch.name}
                  </option>
                ))}
              </Select>
            </Field>
          ) : (
            <Field>
              <Label>{t("admin.users.newPouchNameLabel")}</Label>
              <Input {...register("newPouchName")} />
              {undefined !== errors.newPouchName && <ErrorMessage>{errors.newPouchName.message}</ErrorMessage>}
            </Field>
          )}

          {undefined !== errors.root && <ErrorMessage>{errors.root.message}</ErrorMessage>}
        </div>
      </DialogBody>
      <DialogActions>
        <Button type="submit" disabled={isLoading}>
          {isLoading ? t("admin.users.creating") : t("admin.users.createSubmit")}
        </Button>
      </DialogActions>
    </form>
  );
}
