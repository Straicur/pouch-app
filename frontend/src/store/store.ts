import { configureStore } from "@reduxjs/toolkit";
import { authApi } from "./api/authApi";
import { sessionApi } from "./api/sessionApi";

export const store = configureStore({
  reducer: {
    [authApi.reducerPath]: authApi.reducer,
    [sessionApi.reducerPath]: sessionApi.reducer,
  },
  devTools: import.meta.env.MODE !== "production",
  middleware: (getDefaultMiddleware) => getDefaultMiddleware().concat(authApi.middleware, sessionApi.middleware),
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;
