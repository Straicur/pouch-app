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

// Część 13: jedna, identyczna nawigacja dla /user/* i /admin/* — dawniej
// UserLayout i AdminLayout renderowały każdy swój własny <Sidebar> (usera
// widział tylko Itemy/Kategorie, admina tylko podstrony admina), więc kliknięcie
// w dowolny moduł admina "gubiło" dostęp do Itemów/Kategorii i odwrotnie. Ten
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
          <SidebarItem href="/user/favorites" current={pathname.startsWith("/user/favorites")}>
            <SidebarLabel>{t("nav.favorites")}</SidebarLabel>
          </SidebarItem>
          <SidebarItem href="/user/categories" current={pathname.startsWith("/user/categories")}>
            <SidebarLabel>{t("nav.categories")}</SidebarLabel>
          </SidebarItem>
        </SidebarSection>

        {/* "Panel Admina" jest samym opisem (nieklikalny), nie linkiem —
            kreska pod nim to SidebarDivider, wyglądający jak SidebarHeader'owy
            border-b widoczny na górze sidebaru. */}
        {true === data?.isAdmin && (
          <SidebarSection>
            {/* Część 14 post-review fix: domyślny SidebarHeading (text-xs) był
                ledwo czytelny jako etykieta całej sekcji — powiększony tu, nie
                w samym komponencie (jedyne miejsce, gdzie jest dziś używany,
                ale inne przyszłe użycia mogą chcieć oryginalnego rozmiaru). */}
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
        <SidebarItem onClick={handleLogout}>
          <SidebarLabel>{t("auth.logoutButton")}</SidebarLabel>
        </SidebarItem>
      </SidebarFooter>
    </Sidebar>
  );
}
