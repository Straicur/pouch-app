import type { BaseQueryFn } from "@reduxjs/toolkit/query";
import type { AxiosError, AxiosResponse, Method } from "axios";
import { httpMethods } from "../../lib/httpMethods";

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

const dispatch = (
  method: Method,
  url: string,
  data: unknown,
  params: Record<string, unknown> | undefined,
): Promise<AxiosResponse> => {
  const normalizedMethod = method.toUpperCase();

  switch (normalizedMethod) {
    case "GET":
      return httpMethods.get(url, { params });
    case "POST":
      return httpMethods.post(url, data, { params });
    case "PUT":
      return httpMethods.put(url, data, { params });
    case "PATCH":
      return httpMethods.patch(url, data, { params });
    case "DELETE":
      return httpMethods.del(url, { params });
    default:
      throw new Error(`Unsupported HTTP method: ${method}`);
  }
};

// Routes RTK Query through httpMethods (see lib/httpMethods.ts) instead of
// its default fetch-based baseQuery.
export const axiosBaseQuery = (): BaseQueryFn<AxiosBaseQueryArgs, unknown, AxiosBaseQueryError> => {
  return async ({ url, method = "GET", data, params }) => {
    try {
      const response = await dispatch(method, url, data, params);

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
