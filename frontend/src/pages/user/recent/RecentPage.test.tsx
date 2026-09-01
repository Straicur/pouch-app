import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { Mock } from "vitest";
import { httpMethods } from "../../../libs/httpMethods";
import type { ItemListResult, ItemSummary } from "../../../store/types/item";
import { mockApiResponse } from "../../../testUtils/mockHttp";
import { renderWithProviders } from "../../../testUtils/renderWithProviders";
import { RecentPage } from "./RecentPage";

vi.mock("../../../libs/httpMethods", () => ({
  httpMethods: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), del: vi.fn() },
}));

function buildSummary(overrides: Partial<ItemSummary> = {}): ItemSummary {
  return {
    id: 1,
    categoryId: 1,
    type: "note",
    name: "Notatka",
    processingStatus: "completed",
    processingError: null,
    originalFilename: null,
    mimeType: null,
    size: null,
    hasThumbnail: false,
    url: null,
    pageTitle: null,
    pageDescription: null,
    noteContent: "treść",
    favorite: false,
    tags: [],
    keepForever: true,
    expiresAt: null,
    trashedAt: null,
    createdAt: "2026-01-01T00:00:00Z",
    locked: false,
    snippet: null,
    ...overrides,
  };
}

describe("RecentPage", () => {
  afterEach(() => {
    vi.clearAllMocks();
  });

  it("fetches the item list with no filters, newest first as returned by the backend", async () => {
    (httpMethods.get as Mock).mockImplementation((url: string) => {
      if (url === "/api/items") {
        return mockApiResponse<ItemListResult>({
          items: [buildSummary({ id: 1, name: "Najnowszy" }), buildSummary({ id: 2, name: "Starszy" })],
          total: 2,
          page: 1,
          pageSize: 24,
        });
      }

      return mockApiResponse([]);
    });

    renderWithProviders(<RecentPage />);

    expect(await screen.findByText("Najnowszy")).toBeInTheDocument();
    expect(screen.getByText("Starszy")).toBeInTheDocument();
    expect(httpMethods.get).toHaveBeenCalledWith("/api/items", {
      params: {
        categoryIds: undefined,
        favorite: undefined,
        tags: undefined,
        q: undefined,
        page: 1,
        pageSize: 24,
      },
    });
  });

  it("shows an empty state when there are no items yet", async () => {
    (httpMethods.get as Mock).mockImplementation((url: string) => {
      if (url === "/api/items") {
        return mockApiResponse<ItemListResult>({ items: [], total: 0, page: 1, pageSize: 24 });
      }

      return mockApiResponse([]);
    });

    renderWithProviders(<RecentPage />);

    expect(await screen.findByText("Brak dodanych itemów.")).toBeInTheDocument();
  });

  it("pages through the list without carrying any filter", async () => {
    (httpMethods.get as Mock).mockImplementation((url: string, config: { params?: Record<string, unknown> }) => {
      if (url !== "/api/items") {
        return mockApiResponse([]);
      }

      const page = (config?.params?.page as number | undefined) ?? 1;

      return mockApiResponse<ItemListResult>({
        items: [buildSummary({ id: page, name: `Item strony ${page}` })],
        total: 30,
        page,
        pageSize: 24,
      });
    });

    renderWithProviders(<RecentPage />);
    const user = userEvent.setup();

    expect(await screen.findByText("Item strony 1")).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Następna" }));

    expect(await screen.findByText("Item strony 2")).toBeInTheDocument();
    await waitFor(() => {
      expect(httpMethods.get).toHaveBeenCalledWith("/api/items", {
        params: {
          categoryIds: undefined,
          favorite: undefined,
          tags: undefined,
          q: undefined,
          page: 2,
          pageSize: 24,
        },
      });
    });
  });
});
