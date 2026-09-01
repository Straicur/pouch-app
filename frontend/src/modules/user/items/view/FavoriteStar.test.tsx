import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { Mock } from "vitest";
import { httpMethods } from "../../../../libs/httpMethods";
import type { ItemDetail } from "../../../../store/types/item";
import { mockApiResponse } from "../../../../testUtils/mockHttp";
import { renderWithProviders } from "../../../../testUtils/renderWithProviders";
import { FavoriteStar } from "./FavoriteStar";

vi.mock("../../../../libs/httpMethods", () => ({
  httpMethods: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), del: vi.fn() },
}));

function buildItem(overrides: Partial<ItemDetail> = {}): ItemDetail {
  return {
    id: 5,
    categoryId: 1,
    type: "note",
    name: "Item",
    processingStatus: "completed",
    processingError: null,
    originalFilename: null,
    mimeType: null,
    size: null,
    hasThumbnail: false,
    url: null,
    pageTitle: null,
    pageDescription: null,
    noteContent: null,
    favorite: false,
    tags: [],
    keepForever: true,
    expiresAt: null,
    trashedAt: null,
    createdAt: "2026-01-01T00:00:00Z",
    extractedText: null,
    hasAccessKey: false,
    ...overrides,
  };
}

describe("FavoriteStar", () => {
  afterEach(() => {
    vi.clearAllMocks();
  });

  it("marks an item as favorite and reflects it as pressed", async () => {
    (httpMethods.put as Mock).mockReturnValue(mockApiResponse(buildItem({ favorite: true })));

    renderWithProviders(<FavoriteStar itemId={5} favorite={false} />);
    const user = userEvent.setup();

    const star = screen.getByRole("button", { name: "Dodaj do ulubionych" });
    expect(star).toHaveAttribute("aria-pressed", "false");

    await user.click(star);

    expect(httpMethods.put).toHaveBeenCalledWith("/api/items/5/favorite", undefined, { params: undefined });
  });

  it("unmarks a favorite item via the delete endpoint", async () => {
    (httpMethods.del as Mock).mockReturnValue(mockApiResponse(buildItem({ favorite: false })));

    renderWithProviders(<FavoriteStar itemId={5} favorite />);
    const user = userEvent.setup();

    const star = screen.getByRole("button", { name: "Usuń z ulubionych" });
    expect(star).toHaveAttribute("aria-pressed", "true");

    await user.click(star);

    expect(httpMethods.del).toHaveBeenCalledWith("/api/items/5/favorite", { params: undefined });
  });

  it("toggles via the keyboard (Enter/Space), not just click", async () => {
    (httpMethods.put as Mock).mockReturnValue(mockApiResponse(buildItem({ favorite: true })));

    renderWithProviders(<FavoriteStar itemId={5} favorite={false} />);
    const star = screen.getByRole("button", { name: "Dodaj do ulubionych" });
    star.focus();

    const user = userEvent.setup();
    await user.keyboard("{Enter}");

    expect(httpMethods.put).toHaveBeenCalledWith("/api/items/5/favorite", undefined, { params: undefined });
  });
});
