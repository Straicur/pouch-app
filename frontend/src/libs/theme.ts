import { logger } from "./logger";

// Tryb jasny/ciemny (Część 13) — sterowany klasą `.dark` na <html> (patrz
// @custom-variant w index.css), nie samym prefers-color-scheme, żeby
// ThemeSwitch mógł nadpisać ustawienie systemowe. Zapisany w localStorage,
// żeby przetrwał odświeżenie/kolejną wizytę.
export type Theme = "light" | "dark";

const STORAGE_KEY = "pouchTheme";

const isTheme = (value: string | null): value is Theme => "light" === value || "dark" === value;

const prefersDark = (): boolean => window.matchMedia("(prefers-color-scheme: dark)").matches;

const apply = (theme: Theme): void => {
  document.documentElement.classList.toggle("dark", "dark" === theme);
};

export const themeUtil = {
  get(): Theme {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (isTheme(stored)) {
        return stored;
      }
    } catch (error) {
      logger.error("Failed to read stored theme:", error);
    }

    return prefersDark() ? "dark" : "light";
  },

  set(theme: Theme): void {
    apply(theme);
    try {
      localStorage.setItem(STORAGE_KEY, theme);
    } catch (error) {
      logger.error("Failed to persist theme:", error);
    }
  },

  // Wołane raz w main.tsx, obok import "./libs/i18n" — stosuje zapisany (albo
  // systemowy) motyw zanim cokolwiek się wyrenderuje.
  init(): void {
    apply(themeUtil.get());
  },
};
