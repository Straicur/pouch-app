import { createApi } from "@reduxjs/toolkit/query/react";
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
    }),
    logout: builder.mutation<void, void>({
      query: () => ({ url: ApiEndpoints.LOGOUT, method: "POST", data: {} }),
    }),
  }),
});

export const { useLoginMutation, useLogoutMutation } = authApi;
