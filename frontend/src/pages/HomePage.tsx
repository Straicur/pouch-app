import { useEffect } from "react";
import { useTranslation } from "react-i18next";
import { Link, useNavigate } from "react-router-dom";
import { toastUtil } from "../libs/toastUtil";
import { useLogoutMutation } from "../store/api/authApi";
import { useWhoAmIQuery } from "../store/api/sessionApi";

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
    // .unwrap() matters here — the bare trigger's Promise resolves even on
    // a failed request, so without it a failed logout still navigated to
    // /login looking like a success while the server-side session survived.
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

  return (
    <main className="home-page">
      <h1>{t("common.appName")}</h1>
      {undefined !== data && <p>{t("home.loggedInAs", { email: data.email })}</p>}
      {undefined !== error && 401 !== status && <p className="form-error">{t("home.connectionError")}</p>}
      <Link to="/user">{t("home.userAreaLink")}</Link>
      <Link to="/admin">{t("home.adminLink")}</Link>
      <button type="button" onClick={handleLogout}>
        {t("auth.logoutButton")}
      </button>
    </main>
  );
}
