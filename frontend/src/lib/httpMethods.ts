import type { AxiosRequestConfig, AxiosResponse } from "axios";
import { httpClient } from "./httpClient";

type RequestConfig = Omit<AxiosRequestConfig, "url" | "method" | "data">;

// httpClient defaults every request to Content-Type: application/json — fine
// for plain objects, but for a FormData payload (Part 8/9's file endpoints)
// axios's own transformRequest checks the *header*, not the payload type:
// with "application/json" still set, it would JSON.stringify() the FormData
// instead of sending it as multipart. Clearing the header (not just changing
// its value — any explicit value blocks the browser from adding its own
// multipart boundary) lets the browser compute the correct
// "multipart/form-data; boundary=..." header itself. Centralized here, once,
// instead of in axiosBaseQuery alone, so any future direct httpMethods call
// (not just RTK Query) gets it for free too.
const withRequestHeaders = (data: unknown, config?: RequestConfig): RequestConfig => {
  if (!(data instanceof FormData)) {
    return config ?? {};
  }

  return { ...config, headers: { ...config?.headers, "Content-Type": undefined } };
};

// Thin, typed wrappers over httpClient — one call shape per HTTP verb instead
// of every call site building its own AxiosRequestConfig by hand.
export const httpMethods = {
  get: <T>(url: string, config?: RequestConfig): Promise<AxiosResponse<T>> => {
    return httpClient.get<T>(url, config);
  },
  post: <T>(url: string, data?: unknown, config?: RequestConfig): Promise<AxiosResponse<T>> => {
    return httpClient.post<T>(url, data, withRequestHeaders(data, config));
  },
  put: <T>(url: string, data?: unknown, config?: RequestConfig): Promise<AxiosResponse<T>> => {
    return httpClient.put<T>(url, data, withRequestHeaders(data, config));
  },
  patch: <T>(url: string, data?: unknown, config?: RequestConfig): Promise<AxiosResponse<T>> => {
    return httpClient.patch<T>(url, data, withRequestHeaders(data, config));
  },
  del: <T>(url: string, config?: RequestConfig): Promise<AxiosResponse<T>> => {
    return httpClient.delete<T>(url, config);
  },
};
