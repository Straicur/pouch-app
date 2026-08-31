import { useTranslation } from "react-i18next";
import { Outlet, useLocation } from "react-router-dom";
import { ProtectedRoute } from "../../providers/ProtectedRoute";
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

// Wraps every module under /user (items, categories — Part 11) in one shared
// nav, the same way AdminLayout does for /admin. Each module keeps owning
// its own page/data; this only owns switching between them (and, via
// ProtectedRoute, being logged in at all). Nav chrome is the ported Catalyst
// SidebarLayout (Część 11's "duży Tailwind" follow-up) — page content itself
// stays on the app's existing plain CSS.
export function UserLayout() {
  const { t } = useTranslation();
  const { pathname } = useLocation();

  return (
    <ProtectedRoute>
      <SidebarLayout
        navbar={<Navbar>{t("nav.userArea")}</Navbar>}
        sidebar={
          <Sidebar>
            <SidebarHeader>
              <SidebarLabel>{t("nav.userArea")}</SidebarLabel>
            </SidebarHeader>
            <SidebarBody>
              <SidebarSection>
                <SidebarItem href="/user/items" current={pathname.startsWith("/user/items")}>
                  <SidebarLabel>{t("nav.items")}</SidebarLabel>
                </SidebarItem>
                <SidebarItem href="/user/categories" current={pathname.startsWith("/user/categories")}>
                  <SidebarLabel>{t("nav.categories")}</SidebarLabel>
                </SidebarItem>
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
    </ProtectedRoute>
  );
}
