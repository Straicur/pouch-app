import { createApi } from "@reduxjs/toolkit/query/react";
import { ApiEndpoints } from "../../libs/apiEndpoints";
import { axiosBaseQuery } from "./baseQuery";

export interface TagResource {
  id: number;
  name: string;
}

interface RenameTagRequest {
  id: number;
  name: string;
}

// listTags (GET /api/tags) is just the list of tag names currently in use —
// for the tag-filter/autocomplete UI, unchanged. listAllTags/create/rename/
// deleteTag (GET /api/tags/all + CRUD) back the tag-management page instead,
// so they share a tag type ("Tag") the read-only listTags doesn't
// participate in — a brand-new tag only shows up in the filter/autocomplete
// on its next unrelated refetch, same as before this page existed.
export const tagApi = createApi({
  reducerPath: "tagApi",
  baseQuery: axiosBaseQuery(),
  tagTypes: ["Tag", "TagResource"],
  endpoints: (builder) => ({
    listTags: builder.query<string[], void>({
      query: () => ({ url: ApiEndpoints.TAGS, method: "GET" }),
      providesTags: ["Tag"],
    }),
    listAllTags: builder.query<TagResource[], void>({
      query: () => ({ url: ApiEndpoints.TAGS_ALL, method: "GET" }),
      providesTags: ["TagResource"],
    }),
    createTag: builder.mutation<TagResource, string>({
      query: (name) => ({ url: ApiEndpoints.TAGS, method: "POST", data: { name } }),
      invalidatesTags: ["TagResource"],
    }),
    renameTag: builder.mutation<TagResource, RenameTagRequest>({
      query: ({ id, name }) => ({ url: ApiEndpoints.TAG_RENAME(id), method: "PATCH", data: { name } }),
      invalidatesTags: ["TagResource"],
    }),
    deleteTag: builder.mutation<void, number>({
      query: (id) => ({ url: ApiEndpoints.TAG(id), method: "DELETE" }),
      invalidatesTags: ["TagResource"],
    }),
  }),
});

export const {
  useListTagsQuery,
  useListAllTagsQuery,
  useCreateTagMutation,
  useRenameTagMutation,
  useDeleteTagMutation,
} = tagApi;
