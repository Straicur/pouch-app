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

interface RenameCategoryRequest {
  id: number;
  name: string;
}

interface MoveCategoryRequest {
  id: number;
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
    renameCategory: builder.mutation<Category, RenameCategoryRequest>({
      query: ({ id, name }) => ({ url: ApiEndpoints.CATEGORY_RENAME(id), method: "PATCH", data: { name } }),
      invalidatesTags: ["Category"],
    }),
    // parentId: null promotes the category to root. CategoryService::move()
    // rejects (400) moving a category with its own children under a parent
    // (would put grandchildren past the max-depth-2 limit) — surfaced to the
    // user as a toast, not prevented client-side, since it depends on
    // server-side knowledge of the whole subtree.
    moveCategory: builder.mutation<Category, MoveCategoryRequest>({
      query: ({ id, parentId }) => ({ url: ApiEndpoints.CATEGORY_MOVE(id), method: "PATCH", data: { parentId } }),
      invalidatesTags: ["Category"],
    }),
  }),
});

export const { useListCategoriesQuery, useCreateCategoryMutation, useRenameCategoryMutation, useMoveCategoryMutation } =
  categoryApi;
