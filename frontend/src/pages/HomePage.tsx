import { useEffect } from "react";
import { useTranslation } from "react-i18next";
import { Navigate, useNavigate } from "react-router-dom";
import { toastUtil } from "../libs/toastUtil";
import { useLogoutMutation } from "../store/api/authApi";
import { useWhoAmIQuery } from "../store/api/sessionApi";

// Część 13: "/" nie ma już własnej treści — od razu pokazuje to, co
// "/user/items" (życzenie usera: pod "/" ma być widoczne to samo co pod
// "/user/items", nie osobna, okrojona strona). Wciąż jednak nie jest to
// zwykłe <Navigate> wprost w routes.tsx: sesja musi się najpierw rozstrzygnąć
// (401 → /login, chwilowe "nic" podczas ładowania — patrz FRONTEND.md,
// "Layouty i trasy", ostatni akapit — to świadomie inne zachowanie niż
// ProtectedRoute), błąd połączenia z backendem dostaje własny komunikat
// zamiast ślepego przekierowania.
export function HomePage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data, error, isLoading } = useWhoAmIQuery();
  const [logout] = useLogoutMutation();

  const status = (error as { status?: number })?.status;

  useEffect(() => {
    if (401 === status) {
      void navigate("/login", { replace: true });
    }
  }, [status, navigate]);

  const handleLogout = async () => {
    try {
      await logout().unwrap();
      await navigate("/login", { replace: true });
    } catch {
      toastUtil.showToast(t("auth.logoutError"), "error");
    }
  };

  if (isLoading || 401 === status) {
    return null;
  }

  if (undefined !== data) {
    return <Navigate to="/user/items" replace />;
  }

  return (
    <main className="flex flex-col gap-4">
      <p className="text-red-600 dark:text-red-400">{t("home.connectionError")}</p>
      <button
        type="button"
        onClick={handleLogout}
        className="w-fit text-sm text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
      >
        {t("auth.logoutButton")}
      </button>
    </main>
  );
}
