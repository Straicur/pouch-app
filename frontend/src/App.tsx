import { Provider } from "react-redux";
import { RouterProvider } from "react-router-dom";
import { ErrorBoundary } from "./providers/ErrorBoundary";
import { router } from "./routes";
import { store } from "./store/store";

function App() {
  return (
    <ErrorBoundary>
      <Provider store={store}>
        <RouterProvider router={router} />
      </Provider>
    </ErrorBoundary>
  );
}

export default App;
