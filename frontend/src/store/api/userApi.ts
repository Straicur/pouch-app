import { createApi } from "@reduxjs/toolkit/query/react";
import { ApiEndpoints } from "../../libs/apiEndpoints";
import type { CreateUserRequest, PouchOverview, UserAccount, UserCreatedResult, UserRole } from "../types/user";
import { axiosBaseQuery } from "./baseQuery";

// Request/response types live in store/types/user.ts (see its header) — import
// them from there, not from this file. Every endpoint here is ROLE_ADMIN-only
// server-side, same caveat as adminApi.ts: the frontend doesn't duplicate
// that check, it just renders whatever a 403 means.
export const userApi = createApi({
  reducerPath: "userApi",
  baseQuery: axiosBaseQuery(),
  tagTypes: ["Users", "Pouches"],
  endpoints: (builder) => ({
    listUsers: builder.query<UserAccount[], void>({
      query: () => ({ url: ApiEndpoints.ADMIN_USERS, method: "GET" }),
      providesTags: ["Users"],
    }),
    createUser: builder.mutation<UserCreatedResult, CreateUserRequest>({
      query: (body) => ({ url: ApiEndpoints.ADMIN_USERS, method: "POST", data: body }),
      // A new account can found a new pouch — the overview list changes too.
      invalidatesTags: ["Users", "Pouches"],
    }),
    changeUserRole: builder.mutation<UserAccount, { id: number; role: UserRole }>({
      query: ({ id, role }) => ({ url: ApiEndpoints.ADMIN_USER_ROLE(id), method: "PATCH", data: { role } }),
      invalidatesTags: ["Users"],
    }),
    setUserEnabled: builder.mutation<UserAccount, { id: number; enabled: boolean }>({
      query: ({ id, enabled }) => ({ url: ApiEndpoints.ADMIN_USER_ENABLED(id), method: "PATCH", data: { enabled } }),
      invalidatesTags: ["Users"],
    }),
    resetUserPassword: builder.mutation<UserCreatedResult, number>({
      query: (id) => ({ url: ApiEndpoints.ADMIN_USER_RESET_PASSWORD(id), method: "POST" }),
    }),
    deleteUser: builder.mutation<void, number>({
      query: (id) => ({ url: ApiEndpoints.ADMIN_USER(id), method: "DELETE" }),
      invalidatesTags: ["Users", "Pouches"],
    }),
    listPouches: builder.query<PouchOverview[], void>({
      query: () => ({ url: ApiEndpoints.ADMIN_POUCHES, method: "GET" }),
      providesTags: ["Pouches"],
    }),
  }),
});

export const {
  useListUsersQuery,
  useCreateUserMutation,
  useChangeUserRoleMutation,
  useSetUserEnabledMutation,
  useResetUserPasswordMutation,
  useDeleteUserMutation,
  useListPouchesQuery,
} = userApi;
