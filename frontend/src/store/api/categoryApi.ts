import { createApi } from "@reduxjs/toolkit/query/react";
import { ApiEndpoints } from "../../libs/apiEndpoints";
import { axiosBaseQuery } from "./baseQuery";

export interface Category {
  id: number;
  name: string;
  parentId: number | null;
  // Czy ta kategoria ma ustawiony własny klucz dostępu (AccessKeyPanel
  // pokazuje "Ustaw klucz" albo "Zmień/Usuń klucz" w zależności od tego).
  hasAccessKey: boolean;
}

interface CreateCategoryRequest {
  name: string;
  parentId: number | null;
}

export const categoryApi = createApi({
  reducerPath: "categoryApi",
  baseQuery: axiosBaseQuery(),
  tagTypes: ["Category"],
  endpoints: (builder) => ({
    listCategories: builder.query<Category[], void>({
      query: () => ({ url: ApiEndpoints.CATEGORIES, method: "GET" }),
      providesTags: ["Category"],
    }),
    // CategoriesPage's "Dodaj kategorię"/"Dodaj podkategorię".
    // Depth (kategoria główna + jedna podkategoria) is enforced by
    // CategoryForm never offering a parent past a root category, backed up
    // by CategoryService::create() rejecting it server-side either way.
    createCategory: builder.mutation<Category, CreateCategoryRequest>({
      query: (body) => ({ url: ApiEndpoints.CATEGORIES, method: "POST", data: body }),
      invalidatesTags: ["Category"],
    }),
  }),
});

export const { useListCategoriesQuery, useCreateCategoryMutation } = categoryApi;
