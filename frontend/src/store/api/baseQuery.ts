import type { BaseQueryFn } from "@reduxjs/toolkit/query";
import type { AxiosError, AxiosRequestConfig, Method } from "axios";
import { httpClient } from "../../lib/httpClient";

export interface AxiosBaseQueryArgs {
  url: string;
  method?: Method;
  data?: unknown;
  params?: Record<string, unknown>;
}

export interface AxiosBaseQueryError {
  status?: number;
  data?: unknown;
  message: string;
}

// Routes RTK Query through httpClient instead of its default fetch-based baseQuery.
export const axiosBaseQuery = (): BaseQueryFn<AxiosBaseQueryArgs, unknown, AxiosBaseQueryError> => {
  return async ({ url, method = "GET", data, params }) => {
    try {
      const config: AxiosRequestConfig = { url, method, data, params };
      const response = await httpClient(config);

      return { data: response.data as unknown };
    } catch (error) {
      const axiosError = error as AxiosError;

      return {
        error: {
          status: axiosError.response?.status,
          data: axiosError.response?.data,
          message: axiosError.message,
        },
      };
    }
  };
};
