import { useEffect } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useLogoutMutation } from "../store/api/authApi";
import { useWhoAmIQuery } from "../store/api/sessionApi";

export function HomePage() {
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
    await logout();
    await navigate("/login", { replace: true });
  };

  if (isLoading || 401 === status) {
    return null;
  }

  return (
    <main className="home-page">
      <h1>Pouch</h1>
      {undefined !== data && <p>Zalogowano jako: {data.email}</p>}
      {undefined !== error && 401 !== status && <p className="form-error">Nie udało się połączyć z backendem.</p>}
      <Link to="/items">Zobacz itemy</Link>
      <button type="button" onClick={handleLogout}>
        Wyloguj się
      </button>
    </main>
  );
}
