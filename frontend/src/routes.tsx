import { createBrowserRouter, Navigate } from "react-router-dom";
import { AdminLayout } from "./pages/admin/AdminLayout";
import { AuditLogPage } from "./pages/admin/auditLog/AuditLogPage";
import { BackupPage } from "./pages/admin/backup/BackupPage";
import { ExpiringPage } from "./pages/admin/expiring/ExpiringPage";
import { GcPage } from "./pages/admin/gc/GcPage";
import { StoragePage } from "./pages/admin/storage/StoragePage";
import { UsersPage } from "./pages/admin/users/UsersPage";
import { HomePage } from "./pages/HomePage";
import { LoginPage } from "./pages/LoginPage";
import { RootLayout } from "./pages/RootLayout";
import { CategoriesPage } from "./pages/user/categories/CategoriesPage";
import { FavoritesPage } from "./pages/user/favorites/FavoritesPage";
import { ItemsPage } from "./pages/user/items/ItemsPage";
import { RecentPage } from "./pages/user/recent/RecentPage";
import { SettingsPage } from "./pages/user/settings/SettingsPage";
import { TagsPage } from "./pages/user/tags/TagsPage";
import { UserLayout } from "./pages/user/UserLayout";

// Nested per area (User / Admin), and within each area, one route per
// concrete page (UserLayout/AdminLayout own the shared nav, each page owns
// its own content).
export const router = createBrowserRouter([
  {
    element: <RootLayout />,
    children: [
      { path: "/", element: <HomePage /> },
      { path: "/login", element: <LoginPage /> },
      {
        path: "/user",
        element: <UserLayout />,
        children: [
          { index: true, element: <Navigate to="items" replace /> },
          { path: "items", element: <ItemsPage /> },
          { path: "recent", element: <RecentPage /> },
          { path: "favorites", element: <FavoritesPage /> },
          { path: "categories", element: <CategoriesPage /> },
          { path: "tags", element: <TagsPage /> },
          { path: "settings", element: <SettingsPage /> },
        ],
      },
      {
        path: "/admin",
        element: <AdminLayout />,
        children: [
          { index: true, element: <Navigate to="storage" replace /> },
          { path: "storage", element: <StoragePage /> },
          { path: "gc", element: <GcPage /> },
          { path: "audit-log", element: <AuditLogPage /> },
          { path: "expiring", element: <ExpiringPage /> },
          { path: "backup", element: <BackupPage /> },
          { path: "users", element: <UsersPage /> },
        ],
      },
    ],
  },
]);
