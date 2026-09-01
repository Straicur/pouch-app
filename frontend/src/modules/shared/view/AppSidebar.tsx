import { useTranslation } from "react-i18next";
import { useLocation, useNavigate } from "react-router-dom";
import { toastUtil } from "../../../libs/toastUtil";
import { useLogoutMutation } from "../../../store/api/authApi";
import { useWhoAmIQuery } from "../../../store/api/sessionApi";
import {
  Sidebar,
  SidebarBody,
  SidebarDivider,
  SidebarFooter,
  SidebarHeader,
  SidebarHeading,
  SidebarItem,
  SidebarLabel,
  SidebarSection,
} from "../../../ui/catalyst/sidebar";
import { ADMIN_PAGES } from "../adminPages";
import { ThemeSwitch } from "./ThemeSwitch";

// Jedna, identyczna nawigacja dla /user/* i /admin/* — bez tego kliknięcie w
// dowolny moduł admina "gubiłoby" dostęp do Itemów/Kategorii i odwrotnie. Ten
// komponent jest samowystarczalny (własne useLocation/useWhoAmIQuery/
// useLogoutMutation) — oba layouty tylko go renderują jako `sidebar` prop
// swojego SidebarLayoutu, bez przekazywania żadnych propsów.
export function AppSidebar() {
  const { t } = useTranslation();
  const { pathname } = useLocation();
  const navigate = useNavigate();
  const { data } = useWhoAmIQuery();
  const [logout] = useLogoutMutation();

  // .unwrap() matters here — the bare trigger's Promise resolves even on a
  // failed request, so without it a failed logout still navigated to /login
  // looking like a success while the server-side session survived.
  const handleLogout = async () => {
    try {
      await logout().unwrap();
      await navigate("/login", { replace: true });
    } catch {
      toastUtil.showToast(t("auth.logoutError"), "error");
    }
  };

  return (
    <Sidebar>
      <SidebarHeader>
        <SidebarLabel>{t("common.appName")}</SidebarLabel>
      </SidebarHeader>
      <SidebarBody>
        <SidebarSection>
          <SidebarItem href="/user/items" current={pathname.startsWith("/user/items")}>
            <SidebarLabel>{t("nav.items")}</SidebarLabel>
          </SidebarItem>
          <SidebarItem href="/user/recent" current={pathname.startsWith("/user/recent")}>
            <SidebarLabel>{t("nav.recent")}</SidebarLabel>
          </SidebarItem>
          <SidebarItem href="/user/favorites" current={pathname.startsWith("/user/favorites")}>
            <SidebarLabel>{t("nav.favorites")}</SidebarLabel>
          </SidebarItem>
          <SidebarItem href="/user/categories" current={pathname.startsWith("/user/categories")}>
            <SidebarLabel>{t("nav.categories")}</SidebarLabel>
          </SidebarItem>
          <SidebarItem href="/user/tags" current={pathname.startsWith("/user/tags")}>
            <SidebarLabel>{t("nav.tags")}</SidebarLabel>
          </SidebarItem>
          <SidebarItem href="/user/trash" current={pathname.startsWith("/user/trash")}>
            <SidebarLabel>{t("nav.trash")}</SidebarLabel>
          </SidebarItem>
        </SidebarSection>

        {/* "Panel Admina" jest samym opisem (nieklikalny), nie linkiem —
            kreska pod nim to SidebarDivider, wyglądający jak SidebarHeader'owy
            border-b widoczny na górze sidebaru. */}
        {true === data?.isAdmin && (
          <SidebarSection>
            {/* Domyślny SidebarHeading (text-xs) jest ledwo czytelny jako etykieta
                całej sekcji — powiększony tu, nie w samym komponencie (jedyne
                miejsce, gdzie jest dziś używany, ale inne przyszłe użycia mogą
                chcieć oryginalnego rozmiaru). */}
            <SidebarHeading className="text-sm font-semibold text-zinc-950 dark:text-white">
              {t("nav.adminArea")}
            </SidebarHeading>
            <SidebarDivider />
            {ADMIN_PAGES.map(({ path, labelKey }) => (
              <SidebarItem key={path} href={path} current={pathname.startsWith(path)}>
                <SidebarLabel>{t(labelKey)}</SidebarLabel>
              </SidebarItem>
            ))}
          </SidebarSection>
        )}
      </SidebarBody>
      <SidebarFooter>
        <ThemeSwitch />
        <SidebarItem href="/user/settings" current={pathname.startsWith("/user/settings")}>
          <SidebarLabel>{t("nav.settings")}</SidebarLabel>
        </SidebarItem>
        <SidebarItem onClick={handleLogout}>
          <SidebarLabel>{t("auth.logoutButton")}</SidebarLabel>
        </SidebarItem>
      </SidebarFooter>
    </Sidebar>
  );
}
