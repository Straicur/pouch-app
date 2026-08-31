import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { useNavigate } from "react-router-dom";
import { z } from "zod";
import i18n from "../libs/i18n";
import { useLoginMutation } from "../store/api/authApi";
import { Button } from "../ui/catalyst/button";
import { ErrorMessage, Field, FieldGroup, Fieldset, Label } from "../ui/catalyst/form/fieldset";
import { Input } from "../ui/catalyst/form/input";
import { Heading } from "../ui/catalyst/heading";

// Defined at module scope (zod needs the schema before any component/hook
// runs), so — unlike everything else in this file — these two messages go
// through the imported i18n instance's t() directly instead of the hook
// (see locales/pl.ts's header and FRONTEND.md's "Formularze" section).
const loginSchema = z.object({
  email: z.email(i18n.t("validation.invalidEmail")),
  password: z.string().min(1, i18n.t("validation.passwordRequired")),
});

type LoginFormValues = z.infer<typeof loginSchema>;

export function LoginPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [login, { isLoading }] = useLoginMutation();
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<LoginFormValues>({ resolver: zodResolver(loginSchema) });

  const onSubmit = async (values: LoginFormValues) => {
    try {
      await login(values).unwrap();
      await navigate("/", { replace: true });
    } catch (error) {
      const status = (error as { status?: number })?.status;
      const message = 401 === status ? t("auth.invalidCredentials") : t("auth.genericError");
      setError("root", { message });
    }
  };

  return (
    <main className="flex min-h-[calc(100vh-4rem)] items-center justify-center">
      <form className="w-full max-w-sm" onSubmit={handleSubmit(onSubmit)} noValidate>
        <Heading variant="page" className="mb-6 text-center">
          {t("auth.loginTitle")}
        </Heading>

        <Fieldset>
          <FieldGroup>
            <Field>
              <Label htmlFor="email">{t("auth.emailLabel")}</Label>
              <Input id="email" type="email" autoComplete="username" {...register("email")} />
              {undefined !== errors.email && <ErrorMessage>{errors.email.message}</ErrorMessage>}
            </Field>

            <Field>
              <Label htmlFor="password">{t("auth.passwordLabel")}</Label>
              <Input id="password" type="password" autoComplete="current-password" {...register("password")} />
              {undefined !== errors.password && <ErrorMessage>{errors.password.message}</ErrorMessage>}
            </Field>

            {undefined !== errors.root && <ErrorMessage>{errors.root.message}</ErrorMessage>}

            <Button type="submit" disabled={isLoading} fullWidth>
              {isLoading ? t("auth.loginButtonLoading") : t("auth.loginButton")}
            </Button>
          </FieldGroup>
        </Fieldset>
      </form>
    </main>
  );
}
