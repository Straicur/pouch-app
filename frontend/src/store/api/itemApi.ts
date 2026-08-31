import { createApi } from "@reduxjs/toolkit/query/react";
import { ApiEndpoints } from "../../lib/apiEndpoints";
import { axiosBaseQuery } from "./baseQuery";

export type ItemType = "file" | "url" | "photo" | "note";
export type ItemProcessingStatus = "pending" | "completed" | "failed";

export interface Item {
  id: number;
  categoryId: number;
  type: ItemType;
  name: string;
  processingStatus: ItemProcessingStatus;
  processingError: string | null;
  originalFilename: string | null;
  mimeType: string | null;
  size: number | null;
  hasThumbnail: boolean;
  url: string | null;
  pageTitle: string | null;
  pageDescription: string | null;
  extractedText: string | null;
  noteContent: string | null;
  favorite: boolean;
  tags: string[];
  keepForever: boolean;
  expiresAt: string | null;
  trashedAt: string | null;
  createdAt: string;
}

export interface ItemListParams {
  categoryId?: number;
  favorite?: boolean;
  tags?: string[];
  q?: string;
}

export interface SignedLink {
  url: string;
  expiresAt: string;
}

export interface CreateNoteRequest {
  categoryId: number;
  content: string;
  name?: string;
}

export interface UpdateNoteRequest {
  id: number;
  content: string;
}

export interface UpdateTagsRequest {
  id: number;
  tags: string[];
}

export interface CreateFileRequest {
  categoryId: number;
  file: File;
  name?: string;
}

export interface OverwriteFileRequest {
  id: number;
  file: File;
}

export interface ItemVersion {
  version: number;
  originalFilename: string;
  mimeType: string;
  size: number;
  createdAt: string;
}

export interface PublicLink {
  viewUrl: string;
  downloadUrl: string | null;
  thumbnailUrl: string | null;
  expiresAt: string;
}

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
    listItems: builder.query<Item[], ItemListParams | undefined>({
      query: (args) => ({
        url: ApiEndpoints.ITEMS,
        method: "GET",
        params: {
          categoryId: args?.categoryId,
          favorite: true === args?.favorite ? true : undefined,
          tags: args?.tags && args.tags.length > 0 ? args.tags.join(",") : undefined,
          q: args?.q && "" !== args.q ? args.q : undefined,
        },
      }),
      providesTags: ["Item"],
    }),
    getItemThumbnailLink: builder.mutation<SignedLink, number>({
      query: (id) => ({ url: ApiEndpoints.ITEM_THUMBNAIL_LINK(id), method: "POST", data: {} }),
    }),
    getItemDownloadLink: builder.mutation<SignedLink, number>({
      query: (id) => ({ url: ApiEndpoints.ITEM_DOWNLOAD_LINK(id), method: "POST", data: {} }),
    }),
    createNote: builder.mutation<Item, CreateNoteRequest>({
      query: (body) => ({ url: ApiEndpoints.ITEM_NOTES, method: "POST", data: body }),
      invalidatesTags: ["Item"],
    }),
    updateNote: builder.mutation<Item, UpdateNoteRequest>({
      query: ({ id, content }) => ({ url: ApiEndpoints.ITEM_NOTE(id), method: "PATCH", data: { content } }),
      invalidatesTags: ["Item"],
    }),
    updateTags: builder.mutation<Item, UpdateTagsRequest>({
      query: ({ id, tags }) => ({ url: ApiEndpoints.ITEM_TAGS(id), method: "PUT", data: { tags } }),
      invalidatesTags: ["Item"],
    }),
    markFavorite: builder.mutation<Item, number>({
      query: (id) => ({ url: ApiEndpoints.ITEM_FAVORITE(id), method: "PUT" }),
      invalidatesTags: ["Item"],
    }),
    unmarkFavorite: builder.mutation<Item, number>({
      query: (id) => ({ url: ApiEndpoints.ITEM_FAVORITE(id), method: "DELETE" }),
      invalidatesTags: ["Item"],
    }),
    createFile: builder.mutation<Item, CreateFileRequest>({
      query: ({ categoryId, file, name }) => ({
        url: ApiEndpoints.ITEM_FILES,
        method: "POST",
        data: fileFormData(file, { categoryId: String(categoryId), ...(undefined !== name ? { name } : {}) }),
      }),
      invalidatesTags: ["Item"],
    }),
    // Part 8 — same id/URL afterwards, see ItemServiceInterface::overwriteFile().
    overwriteFile: builder.mutation<Item, OverwriteFileRequest>({
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
  useGetItemThumbnailLinkMutation,
  useGetItemDownloadLinkMutation,
  useCreateNoteMutation,
  useUpdateNoteMutation,
  useUpdateTagsMutation,
  useMarkFavoriteMutation,
  useUnmarkFavoriteMutation,
  useCreateFileMutation,
  useOverwriteFileMutation,
  useListVersionsQuery,
  useGetVersionDownloadLinkMutation,
  useGetPublicLinkMutation,
} = itemApi;
