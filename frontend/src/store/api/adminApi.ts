import { createApi } from "@reduxjs/toolkit/query/react";
import { ApiEndpoints } from "../../lib/apiEndpoints";
import { axiosBaseQuery } from "./baseQuery";
import type { Item } from "./itemApi";

export interface StorageUsageByType {
  type: string;
  totalBytes: number;
  itemCount: number;
}

export interface StorageLimit {
  type: string;
  maxSizeBytes: number;
}

export interface StorageReport {
  byType: StorageUsageByType[];
  archivedVersionsBytes: number;
  limits: StorageLimit[];
}

export interface GcRunLog {
  id: number;
  trigger: "cron" | "manual";
  expiredCount: number;
  purgedCount: number;
  runAt: string;
}

export interface AuditLogEntry {
  id: number;
  action: "view" | "download" | "delete" | "key_change" | "purge";
  resourceType: "category" | "item";
  resourceId: number;
  userId: number | null;
  userEmail: string | null;
  ip: string | null;
  createdAt: string;
}

export interface AuditLogParams {
  limit?: number;
  resourceType?: "category" | "item";
  action?: AuditLogEntry["action"];
}

export interface ExtendExpiryRequest {
  itemIds: number[];
  keepForever: boolean;
  ttlPreset?: string;
  expiresAt?: string;
}

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
