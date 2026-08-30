import type { NavigateFunction } from "react-router-dom";
import { RedirectEndpoints } from "./redirectEndpoints";

// Set once from RootLayout (useNavigate needs router context, this util doesn't have one) so
// non-component code — toastUtil, httpClient error handling, etc. — can still navigate.
let navigateFn: NavigateFunction | null = null;

export const navigationUtil = {
  setNavigate(navigate: NavigateFunction) {
    navigateFn = navigate;
  },

  navigate(path: string, options?: { replace?: boolean; state?: unknown }) {
    if (navigateFn) {
      navigateFn(path, options);
    } else {
      window.location.href = path;
    }
  },

  navigateToLogin() {
    navigationUtil.navigate(RedirectEndpoints.LOGIN, { replace: true });
  },

  reload() {
    window.location.reload();
  },
};
