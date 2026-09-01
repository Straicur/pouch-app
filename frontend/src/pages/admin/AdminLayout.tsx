import { useTranslation } from "react-i18next";
import { NavLink, Outlet } from "react-router-dom";
import { ExceptionUuid, isApiError } from "../../libs/apiError";
import { PouchSwitcher } from "../../modules/admin/PouchSwitcher";
import { PouchFilterProvider } from "../../modules/admin/pouchFilter";
import { AppSidebar } from "../../modules/shared/view/AppSidebar";
import { LoadingIndicator } from "../../modules/shared/view/LoadingIndicator";
import { ProtectedRoute } from "../../providers/ProtectedRoute";
import { useGetStorageReportQuery } from "../../store/api/adminApi";
import { Navbar, NavbarSpacer } from "../../ui/catalyst/navbar";
import { SidebarLayout } from "../../ui/catalyst/sidebar-layout";

// One admin-only check for the whole /admin area instead of every page
// re-probing — no role is exposed to JS at all (see adminApi's own
// docblock), so this still just renders whatever the storage report's 403
// means, the same signal every page used individually before. StoragePage's
// own useGetStorageReportQuery() call below hits RTK Query's cache for the
// same query, not a second request.
//
// Split from AdminLayout itself so ProtectedRoute's session check runs
// first: without it, someone with no session at all got a 401 here (not a
// 403), which isForbidden doesn't match — the admin-only check would have
// silently fallen through to rendering the full nav as if authorized.
function AdminLayoutContent() {
  const { t } = useTranslation();
  const { error, isLoading } = useGetStorageReportQuery(undefined);
  const isForbidden = isApiError(error, ExceptionUuid.FORBIDDEN);

  if (isLoading) {
    return <LoadingIndicator />;
  }

  if (isForbidden) {
    return (
      <main className="admin-page">
        <p className="form-error">{t("admin.forbidden")}</p>
        <NavLink to="/">{t("nav.home")}</NavLink>
      </main>
    );
  }

  // Ten sam AppSidebar co UserLayout — jedna, identyczna nawigacja dla obu
  // obszarów, żeby wejście w moduł admina nie "gubiło" Itemów/Kategorii (i
  // odwrotnie). PouchSwitcher w navbarze — wybór pouch obowiązuje całą resztę
  // panelu admina (patrz PouchFilterProvider niżej).
  return (
    <PouchFilterProvider>
      <SidebarLayout
        navbar={
          <Navbar>
            {t("nav.adminArea")}
            <NavbarSpacer />
            <PouchSwitcher />
          </Navbar>
        }
        sidebar={<AppSidebar />}
      >
        <Outlet />
      </SidebarLayout>
    </PouchFilterProvider>
  );
}

export function AdminLayout() {
  return (
    <ProtectedRoute>
      <AdminLayoutContent />
    </ProtectedRoute>
  );
}
