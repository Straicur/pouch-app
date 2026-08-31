import type { ReactNode } from "react";
import { useTranslation } from "react-i18next";
import { Navigate } from "react-router-dom";
import { useWhoAmIQuery } from "../store/api/sessionApi";

interface ProtectedRouteProps {
  children: ReactNode;
}

// Wraps a whole area (UserLayout/AdminLayout) in one session check instead
// of every page under it re-deriving "am I even logged in" — HomePage still
// does its own inline check (it's the one place a *stale* session should
// render briefly before redirecting, not bounce immediately), but nothing
// under /user or /admin should be reachable without a session at all.
// AdminLayout's own admin-only (403) check still runs separately, after
// this — being logged in and being an admin are two different questions.
export function ProtectedRoute({ children }: ProtectedRouteProps) {
  const { t } = useTranslation();
  const { isLoading, isFetching, isError } = useWhoAmIQuery();

  if (isLoading || isFetching) {
    return <p>{t("common.loading")}</p>;
  }

  if (isError) {
    return <Navigate to="/login" replace />;
  }

  return <>{children}</>;
}
