// Współdzielone przez AdminLayout (nawigacja wewnątrz /admin) i UserLayout
// (skrót do panelu admina wprost z sidebaru usera, żeby nie trzeba było
// najpierw wchodzić na /admin) — jedna lista zamiast dwóch kopii.
export const ADMIN_PAGES: { path: string; labelKey: string }[] = [
  { path: "/admin/storage", labelKey: "nav.adminStorage" },
  { path: "/admin/gc", labelKey: "nav.adminGc" },
  { path: "/admin/audit-log", labelKey: "nav.adminAuditLog" },
  { path: "/admin/expiring", labelKey: "nav.adminExpiring" },
  { path: "/admin/backup", labelKey: "nav.adminBackup" },
  { path: "/admin/users", labelKey: "nav.adminUsers" },
  { path: "/admin/technical-break", labelKey: "nav.adminTechnicalBreak" },
];
