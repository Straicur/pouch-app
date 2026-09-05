import axios, { type AxiosError, type InternalAxiosRequestConfig } from "axios";
import { accessGrants, GRANTS_HEADER } from "../utils/accessGrants";
import { ApiEndpoints, NO_REFRESH_ENDPOINTS } from "./apiEndpoints";
import { ExceptionUuid, getApiErrorBody, getApiErrorUuid } from "./apiError";
import { navigationUtil } from "./navigationUtil";
import { RedirectEndpoints } from "./redirectEndpoints";

// Auth is cookie-based (httpOnly, see App\Security\CookieService) — no token touches JS.
export const httpClient = axios.create({
  baseURL: "/",
  withCredentials: true,
  headers: {
    "Content-Type": "application/json",
    // biome-ignore lint/style/useNamingConvention: HTTP header name, not a JS identifier.
    Accept: "application/json",
  },
});

// Part 7: every request carries whatever access grants this tab currently
// holds — AccessKeyGuard only looks at the ones relevant to the resource
// being touched, so it's simplest to just always attach the full set rather
// than have every call site figure out which grant(s) a given request needs.
httpClient.interceptors.request.use((config) => {
  const headerValue = accessGrants.toHeaderValue();
  if (undefined !== headerValue) {
    config.headers.set(GRANTS_HEADER, headerValue);
  }

  return config;
});

type RetryableConfig = InternalAxiosRequestConfig & { _retry?: boolean };

// Concurrent 401s share a single refresh call instead of racing each other.
let refreshPromise: Promise<void> | null = null;

const refreshAccessToken = async (): Promise<void> => {
  refreshPromise ??= httpClient
    .post(ApiEndpoints.REFRESH_TOKEN)
    .then(() => undefined)
    .finally(() => {
      refreshPromise = null;
    });

  return refreshPromise;
};

httpClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const { response, config } = error;
    const originalRequest = config as RetryableConfig | undefined;

    if (undefined === response || undefined === originalRequest) {
      return Promise.reject(error);
    }

    // Admins are never sent this error (see TechnicalBreakListener) — any request
    // that gets it is from a blocked, logged-in non-admin, on whatever endpoint they
    // happened to be calling. Message comes along as router state so the page can
    // show the admin's own text instead of a generic fallback.
    if (ExceptionUuid.TECHNICAL_BREAK === getApiErrorUuid(response)) {
      navigationUtil.navigate(RedirectEndpoints.TECHNICAL_BREAK, {
        replace: true,
        state: { message: getApiErrorBody(response)?.detail },
      });

      return Promise.reject(error);
    }

    const url = originalRequest.url ?? "";
    const isUnauthorized = 401 === response.status;
    const isRetryable = isUnauthorized && !NO_REFRESH_ENDPOINTS.includes(url) && true !== originalRequest._retry;

    if (!isRetryable) {
      return Promise.reject(error);
    }

    originalRequest._retry = true;

    try {
      await refreshAccessToken();
    } catch (refreshError) {
      return Promise.reject(refreshError);
    }

    return httpClient(originalRequest);
  },
);
