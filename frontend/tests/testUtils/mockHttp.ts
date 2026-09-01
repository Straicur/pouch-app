import type { AxiosResponse } from "axios";
import type { Mock } from "vitest";

// Every store/api/*.ts slice goes through axiosBaseQuery -> httpMethods (see
// store/api/baseQuery.ts) — mocking httpMethods here, once, lets component
// tests exercise the real RTK Query slices (cache tags, invalidation,
// onQueryStarted side effects) without a real backend.
export interface MockedHttpMethods {
  get: Mock;
  post: Mock;
  put: Mock;
  patch: Mock;
  del: Mock;
}

export function mockApiResponse<T>(data: T): Promise<AxiosResponse<T>> {
  return Promise.resolve({ data, status: 200, statusText: "OK", headers: {}, config: {} } as AxiosResponse<T>);
}

// Shaped like the AxiosError axiosBaseQuery expects (see its catch block) —
// only the fields it actually reads.
export function mockApiError(status: number, body: unknown): Promise<never> {
  return Promise.reject({ response: { status, data: body }, message: `Request failed with status code ${status}` });
}
