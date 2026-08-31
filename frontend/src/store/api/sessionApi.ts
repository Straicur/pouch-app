import { createApi } from "@reduxjs/toolkit/query/react";
import { ApiEndpoints } from "../../libs/apiEndpoints";
import { axiosBaseQuery } from "./baseQuery";

export interface WhoAmIResponse {
  email: string;
}

// TestRequestDTO requires a valid-looking email, but ignores its value — see TestController.
const IGNORED_VALIDATION_PLACEHOLDER = "whoami@pouch.local";

export const sessionApi = createApi({
  reducerPath: "sessionApi",
  baseQuery: axiosBaseQuery(),
  keepUnusedDataFor: 0,
  endpoints: (builder) => ({
    whoAmI: builder.query<WhoAmIResponse, void>({
      query: () => ({
        url: ApiEndpoints.TEST,
        method: "POST",
        data: { email: IGNORED_VALIDATION_PLACEHOLDER },
      }),
    }),
  }),
});

export const { useWhoAmIQuery, useLazyWhoAmIQuery } = sessionApi;
