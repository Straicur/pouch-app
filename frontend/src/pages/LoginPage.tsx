import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { useNavigate } from "react-router-dom";
import { z } from "zod";
import { useLoginMutation } from "../store/api/authApi";

const loginSchema = z.object({
  email: z.email("Nieprawidłowy adres e-mail"),
  password: z.string().min(1, "Podaj hasło"),
});

type LoginFormValues = z.infer<typeof loginSchema>;

export function LoginPage() {
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
      const message = 401 === status ? "Nieprawidłowy e-mail lub hasło." : "Coś poszło nie tak. Spróbuj ponownie.";
      setError("root", { message });
    }
  };

  return (
    <main className="login-page">
      <form className="login-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        <h1>Zaloguj się</h1>

        <label htmlFor="email">E-mail</label>
        <input id="email" type="email" autoComplete="username" {...register("email")} />
        {undefined !== errors.email && <p className="field-error">{errors.email.message}</p>}

        <label htmlFor="password">Hasło</label>
        <input id="password" type="password" autoComplete="current-password" {...register("password")} />
        {undefined !== errors.password && <p className="field-error">{errors.password.message}</p>}

        {undefined !== errors.root && <p className="form-error">{errors.root.message}</p>}

        <button type="submit" disabled={isLoading}>
          {isLoading ? "Logowanie…" : "Zaloguj się"}
        </button>
      </form>
    </main>
  );
}
