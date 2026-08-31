import { createApi } from "@reduxjs/toolkit/query/react";
import { accessGrants } from "../../lib/accessGrants";
import { ApiEndpoints } from "../../lib/apiEndpoints";
import { axiosBaseQuery } from "./baseQuery";
import { itemApi } from "./itemApi";

export interface AccessGrantResponse {
  resource: string;
  expires: number;
  signature: string;
}

interface UnlockCategoryRequest {
  categoryId: number;
  key: string;
}

interface UnlockItemRequest {
  itemId: number;
  key: string;
}

interface SetCategoryKeyRequest {
  categoryId: number;
  key: string | null;
}

interface SetItemKeyRequest {
  itemId: number;
  key: string | null;
}

// Part 7. accessKeyApi is its own createApi instance (like every other slice
// here), so its invalidatesTags can't reach itemApi's cache — RTK Query tags
// only invalidate within the same api. Instead, every mutation that changes
// what's visible/locked dispatches itemApi.util.invalidateTags directly, and
// a successful unlock stores the grant it earned (see lib/accessGrants.ts)
// as a side effect too, right here, so every call site gets both for free.
export const accessKeyApi = createApi({
  reducerPath: "accessKeyApi",
  baseQuery: axiosBaseQuery(),
  endpoints: (builder) => ({
    unlockCategory: builder.mutation<AccessGrantResponse, UnlockCategoryRequest>({
      query: ({ categoryId, key }) => ({
        url: ApiEndpoints.CATEGORY_UNLOCK(categoryId),
        method: "POST",
        data: { key },
      }),
      onQueryStarted: async (_args, { dispatch, queryFulfilled }) => {
        const { data } = await queryFulfilled;
        accessGrants.add(data);
        dispatch(itemApi.util.invalidateTags(["Item"]));
      },
    }),
    unlockItem: builder.mutation<AccessGrantResponse, UnlockItemRequest>({
      query: ({ itemId, key }) => ({ url: ApiEndpoints.ITEM_UNLOCK(itemId), method: "POST", data: { key } }),
      onQueryStarted: async (_args, { dispatch, queryFulfilled }) => {
        const { data } = await queryFulfilled;
        accessGrants.add(data);
        dispatch(itemApi.util.invalidateTags(["Item"]));
      },
    }),
    setCategoryKey: builder.mutation<void, SetCategoryKeyRequest>({
      query: ({ categoryId, key }) => ({
        url: ApiEndpoints.CATEGORY_ACCESS_KEY(categoryId),
        method: "PUT",
        data: { key },
      }),
      onQueryStarted: async (_args, { dispatch, queryFulfilled }) => {
        await queryFulfilled;
        dispatch(itemApi.util.invalidateTags(["Item"]));
      },
    }),
    setItemKey: builder.mutation<void, SetItemKeyRequest>({
      query: ({ itemId, key }) => ({ url: ApiEndpoints.ITEM_ACCESS_KEY(itemId), method: "PUT", data: { key } }),
      onQueryStarted: async (_args, { dispatch, queryFulfilled }) => {
        await queryFulfilled;
        dispatch(itemApi.util.invalidateTags(["Item"]));
      },
    }),
  }),
});

export const { useUnlockCategoryMutation, useUnlockItemMutation, useSetCategoryKeyMutation, useSetItemKeyMutation } =
  accessKeyApi;
