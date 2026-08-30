import { createApi } from "@reduxjs/toolkit/query/react";
import { ApiEndpoints } from "../../lib/apiEndpoints";
import { axiosBaseQuery } from "./baseQuery";

// Just the list of tag names currently in use — for the tag-filter/
// autocomplete UI. Tags have no CRUD of their own (see itemApi's
// updateTags — an item's own tag set is where they're actually managed).
// A brand-new tag name only shows up here on the next refetch (RTK Query
// tags don't cross API slices) — fine for an autocomplete list.
export const tagApi = createApi({
  reducerPath: "tagApi",
  baseQuery: axiosBaseQuery(),
  tagTypes: ["Tag"],
  endpoints: (builder) => ({
    listTags: builder.query<string[], void>({
      query: () => ({ url: ApiEndpoints.TAGS, method: "GET" }),
      providesTags: ["Tag"],
    }),
  }),
});

export const { useListTagsQuery } = tagApi;
