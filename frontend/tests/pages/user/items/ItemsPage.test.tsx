import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { Mock } from "vitest";
import { httpMethods } from "../../../../src/libs/httpMethods";
import { ItemsPage } from "../../../../src/pages/user/items/ItemsPage";
import type { ItemListResult, ItemSummary } from "../../../../src/store/types/item";
import { mockApiResponse } from "../../../testUtils/mockHttp";
import { renderWithProviders } from "../../../testUtils/renderWithProviders";

vi.mock("../../../../src/libs/httpMethods", () => ({
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

describe("ItemsPage — RTK Query cache after a mutation", () => {
  afterEach(() => {
    vi.clearAllMocks();
  });

  it("refetches the item list after marking an item as favorite, without a manual reload", async () => {
    let listCallCount = 0;
    (httpMethods.get as Mock).mockImplementation((url: string) => {
      if (url === "/api/items") {
        listCallCount += 1;
        const result: ItemListResult = {
          items: [buildSummary({ favorite: listCallCount > 1 })],
          total: 1,
          page: 1,
          pageSize: 20,
        };

        return mockApiResponse(result);
      }

      // categories/tags for ItemFilters — irrelevant to this scenario.
      return mockApiResponse([]);
    });
    (httpMethods.put as Mock).mockReturnValue(mockApiResponse(buildSummary({ favorite: true })));

    renderWithProviders(<ItemsPage />);
    const user = userEvent.setup();

    const star = await screen.findByRole("button", { name: "Dodaj do ulubionych" });
    expect(listCallCount).toBe(1);

    await user.click(star);

    await waitFor(() => {
      expect(listCallCount).toBe(2);
    });
    expect(await screen.findByRole("button", { name: "Usuń z ulubionych" })).toBeInTheDocument();
  });
});
