import { createBrowserRouter, Navigate } from "react-router-dom";
import { AdminLayout } from "./pages/admin/AdminLayout";
import { AuditLogPage } from "./pages/admin/auditLog/AuditLogPage";
import { BackupPage } from "./pages/admin/backup/BackupPage";
import { ExpiringPage } from "./pages/admin/expiring/ExpiringPage";
import { GcPage } from "./pages/admin/gc/GcPage";
import { StoragePage } from "./pages/admin/storage/StoragePage";
import { HomePage } from "./pages/HomePage";
import { LoginPage } from "./pages/LoginPage";
import { RootLayout } from "./pages/RootLayout";
import { CategoriesPage } from "./pages/user/categories/CategoriesPage";
import { ItemsPage } from "./pages/user/items/ItemsPage";
import { UserLayout } from "./pages/user/UserLayout";

// Part 11: everything used to sit flat under one router — now nested per
// area (User / Admin), and within each area, one route per concrete page
// (UserLayout/AdminLayout own the shared nav, each page owns its own content).
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
          { path: "storage", element: <StoragePage /> },
          { path: "gc", element: <GcPage /> },
          { path: "audit-log", element: <AuditLogPage /> },
          { path: "expiring", element: <ExpiringPage /> },
          { path: "backup", element: <BackupPage /> },
        ],
      },
    ],
  },
]);
