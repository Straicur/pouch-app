import { createBrowserRouter, Navigate } from "react-router-dom";
import { RootLayout } from "./components/RootLayout";
import { AdminLayout } from "./modules/admin/AdminLayout";
import { AuditLogModule } from "./modules/admin/auditLog/AuditLogModule";
import { BackupModule } from "./modules/admin/backup/BackupModule";
import { ExpiringModule } from "./modules/admin/expiring/ExpiringModule";
import { GcModule } from "./modules/admin/gc/GcModule";
import { StorageModule } from "./modules/admin/storage/StorageModule";
import { CategoriesPage } from "./modules/user/categories/CategoriesPage";
import { ItemsPage } from "./modules/user/items/ItemsPage";
import { UserLayout } from "./modules/user/UserLayout";
import { HomePage } from "./pages/HomePage";
import { LoginPage } from "./pages/LoginPage";

// Part 11: everything used to sit flat under one router — now nested per
// area (User / Admin), and within each area, one route per concrete module
// (UserLayout/AdminLayout own the shared nav, each module owns its own page).
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
          { path: "categories", element: <CategoriesPage /> },
        ],
      },
      {
        path: "/admin",
        element: <AdminLayout />,
        children: [
          { index: true, element: <Navigate to="storage" replace /> },
          { path: "storage", element: <StorageModule /> },
          { path: "gc", element: <GcModule /> },
          { path: "audit-log", element: <AuditLogModule /> },
          { path: "expiring", element: <ExpiringModule /> },
          { path: "backup", element: <BackupModule /> },
        ],
      },
    ],
  },
]);
