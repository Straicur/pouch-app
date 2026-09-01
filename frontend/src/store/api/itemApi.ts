import { createApi } from "@reduxjs/toolkit/query/react";
import { ApiEndpoints } from "../../libs/apiEndpoints";
import type {
  CreateFileRequest,
  CreateNoteRequest,
  CreatePhotoRequest,
  CreateUrlRequest,
  ItemDetail,
  ItemListParams,
  ItemListResult,
  ItemVersion,
  MoveItemRequest,
  OverwriteFileRequest,
  PublicLink,
  SignedLink,
  UpdateNoteRequest,
  UpdateTagsRequest,
} from "../types/item";
import { axiosBaseQuery } from "./baseQuery";

// Request/response types live in store/types/item.ts (see its header) —
// import them from there, not from this file.

const fileFormData = (file: File, extra?: Record<string, string>): FormData => {
  const formData = new FormData();
  formData.append("file", file);
  for (const [key, value] of Object.entries(extra ?? {})) {
    formData.append(key, value);
  }

  return formData;
};

export const itemApi = createApi({
  reducerPath: "itemApi",
  baseQuery: axiosBaseQuery(),
  tagTypes: ["Item"],
  endpoints: (builder) => ({
    listItems: builder.query<ItemListResult, ItemListParams | undefined>({
      query: (args) => ({
        url: ApiEndpoints.ITEMS,
        method: "GET",
        params: {
          categoryIds: args?.categoryIds && args.categoryIds.length > 0 ? args.categoryIds.join(",") : undefined,
          favorite: true === args?.favorite ? true : undefined,
          tags: args?.tags && args.tags.length > 0 ? args.tags.join(",") : undefined,
          q: args?.q && "" !== args.q ? args.q : undefined,
          page: args?.page,
          pageSize: args?.pageSize,
        },
      }),
      providesTags: ["Item"],
    }),
    // ItemDetailsModal's fetch behind a deliberate click on a card, not
    // alongside the (paginated) list itself.
    getItem: builder.query<ItemDetail, number>({
      query: (id) => ({ url: ApiEndpoints.ITEM(id), method: "GET" }),
      providesTags: ["Item"],
    }),
    // DELETE /api/items/{id} moves the item to trash (see
    // ItemGarbageCollector) — ItemDetailsModal's delete button, behind a
    // ConfirmDialog.
    deleteItem: builder.mutation<void, number>({
      query: (id) => ({ url: ApiEndpoints.ITEM(id), method: "DELETE" }),
      invalidatesTags: ["Item"],
    }),
    // No filters, unlike listItems — see ItemRepository::findTrashedPage().
    listTrash: builder.query<ItemListResult, { page?: number; pageSize?: number } | undefined>({
      query: (args) => ({
        url: ApiEndpoints.ITEMS_TRASH,
        method: "GET",
        params: { page: args?.page, pageSize: args?.pageSize },
      }),
      providesTags: ["Item"],
    }),
    // Always comes back kept-forever — see ItemServiceInterface::restore().
    restoreItem: builder.mutation<ItemDetail, number>({
      query: (id) => ({ url: ApiEndpoints.ITEM_RESTORE(id), method: "PATCH" }),
      invalidatesTags: ["Item"],
    }),
    moveItem: builder.mutation<ItemDetail, MoveItemRequest>({
      query: ({ id, categoryId }) => ({ url: ApiEndpoints.ITEM_MOVE(id), method: "PATCH", data: { categoryId } }),
      invalidatesTags: ["Item"],
    }),
    getItemThumbnailLink: builder.mutation<SignedLink, number>({
      query: (id) => ({ url: ApiEndpoints.ITEM_THUMBNAIL_LINK(id), method: "POST", data: {} }),
    }),
    getItemDownloadLink: builder.mutation<SignedLink, number>({
      query: (id) => ({ url: ApiEndpoints.ITEM_DOWNLOAD_LINK(id), method: "POST", data: {} }),
    }),
    createNote: builder.mutation<ItemDetail, CreateNoteRequest>({
      query: (body) => ({ url: ApiEndpoints.ITEM_NOTES, method: "POST", data: body }),
      invalidatesTags: ["Item"],
    }),
    updateNote: builder.mutation<ItemDetail, UpdateNoteRequest>({
      query: ({ id, content }) => ({ url: ApiEndpoints.ITEM_NOTE(id), method: "PATCH", data: { content } }),
      invalidatesTags: ["Item"],
    }),
    updateTags: builder.mutation<ItemDetail, UpdateTagsRequest>({
      query: ({ id, tags }) => ({ url: ApiEndpoints.ITEM_TAGS(id), method: "PUT", data: { tags } }),
      invalidatesTags: ["Item"],
    }),
    markFavorite: builder.mutation<ItemDetail, number>({
      query: (id) => ({ url: ApiEndpoints.ITEM_FAVORITE(id), method: "PUT" }),
      invalidatesTags: ["Item"],
    }),
    unmarkFavorite: builder.mutation<ItemDetail, number>({
      query: (id) => ({ url: ApiEndpoints.ITEM_FAVORITE(id), method: "DELETE" }),
      invalidatesTags: ["Item"],
    }),
    createFile: builder.mutation<ItemDetail, CreateFileRequest>({
      query: ({ categoryId, file, name, content, tags, keepForever, ttlPreset, expiresAt }) => ({
        url: ApiEndpoints.ITEM_FILES,
        method: "POST",
        data: fileFormData(file, {
          categoryId: String(categoryId),
          ...(undefined !== name ? { name } : {}),
          ...(undefined !== content ? { content } : {}),
          ...(undefined !== tags && tags.length > 0 ? { tags: tags.join(",") } : {}),
          ...(undefined !== keepForever ? { keepForever: String(keepForever) } : {}),
          ...(undefined !== ttlPreset ? { ttlPreset } : {}),
          ...(undefined !== expiresAt ? { expiresAt } : {}),
        }),
      }),
      invalidatesTags: ["Item"],
    }),
    createPhoto: builder.mutation<ItemDetail, CreatePhotoRequest>({
      query: ({ categoryId, file, name, keepForever, ttlPreset, expiresAt }) => ({
        url: ApiEndpoints.ITEM_PHOTOS,
        method: "POST",
        data: fileFormData(file, {
          categoryId: String(categoryId),
          ...(undefined !== name ? { name } : {}),
          ...(undefined !== keepForever ? { keepForever: String(keepForever) } : {}),
          ...(undefined !== ttlPreset ? { ttlPreset } : {}),
          ...(undefined !== expiresAt ? { expiresAt } : {}),
        }),
      }),
      invalidatesTags: ["Item"],
    }),
    createUrl: builder.mutation<ItemDetail, CreateUrlRequest>({
      query: (body) => ({ url: ApiEndpoints.ITEM_URLS, method: "POST", data: body }),
      invalidatesTags: ["Item"],
    }),
    // Part 8 — same id/URL afterwards, see ItemServiceInterface::overwriteFile().
    overwriteFile: builder.mutation<ItemDetail, OverwriteFileRequest>({
      query: ({ id, file }) => ({ url: ApiEndpoints.ITEM_FILE(id), method: "POST", data: fileFormData(file) }),
      invalidatesTags: ["Item"],
    }),
    listVersions: builder.query<ItemVersion[], number>({
      query: (id) => ({ url: ApiEndpoints.ITEM_VERSIONS(id), method: "GET" }),
      providesTags: ["Item"],
    }),
    getVersionDownloadLink: builder.mutation<SignedLink, { id: number; version: number }>({
      query: ({ id, version }) => ({
        url: ApiEndpoints.ITEM_VERSION_DOWNLOAD_LINK(id, version),
        method: "POST",
        data: {},
      }),
    }),
    // Part 9 — a 24h link usable with no account at all; see AccessKeyGuard
    // (Part 7) for why generating it still requires being logged in and
    // already having access to the item.
    getPublicLink: builder.mutation<PublicLink, number>({
      query: (id) => ({ url: ApiEndpoints.ITEM_PUBLIC_LINK(id), method: "POST", data: {} }),
    }),
  }),
});

export const {
  useListItemsQuery,
  useGetItemQuery,
  useDeleteItemMutation,
  useListTrashQuery,
  useRestoreItemMutation,
  useMoveItemMutation,
  useGetItemThumbnailLinkMutation,
  useGetItemDownloadLinkMutation,
  useCreateNoteMutation,
  useUpdateNoteMutation,
  useUpdateTagsMutation,
  useMarkFavoriteMutation,
  useUnmarkFavoriteMutation,
  useCreateFileMutation,
  useCreatePhotoMutation,
  useCreateUrlMutation,
  useOverwriteFileMutation,
  useListVersionsQuery,
  useGetVersionDownloadLinkMutation,
  useGetPublicLinkMutation,
} = itemApi;
