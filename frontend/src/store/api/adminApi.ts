import { createApi } from "@reduxjs/toolkit/query/react";
import { ApiEndpoints } from "../../libs/apiEndpoints";
import type { AuditLogEntry, AuditLogParams, ExtendExpiryRequest, GcRunLog, StorageReport } from "../types/admin";
import type { Item } from "../types/item";
import { axiosBaseQuery } from "./baseQuery";

// Request/response types live in store/types/admin.ts (see its header) —
// import them from there, not from this file.

// Part 10 — every endpoint here is ROLE_ADMIN-only server-side; the frontend
// doesn't try to duplicate that check (no role info is exposed to JS at all —
// see App\Security\CookieService, the JWT is httpOnly), it just renders
// whatever a 403 (ExceptionUuid.FORBIDDEN) means: "you're not an admin".
export const adminApi = createApi({
  reducerPath: "adminApi",
  baseQuery: axiosBaseQuery(),
  tagTypes: ["Storage", "GcRuns", "AuditLog", "ExpiringSoon"],
  endpoints: (builder) => ({
    getStorageReport: builder.query<StorageReport, void>({
      query: () => ({ url: ApiEndpoints.ADMIN_STORAGE, method: "GET" }),
      providesTags: ["Storage"],
    }),
    setStorageLimit: builder.mutation<void, { type: string; maxSizeBytes: number }>({
      query: ({ type, maxSizeBytes }) => ({
        url: ApiEndpoints.ADMIN_STORAGE_LIMIT(type),
        method: "PUT",
        data: { maxSizeBytes },
      }),
      invalidatesTags: ["Storage"],
    }),
    runGc: builder.mutation<GcRunLog, void>({
      query: () => ({ url: ApiEndpoints.ADMIN_GC_RUN, method: "POST" }),
      invalidatesTags: ["GcRuns"],
    }),
    listGcRuns: builder.query<GcRunLog[], number | undefined>({
      query: (limit) => ({ url: ApiEndpoints.ADMIN_GC_RUNS, method: "GET", params: { limit } }),
      providesTags: ["GcRuns"],
    }),
    listAuditLog: builder.query<AuditLogEntry[], AuditLogParams | undefined>({
      query: (args) => ({
        url: ApiEndpoints.ADMIN_AUDIT_LOG,
        method: "GET",
        params: { limit: args?.limit, resourceType: args?.resourceType, action: args?.action },
      }),
      providesTags: ["AuditLog"],
    }),
    listExpiringSoon: builder.query<Item[], number | undefined>({
      query: (withinHours) => ({ url: ApiEndpoints.ADMIN_ITEMS_EXPIRING_SOON, method: "GET", params: { withinHours } }),
      providesTags: ["ExpiringSoon"],
    }),
    extendExpiry: builder.mutation<Item[], ExtendExpiryRequest>({
      query: (body) => ({ url: ApiEndpoints.ADMIN_ITEMS_EXTEND, method: "POST", data: body }),
      invalidatesTags: ["ExpiringSoon"],
    }),
  }),
});

export const {
  useGetStorageReportQuery,
  useSetStorageLimitMutation,
  useRunGcMutation,
  useListGcRunsQuery,
  useListAuditLogQuery,
  useListExpiringSoonQuery,
  useExtendExpiryMutation,
} = adminApi;
