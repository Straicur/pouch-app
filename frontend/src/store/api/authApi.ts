import { createApi } from "@reduxjs/toolkit/query/react";
import { ApiEndpoints } from "../../libs/apiEndpoints";
import { accessGrants } from "../../utils/accessGrants";
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
      // A grant earned by whoever was logged in before (or not logged in at
      // all) must not follow into this new session — see utils/accessGrants.ts.
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
