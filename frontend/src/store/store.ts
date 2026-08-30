import { configureStore } from "@reduxjs/toolkit";
import { authApi } from "./api/authApi";
import { categoryApi } from "./api/categoryApi";
import { itemApi } from "./api/itemApi";
import { sessionApi } from "./api/sessionApi";

export const store = configureStore({
  reducer: {
    [authApi.reducerPath]: authApi.reducer,
    [sessionApi.reducerPath]: sessionApi.reducer,
    [itemApi.reducerPath]: itemApi.reducer,
    [categoryApi.reducerPath]: categoryApi.reducer,
  },
  devTools: import.meta.env.MODE !== "production",
  middleware: (getDefaultMiddleware) =>
    getDefaultMiddleware().concat(
      authApi.middleware,
      sessionApi.middleware,
      itemApi.middleware,
      categoryApi.middleware,
    ),
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;
