import { configureStore } from "@reduxjs/toolkit";
import { accessKeyApi } from "./api/accessKeyApi";
import { adminApi } from "./api/adminApi";
import { authApi } from "./api/authApi";
import { categoryApi } from "./api/categoryApi";
import { itemApi } from "./api/itemApi";
import { sessionApi } from "./api/sessionApi";
import { tagApi } from "./api/tagApi";
import { userApi } from "./api/userApi";

export const store = configureStore({
  reducer: {
    [authApi.reducerPath]: authApi.reducer,
    [sessionApi.reducerPath]: sessionApi.reducer,
    [itemApi.reducerPath]: itemApi.reducer,
    [categoryApi.reducerPath]: categoryApi.reducer,
    [tagApi.reducerPath]: tagApi.reducer,
    [accessKeyApi.reducerPath]: accessKeyApi.reducer,
    [adminApi.reducerPath]: adminApi.reducer,
    [userApi.reducerPath]: userApi.reducer,
  },
  devTools: import.meta.env.MODE !== "production",
  middleware: (getDefaultMiddleware) =>
    getDefaultMiddleware().concat(
      authApi.middleware,
      sessionApi.middleware,
      itemApi.middleware,
      categoryApi.middleware,
      tagApi.middleware,
      accessKeyApi.middleware,
      adminApi.middleware,
      userApi.middleware,
    ),
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;
