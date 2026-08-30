import { createElement } from "react";
import { type ToastOptions, toast } from "react-toastify";
import { logger } from "./logger";
import { navigationUtil } from "./navigationUtil";

export type ToastType = "success" | "error" | "info" | "warning";

const TOAST_STORAGE_KEY = "pendingToast";
const UI_DELAY_MS = 4000;
// A pending toast older than this (page reloaded much later, e.g. a stale tab) is dropped
// instead of shown, so it doesn't surprise the user with a message from a previous action.
const PENDING_TOAST_TTL_MS = 10_000;
// Gives persistToast's sessionStorage write time to land before the reload/navigation fires.
const NAVIGATE_DELAY_MS = 50;
// Gives ToastContainer a tick to mount before showPendingToast fires right after page load.
const PENDING_TOAST_SHOW_DELAY_MS = 100;

interface PendingToast {
  message: string;
  type: ToastType;
  timestamp: number;
}

const TOAST_TITLES: Record<ToastType, string> = {
  success: "Sukces",
  error: "Błąd",
  warning: "Ostrzeżenie",
  info: "Informacja",
};

const TOAST_FN: Record<ToastType, typeof toast.success> = {
  success: toast.success,
  error: toast.error,
  warning: toast.warn,
  info: toast.info,
};

export const toastUtil = {
  showToast(message: string, type: ToastType, autoClose: boolean = true, options?: ToastOptions) {
    const mergedOptions: ToastOptions = {
      position: "top-right",
      autoClose: autoClose ? UI_DELAY_MS : false,
      theme: "colored",
      ...options,
    };

    const payload = createElement(
      "div",
      null,
      createElement("p", { style: { fontWeight: 600, margin: 0 } }, TOAST_TITLES[type]),
      createElement("p", { style: { margin: 0 } }, message),
    );

    TOAST_FN[type](payload, mergedOptions);
  },

  persistToast(message: string, type: ToastType) {
    try {
      const pendingToast: PendingToast = { message, type, timestamp: Date.now() };
      sessionStorage.setItem(TOAST_STORAGE_KEY, JSON.stringify(pendingToast));
    } catch (error) {
      logger.error("Failed to persist toast:", error);
    }
  },

  showPendingToast() {
    try {
      const storedToast = sessionStorage.getItem(TOAST_STORAGE_KEY);
      if (null === storedToast) {
        return;
      }

      const pendingToast: PendingToast = JSON.parse(storedToast);
      sessionStorage.removeItem(TOAST_STORAGE_KEY);

      if (Date.now() - pendingToast.timestamp < PENDING_TOAST_TTL_MS) {
        setTimeout(() => {
          toastUtil.showToast(pendingToast.message, pendingToast.type);
        }, PENDING_TOAST_SHOW_DELAY_MS);
      }
    } catch (error) {
      logger.error("Failed to show pending toast:", error);
    }
  },

  showToastAndReload(message: string, type: ToastType) {
    toastUtil.persistToast(message, type);
    setTimeout(() => {
      navigationUtil.reload();
    }, NAVIGATE_DELAY_MS);
  },

  showToastAndNavigateToLogin(message: string, type: ToastType) {
    toastUtil.persistToast(message, type);
    setTimeout(() => {
      navigationUtil.navigateToLogin();
    }, NAVIGATE_DELAY_MS);
  },
};
