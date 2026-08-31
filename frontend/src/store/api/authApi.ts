import { createApi } from "@reduxjs/toolkit/query/react";
import { accessGrants } from "../../lib/accessGrants";
import { ApiEndpoints } from "../../lib/apiEndpoints";
import { axiosBaseQuery } from "./baseQuery";

export interface LoginRequest {
  email: string;
  password: string;
}

export const authApi = createApi({
  reducerPath: "authApi",
  baseQuery: axiosBaseQuery(),
  endpoints: (builder) => ({
    login: builder.mutation<void, LoginRequest>({
      query: (body) => ({ url: ApiEndpoints.LOGIN, method: "POST", data: body }),
      // Post-review fix: a grant earned by whoever was logged in before
      // (or not logged in at all) must not follow into this new session —
      // see lib/accessGrants.ts.
      async onQueryStarted(_arg, { queryFulfilled }) {
        accessGrants.clear();
        await queryFulfilled;
      },
    }),
    logout: builder.mutation<void, void>({
      query: () => ({ url: ApiEndpoints.LOGOUT, method: "POST", data: {} }),
      async onQueryStarted(_arg, { queryFulfilled }) {
        await queryFulfilled;
        accessGrants.clear();
      },
    }),
  }),
});

export const { useLoginMutation, useLogoutMutation } = authApi;
