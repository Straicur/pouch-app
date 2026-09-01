import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { Mock } from "vitest";
import { httpMethods } from "../../../libs/httpMethods";
import type { ItemListResult, ItemSummary } from "../../../store/types/item";
import { mockApiError, mockApiResponse } from "../../../testUtils/mockHttp";
import { renderWithProviders } from "../../../testUtils/renderWithProviders";
import { TrashPage } from "./TrashPage";

vi.mock("../../../libs/httpMethods", () => ({
  httpMethods: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), del: vi.fn() },
}));

function buildTrashedSummary(overrides: Partial<ItemSummary> = {}): ItemSummary {
  return {
    id: 1,
    categoryId: 1,
    type: "note",
    name: "Skasowana notatka",
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
    keepForever: false,
    expiresAt: null,
    trashedAt: "2026-01-05T12:00:00Z",
    createdAt: "2026-01-01T00:00:00Z",
    locked: false,
    snippet: null,
    ...overrides,
  };
}

function mockTrashList(result: ItemListResult) {
  (httpMethods.get as Mock).mockImplementation((url: string) => {
    if (url === "/api/items/trash") {
      return mockApiResponse(result);
    }

    return mockApiResponse([]);
  });
}

describe("TrashPage", () => {
  afterEach(() => {
    vi.clearAllMocks();
  });

  it("lists trashed items", async () => {
    mockTrashList({ items: [buildTrashedSummary()], total: 1, page: 1, pageSize: 24 });

    renderWithProviders(<TrashPage />);

    expect(await screen.findByText("Skasowana notatka")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Przywróć" })).toBeInTheDocument();
  });

  it("shows an empty state when there is nothing in the trash", async () => {
    mockTrashList({ items: [], total: 0, page: 1, pageSize: 24 });

    renderWithProviders(<TrashPage />);

    expect(await screen.findByText("Kosz jest pusty.")).toBeInTheDocument();
  });

  it("restores an item and removes it from the list after the cache invalidation", async () => {
    let listCallCount = 0;
    (httpMethods.get as Mock).mockImplementation((url: string) => {
      if (url === "/api/items/trash") {
        listCallCount += 1;

        return mockApiResponse<ItemListResult>({
          items: listCallCount > 1 ? [] : [buildTrashedSummary()],
          total: listCallCount > 1 ? 0 : 1,
          page: 1,
          pageSize: 24,
        });
      }

      return mockApiResponse([]);
    });
    (httpMethods.patch as Mock).mockReturnValue(mockApiResponse(buildTrashedSummary({ trashedAt: null })));

    renderWithProviders(<TrashPage />);
    const user = userEvent.setup();

    await screen.findByText("Skasowana notatka");
    await user.click(screen.getByRole("button", { name: "Przywróć" }));

    await waitFor(() => {
      expect(httpMethods.patch).toHaveBeenCalledWith("/api/items/1/restore", undefined, { params: undefined });
    });
    expect(await screen.findByText("Kosz jest pusty.")).toBeInTheDocument();
  });

  it("shows an error toast when restoring fails", async () => {
    mockTrashList({ items: [buildTrashedSummary()], total: 1, page: 1, pageSize: 24 });
    (httpMethods.patch as Mock).mockImplementation(() => mockApiError(404, {}));

    renderWithProviders(<TrashPage />);
    const user = userEvent.setup();

    await screen.findByText("Skasowana notatka");
    await user.click(screen.getByRole("button", { name: "Przywróć" }));

    expect(await screen.findByText("Nie udało się przywrócić itemu.")).toBeInTheDocument();
  });
});
