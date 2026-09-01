import { createApi } from "@reduxjs/toolkit/query/react";
import { ApiEndpoints } from "../../libs/apiEndpoints";
import { accessGrants } from "../../utils/accessGrants";
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

// What POST .../export-token returns — see utils/triggerDownload.ts and
// CategoryController's own doc comments for why a category export needs
// this at all (a plain navigation, used so the ZIP streams, can't set the
// X-Pouch-Access-Grants header a normal request would — this mints a
// short-lived, opaque token carrying the same grants instead of putting
// them in the URL itself).
export interface CategoryExportToken {
  token: string;
  expiresAt: string;
}

// Part 7. accessKeyApi is its own createApi instance (like every other slice
// here), so its invalidatesTags can't reach itemApi's cache — RTK Query tags
// only invalidate within the same api. Instead, every mutation that changes
// what's visible/locked dispatches itemApi.util.invalidateTags directly, and
// a successful unlock stores the grant it earned (see utils/accessGrants.ts)
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
      // RTK Query rejects queryFulfilled the same way the mutation itself
      // does — the caller already sees that via unlockCategory(...).unwrap(),
      // so a failure here is just swallowed rather than left unhandled.
      onQueryStarted: async (_args, { dispatch, queryFulfilled }) => {
        try {
          const { data } = await queryFulfilled;
          accessGrants.add(data);
          dispatch(itemApi.util.invalidateTags(["Item"]));
        } catch {
          // handled by the caller via .unwrap()
        }
      },
    }),
    unlockItem: builder.mutation<AccessGrantResponse, UnlockItemRequest>({
      query: ({ itemId, key }) => ({ url: ApiEndpoints.ITEM_UNLOCK(itemId), method: "POST", data: { key } }),
      onQueryStarted: async (_args, { dispatch, queryFulfilled }) => {
        try {
          const { data } = await queryFulfilled;
          accessGrants.add(data);
          dispatch(itemApi.util.invalidateTags(["Item"]));
        } catch {
          // handled by the caller via .unwrap()
        }
      },
    }),
    setCategoryKey: builder.mutation<void, SetCategoryKeyRequest>({
      query: ({ categoryId, key }) => ({
        url: ApiEndpoints.CATEGORY_ACCESS_KEY(categoryId),
        method: "PUT",
        data: { key },
      }),
      onQueryStarted: async (_args, { dispatch, queryFulfilled }) => {
        try {
          await queryFulfilled;
          dispatch(itemApi.util.invalidateTags(["Item"]));
        } catch {
          // handled by the caller via .unwrap()
        }
      },
    }),
    setItemKey: builder.mutation<void, SetItemKeyRequest>({
      query: ({ itemId, key }) => ({ url: ApiEndpoints.ITEM_ACCESS_KEY(itemId), method: "PUT", data: { key } }),
      onQueryStarted: async (_args, { dispatch, queryFulfilled }) => {
        try {
          await queryFulfilled;
          dispatch(itemApi.util.invalidateTags(["Item"]));
        } catch {
          // handled by the caller via .unwrap()
        }
      },
    }),
    getCategoryExportToken: builder.mutation<CategoryExportToken, number>({
      // A normal AJAX POST — httpClient's interceptor attaches whatever
      // grants this session currently holds as the usual header, no special
      // handling needed here.
      query: (categoryId) => ({ url: ApiEndpoints.CATEGORY_EXPORT_TOKEN(categoryId), method: "POST", data: {} }),
    }),
  }),
});

export const {
  useUnlockCategoryMutation,
  useUnlockItemMutation,
  useSetCategoryKeyMutation,
  useSetItemKeyMutation,
  useGetCategoryExportTokenMutation,
} = accessKeyApi;
