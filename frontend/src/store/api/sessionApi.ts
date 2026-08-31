import { createApi } from "@reduxjs/toolkit/query/react";
import { ApiEndpoints } from "../../libs/apiEndpoints";
import { axiosBaseQuery } from "./baseQuery";

export interface WhoAmIResponse {
  email: string;
  isAdmin: boolean;
}

export const sessionApi = createApi({
  reducerPath: "sessionApi",
  baseQuery: axiosBaseQuery(),
  keepUnusedDataFor: 0,
  endpoints: (builder) => ({
    whoAmI: builder.query<WhoAmIResponse, void>({
      query: () => ({
        url: ApiEndpoints.WHOAMI,
        method: "GET",
      }),
    }),
  }),
});

export const { useWhoAmIQuery, useLazyWhoAmIQuery } = sessionApi;
