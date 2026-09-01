import { useTranslation } from "react-i18next";
import { Outlet } from "react-router-dom";
import { AppSidebar } from "../../modules/shared/view/AppSidebar";
import { ProtectedRoute } from "../../providers/ProtectedRoute";
import { Navbar } from "../../ui/catalyst/navbar";
import { SidebarLayout } from "../../ui/catalyst/sidebar-layout";

// Wraps every module under /user (items, categories) in one shared nav, the
// same way AdminLayout does for /admin — both render the very same
// AppSidebar (one identical sidebar for both areas, not two separate ones),
// only the navbar title and the <Outlet/> content differ.
export function UserLayout() {
  const { t } = useTranslation();

  return (
    <ProtectedRoute>
      <SidebarLayout navbar={<Navbar>{t("nav.userArea")}</Navbar>} sidebar={<AppSidebar />}>
        <Outlet />
      </SidebarLayout>
    </ProtectedRoute>
  );
}
