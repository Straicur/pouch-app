import { createApi } from "@reduxjs/toolkit/query/react";
import { ApiEndpoints } from "../../libs/apiEndpoints";
import { accessGrants } from "../../utils/accessGrants";
import { axiosBaseQuery } from "./baseQuery";

// Self-service account management — DELETE /api/account (regular account,
// keeps the pouch and its data) and DELETE /api/account/pouch (admin only,
// wipes the whole pouch). Both log the caller out server-side, same as
// authApi's logout — clear any grant earned in this session too.
export const accountApi = createApi({
  reducerPath: "accountApi",
  baseQuery: axiosBaseQuery(),
  endpoints: (builder) => ({
    deleteAccount: builder.mutation<void, void>({
      query: () => ({ url: ApiEndpoints.ACCOUNT, method: "DELETE" }),
      async onQueryStarted(_arg, { queryFulfilled }) {
        await queryFulfilled;
        accessGrants.clear();
      },
    }),
    deletePouch: builder.mutation<void, void>({
      query: () => ({ url: ApiEndpoints.ACCOUNT_POUCH, method: "DELETE" }),
      async onQueryStarted(_arg, { queryFulfilled }) {
        await queryFulfilled;
        accessGrants.clear();
      },
    }),
  }),
});

export const { useDeleteAccountMutation, useDeletePouchMutation } = accountApi;
