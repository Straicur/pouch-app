import { configureStore } from "@reduxjs/toolkit";
import type { RenderOptions } from "@testing-library/react";
import { render } from "@testing-library/react";
import type { PropsWithChildren, ReactElement } from "react";
import { Provider } from "react-redux";
import { MemoryRouter } from "react-router-dom";
import { ToastContainer } from "react-toastify";
import { accessKeyApi } from "../store/api/accessKeyApi";
import { accountApi } from "../store/api/accountApi";
import { adminApi } from "../store/api/adminApi";
import { authApi } from "../store/api/authApi";
import { categoryApi } from "../store/api/categoryApi";
import { itemApi } from "../store/api/itemApi";
import { sessionApi } from "../store/api/sessionApi";
import { tagApi } from "../store/api/tagApi";
import { userApi } from "../store/api/userApi";

// Mirrors store/store.ts's reducer/middleware setup, but built fresh per
// call — reusing the app's singleton store would leak RTK Query cache
// between tests (a query fetched in one test would still be "fulfilled" in
// the next, its mocked httpMethods call never re-firing).
function createTestStore() {
  return configureStore({
    reducer: {
      [authApi.reducerPath]: authApi.reducer,
      [sessionApi.reducerPath]: sessionApi.reducer,
      [itemApi.reducerPath]: itemApi.reducer,
      [categoryApi.reducerPath]: categoryApi.reducer,
      [tagApi.reducerPath]: tagApi.reducer,
      [accessKeyApi.reducerPath]: accessKeyApi.reducer,
      [adminApi.reducerPath]: adminApi.reducer,
      [userApi.reducerPath]: userApi.reducer,
      [accountApi.reducerPath]: accountApi.reducer,
    },
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
        accountApi.middleware,
      ),
  });
}

export type TestStore = ReturnType<typeof createTestStore>;

export function renderWithProviders(ui: ReactElement, options?: Omit<RenderOptions, "wrapper">) {
  const store = createTestStore();

  // A wrapper (not manually nesting `ui` inside the providers) so the
  // returned `rerender(...)` re-renders through the same providers instead
  // of replacing them — RTL's rerender only re-invokes the `wrapper`, it
  // doesn't re-apply anything baked into the element tree at render() time.
  function Wrapper({ children }: PropsWithChildren) {
    return (
      <Provider store={store}>
        <MemoryRouter>
          {children}
          {/* Mirrors RootLayout — toastUtil calls need a mounted container to render anything. */}
          <ToastContainer />
        </MemoryRouter>
      </Provider>
    );
  }

  return {
    store,
    ...render(ui, { wrapper: Wrapper, ...options }),
  };
}
