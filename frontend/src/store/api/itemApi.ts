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
  keepForever: boolean;
  expiresAt: string | null;
  trashedAt: string | null;
  createdAt: string;
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

export const itemApi = createApi({
  reducerPath: "itemApi",
  baseQuery: axiosBaseQuery(),
  tagTypes: ["Item"],
  endpoints: (builder) => ({
    listItems: builder.query<Item[], { categoryId?: number } | undefined>({
      query: (args) => ({
        url: ApiEndpoints.ITEMS,
        method: "GET",
        params: undefined !== args?.categoryId ? { categoryId: args.categoryId } : undefined,
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
  }),
});

export const {
  useListItemsQuery,
  useGetItemThumbnailLinkMutation,
  useGetItemDownloadLinkMutation,
  useCreateNoteMutation,
  useUpdateNoteMutation,
} = itemApi;
