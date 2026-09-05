import { screen } from "@testing-library/react";
import type { AxiosResponse, InternalAxiosRequestConfig } from "axios";
import { ExceptionUuid } from "../../src/libs/apiError";
import { httpClient } from "../../src/libs/httpClient";
import { navigationUtil } from "../../src/libs/navigationUtil";
import { RecentPage } from "../../src/pages/user/recent/RecentPage";
import { renderWithProviders } from "../testUtils/renderWithProviders";

// Deliberately NOT mocking httpMethods here (every other page test does,
// see RecentPage.test.tsx) — the thing under test is httpClient's own
// response interceptor, which mocking httpMethods would bypass entirely.
// Swapping the adapter instead keeps the real interceptor chain in the loop
// while still avoiding a real network call.
function respondWith(status: number, data: unknown) {
  httpClient.defaults.adapter = (config: InternalAxiosRequestConfig): Promise<AxiosResponse> => {
    if (status >= 400) {
      return Promise.reject({
        isAxiosError: true,
        message: `Request failed with status code ${status}`,
        config,
        response: { status, data, statusText: "", headers: {}, config },
      });
    }

    return Promise.resolve({ data, status, statusText: "OK", headers: {}, config });
  };
}

describe("httpClient — technical break redirect", () => {
  const originalAdapter = httpClient.defaults.adapter;

  afterEach(() => {
    httpClient.defaults.adapter = originalAdapter;
    vi.restoreAllMocks();
  });

  it("redirects a logged-in user's page away to /technical-break instead of letting them stay on it", async () => {
    const navigateSpy = vi.spyOn(navigationUtil, "navigate").mockImplementation(() => {});
    respondWith(503, {
      status: 503,
      title: "Service Unavailable",
      detail: "Wracamy o 20:00",
      context: { uuid: ExceptionUuid.TECHNICAL_BREAK },
    });

    renderWithProviders(<RecentPage />);

    await vi.waitFor(() => {
      expect(navigateSpy).toHaveBeenCalledWith("/technical-break", {
        replace: true,
        state: { message: "Wracamy o 20:00" },
      });
    });

    // The page itself never got real data to show — proof the redirect is
    // what handles this, not RecentPage rendering an error state of its own.
    expect(screen.queryByText("Brak dodanych itemów.")).not.toBeInTheDocument();
  });

  it("leaves an ordinary error alone (no redirect) when the break isn't the cause", async () => {
    const navigateSpy = vi.spyOn(navigationUtil, "navigate").mockImplementation(() => {});
    respondWith(500, {
      status: 500,
      title: "Internal Server",
      detail: "Wystąpił błąd wewnętrzny serwera.",
      context: { uuid: ExceptionUuid.INTERNAL_SERVER },
    });

    renderWithProviders(<RecentPage />);

    expect(await screen.findByText("Nie udało się pobrać itemów.")).toBeInTheDocument();
    expect(navigateSpy).not.toHaveBeenCalled();
  });
});
