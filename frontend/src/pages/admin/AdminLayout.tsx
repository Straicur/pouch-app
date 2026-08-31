import { useTranslation } from "react-i18next";
import { NavLink, Outlet, useLocation } from "react-router-dom";
import { ExceptionUuid, isApiError } from "../../libs/apiError";
import { ProtectedRoute } from "../../providers/ProtectedRoute";
import { useGetStorageReportQuery } from "../../store/api/adminApi";
import { Navbar } from "../../ui/catalyst/navbar";
import {
  Sidebar,
  SidebarBody,
  SidebarHeader,
  SidebarItem,
  SidebarLabel,
  SidebarSection,
} from "../../ui/catalyst/sidebar";
import { SidebarLayout } from "../../ui/catalyst/sidebar-layout";

const ADMIN_PAGES: { path: string; labelKey: string }[] = [
  { path: "/admin/storage", labelKey: "nav.adminStorage" },
  { path: "/admin/gc", labelKey: "nav.adminGc" },
  { path: "/admin/audit-log", labelKey: "nav.adminAuditLog" },
  { path: "/admin/expiring", labelKey: "nav.adminExpiring" },
  { path: "/admin/backup", labelKey: "nav.adminBackup" },
];

// Part 10, reworked in Part 11: one admin-only check for the whole /admin
// area instead of every page re-probing — no role is exposed to JS at all
// (see adminApi's own docblock), so this still just renders whatever the
// storage report's 403 means, the same signal every page used individually
// before. StoragePage's own useGetStorageReportQuery() call below hits
// RTK Query's cache for the same query, not a second request.
//
// Split from AdminLayout itself so ProtectedRoute's session check runs
// first: without it, someone with no session at all got a 401 here (not a
// 403), which isForbidden doesn't match — the admin-only check would have
// silently fallen through to rendering the full nav as if authorized.
function AdminLayoutContent() {
  const { t } = useTranslation();
  const { pathname } = useLocation();
  const { error, isLoading } = useGetStorageReportQuery();
  const isForbidden = isApiError(error, ExceptionUuid.FORBIDDEN);

  if (isLoading) {
    return <p>{t("common.loading")}</p>;
  }

  if (isForbidden) {
    return (
      <main className="admin-page">
        <p className="form-error">{t("admin.forbidden")}</p>
        <NavLink to="/">{t("nav.home")}</NavLink>
      </main>
    );
  }

  return (
    <SidebarLayout
      navbar={<Navbar>{t("nav.adminArea")}</Navbar>}
      sidebar={
        <Sidebar>
          <SidebarHeader>
            <SidebarLabel>{t("nav.adminArea")}</SidebarLabel>
          </SidebarHeader>
          <SidebarBody>
            <SidebarSection>
              {ADMIN_PAGES.map(({ path, labelKey }) => (
                <SidebarItem key={path} href={path} current={pathname.startsWith(path)}>
                  <SidebarLabel>{t(labelKey)}</SidebarLabel>
                </SidebarItem>
              ))}
              <SidebarItem href="/" current={"/" === pathname}>
                <SidebarLabel>{t("nav.home")}</SidebarLabel>
              </SidebarItem>
            </SidebarSection>
          </SidebarBody>
        </Sidebar>
      }
    >
      <Outlet />
    </SidebarLayout>
  );
}

export function AdminLayout() {
  return (
    <ProtectedRoute>
      <AdminLayoutContent />
    </ProtectedRoute>
  );
}
