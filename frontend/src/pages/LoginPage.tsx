import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { useNavigate } from "react-router-dom";
import { z } from "zod";
import { useLoginMutation } from "../store/api/authApi";

// Defined at module scope (zod needs the schema before any component/hook
// runs), so — unlike everything else in this file — these two messages stay
// inline instead of going through useTranslation(); see locales/pl.ts's header.
const loginSchema = z.object({
  email: z.email("Nieprawidłowy adres e-mail"),
  password: z.string().min(1, "Podaj hasło"),
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
    <main className="login-page">
      <form className="login-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        <h1>{t("auth.loginTitle")}</h1>

        <label htmlFor="email">{t("auth.emailLabel")}</label>
        <input id="email" type="email" autoComplete="username" {...register("email")} />
        {undefined !== errors.email && <p className="field-error">{errors.email.message}</p>}

        <label htmlFor="password">{t("auth.passwordLabel")}</label>
        <input id="password" type="password" autoComplete="current-password" {...register("password")} />
        {undefined !== errors.password && <p className="field-error">{errors.password.message}</p>}

        {undefined !== errors.root && <p className="form-error">{errors.root.message}</p>}

        <button type="submit" disabled={isLoading}>
          {isLoading ? t("auth.loginButtonLoading") : t("auth.loginButton")}
        </button>
      </form>
    </main>
  );
}
