import { createApi } from "@reduxjs/toolkit/query/react";
import { ApiEndpoints } from "../../libs/apiEndpoints";
import type {
  AuditLogEntry,
  AuditLogParams,
  ExtendExpiryRequest,
  GcRunLog,
  StorageReport,
  TechnicalBreakStatus,
} from "../types/admin";
import type { ItemDetail, ItemListResult } from "../types/item";
import { axiosBaseQuery } from "./baseQuery";

// Request/response types live in store/types/admin.ts (see its header) —
// import them from there, not from this file.

// Every endpoint here is ROLE_ADMIN-only server-side; the frontend doesn't
// try to duplicate that check (no role info is exposed to JS at all — see
// App\Security\CookieService, the JWT is httpOnly), it just renders whatever
// a 403 (ExceptionUuid.FORBIDDEN) means: "you're not an admin".
//
// pouchId (null/undefined = every pouch, the backend's own default) is on
// every read here — see modules/admin/pouchFilter.tsx's PouchFilterProvider,
// the one place a page picks it up instead of managing its own state.
export const adminApi = createApi({
  reducerPath: "adminApi",
  baseQuery: axiosBaseQuery(),
  tagTypes: ["Storage", "GcRuns", "AuditLog", "ExpiringSoon", "AdminItems", "TechnicalBreak"],
  endpoints: (builder) => ({
    getStorageReport: builder.query<StorageReport, number | null | undefined>({
      query: (pouchId) => ({
        url: ApiEndpoints.ADMIN_STORAGE,
        method: "GET",
        params: { pouchId: pouchId ?? undefined },
      }),
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
    runGc: builder.mutation<GcRunLog, number | null | undefined>({
      query: (pouchId) => ({
        url: ApiEndpoints.ADMIN_GC_RUN,
        method: "POST",
        params: { pouchId: pouchId ?? undefined },
      }),
      invalidatesTags: ["GcRuns"],
    }),
    listGcRuns: builder.query<GcRunLog[], { limit?: number; pouchId?: number | null } | undefined>({
      query: (args) => ({
        url: ApiEndpoints.ADMIN_GC_RUNS,
        method: "GET",
        params: { limit: args?.limit, pouchId: args?.pouchId ?? undefined },
      }),
      providesTags: ["GcRuns"],
    }),
    listAuditLog: builder.query<AuditLogEntry[], AuditLogParams | undefined>({
      query: (args) => ({
        url: ApiEndpoints.ADMIN_AUDIT_LOG,
        method: "GET",
        params: {
          limit: args?.limit,
          resourceType: args?.resourceType,
          action: args?.action,
          pouchId: args?.pouchId ?? undefined,
        },
      }),
      providesTags: ["AuditLog"],
    }),
    listExpiringSoon: builder.query<ItemDetail[], { withinHours?: number; pouchId?: number | null } | undefined>({
      query: (args) => ({
        url: ApiEndpoints.ADMIN_ITEMS_EXPIRING_SOON,
        method: "GET",
        params: { withinHours: args?.withinHours, pouchId: args?.pouchId ?? undefined },
      }),
      providesTags: ["ExpiringSoon"],
    }),
    extendExpiry: builder.mutation<ItemDetail[], ExtendExpiryRequest>({
      query: (body) => ({ url: ApiEndpoints.ADMIN_ITEMS_EXTEND, method: "POST", data: body }),
      invalidatesTags: ["ExpiringSoon"],
    }),
    listAdminItems: builder.query<ItemListResult, { pouchId: number; page?: number; pageSize?: number }>({
      query: ({ pouchId, page, pageSize }) => ({
        url: ApiEndpoints.ADMIN_ITEMS,
        method: "GET",
        params: { pouchId, page, pageSize },
      }),
      providesTags: ["AdminItems"],
    }),
    deleteAdminItem: builder.mutation<void, number>({
      query: (id) => ({ url: ApiEndpoints.ADMIN_ITEM(id), method: "DELETE" }),
      invalidatesTags: ["AdminItems", "Storage"],
    }),
    getTechnicalBreakStatus: builder.query<TechnicalBreakStatus, void>({
      query: () => ({ url: ApiEndpoints.ADMIN_TECHNICAL_BREAK, method: "GET" }),
      providesTags: ["TechnicalBreak"],
    }),
    enableTechnicalBreak: builder.mutation<TechnicalBreakStatus, { message?: string }>({
      query: (body) => ({ url: ApiEndpoints.ADMIN_TECHNICAL_BREAK, method: "POST", data: body }),
      invalidatesTags: ["TechnicalBreak"],
    }),
    disableTechnicalBreak: builder.mutation<void, void>({
      query: () => ({ url: ApiEndpoints.ADMIN_TECHNICAL_BREAK, method: "DELETE" }),
      invalidatesTags: ["TechnicalBreak"],
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
  useListAdminItemsQuery,
  useDeleteAdminItemMutation,
  useGetTechnicalBreakStatusQuery,
  useEnableTechnicalBreakMutation,
  useDisableTechnicalBreakMutation,
} = adminApi;
