import { createApi } from "@reduxjs/toolkit/query/react";
import { ApiEndpoints } from "../../lib/apiEndpoints";
import { axiosBaseQuery } from "./baseQuery";

export interface Category {
  id: number;
  name: string;
  parentId: number | null;
}

// Just enough to populate a category picker (e.g. NoteForm) — no tree
// navigation UI yet, that's its own future piece of work.
export const categoryApi = createApi({
  reducerPath: "categoryApi",
  baseQuery: axiosBaseQuery(),
  endpoints: (builder) => ({
    listCategories: builder.query<Category[], void>({
      query: () => ({ url: ApiEndpoints.CATEGORIES, method: "GET" }),
    }),
  }),
});

export const { useListCategoriesQuery } = categoryApi;
