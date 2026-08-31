import { useTranslation } from "react-i18next";

interface LoadingIndicatorProps {
  className?: string;
}

// Replaces the old bare <p>{t("common.loading")}</p> used across
// ItemsPage/FavoritesPage/CategoriesPage/AdminLayout/ItemDetailsModal — too
// small/left-aligned to read as "the page is doing something" rather than
// just more body text.
export function LoadingIndicator({ className }: LoadingIndicatorProps) {
  const { t } = useTranslation();

  return (
    <div className={`flex flex-col items-center justify-center gap-3 py-16 ${className ?? ""}`}>
      <svg
        className="size-10 animate-spin text-zinc-400 dark:text-zinc-500"
        viewBox="0 0 24 24"
        fill="none"
        aria-hidden="true"
      >
        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z" />
      </svg>
      <span className="text-sm text-zinc-500 dark:text-zinc-400">{t("common.loading")}</span>
    </div>
  );
}
