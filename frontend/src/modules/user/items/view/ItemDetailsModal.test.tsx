import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { Mock } from "vitest";
import { httpMethods } from "../../../../libs/httpMethods";
import type { Category } from "../../../../store/api/categoryApi";
import type { ItemDetail } from "../../../../store/types/item";
import { mockApiError, mockApiResponse } from "../../../../testUtils/mockHttp";
import { renderWithProviders } from "../../../../testUtils/renderWithProviders";
import { ItemDetailsModal } from "./ItemDetailsModal";

vi.mock("../../../../libs/httpMethods", () => ({
  httpMethods: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), del: vi.fn() },
}));

const CATEGORIES: Category[] = [{ id: 1, name: "Dokumenty", parentId: null, hasAccessKey: false }];

function buildItem(overrides: Partial<ItemDetail> = {}): ItemDetail {
  return {
    id: 42,
    categoryId: 1,
    type: "note",
    name: "Moja notatka",
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
    extractedText: null,
    hasAccessKey: false,
    ...overrides,
  };
}

function mockGet(item: ItemDetail | (() => Promise<unknown>)) {
  (httpMethods.get as Mock).mockImplementation((url: string) => {
    if (url === "/api/categories") {
      return mockApiResponse(CATEGORIES);
    }
    if (url.startsWith("/api/items/")) {
      return "function" === typeof item ? item() : mockApiResponse(item);
    }

    return mockApiResponse([]);
  });
}

describe("ItemDetailsModal", () => {
  afterEach(() => {
    vi.clearAllMocks();
  });

  it("loads and shows the item's details when opened", async () => {
    mockGet(buildItem({ name: "Ważna notatka" }));

    renderWithProviders(<ItemDetailsModal itemId={42} open onClose={vi.fn()} />);

    expect(await screen.findByText("Ważna notatka")).toBeInTheDocument();
  });

  it("shows an error with a retry action when the fetch fails", async () => {
    let callCount = 0;
    mockGet(() => {
      callCount += 1;
      return 1 === callCount ? mockApiError(500, {}) : mockApiResponse(buildItem({ name: "Po ponowieniu" }));
    });

    renderWithProviders(<ItemDetailsModal itemId={42} open onClose={vi.fn()} />);
    const user = userEvent.setup();

    expect(await screen.findByText("Nie udało się wczytać szczegółów itemu.")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Ponów" }));

    expect(await screen.findByText("Po ponowieniu")).toBeInTheDocument();
  });

  it("deletes the item only after the confirmation dialog is accepted", async () => {
    mockGet(buildItem());
    (httpMethods.del as Mock).mockReturnValue(mockApiResponse(undefined));
    const onClose = vi.fn();

    renderWithProviders(<ItemDetailsModal itemId={42} open onClose={onClose} />);
    const user = userEvent.setup();

    await screen.findByText("Moja notatka");
    await user.click(screen.getByRole("button", { name: "Usuń" }));

    // The confirm dialog's own action button carries the same label ("Usuń")
    // as the button that opened it — findAllByRole below is deliberate.
    expect(await screen.findByText("Usunąć ten item?")).toBeInTheDocument();
    expect(httpMethods.del).not.toHaveBeenCalled();

    await user.click(screen.getByRole("button", { name: "Anuluj" }));
    await waitFor(() => {
      expect(screen.queryByText("Usunąć ten item?")).not.toBeInTheDocument();
    });
    expect(httpMethods.del).not.toHaveBeenCalled();

    await user.click(screen.getByRole("button", { name: "Usuń" }));
    const confirmButtons = await screen.findAllByRole("button", { name: "Usuń" });
    await user.click(confirmButtons[confirmButtons.length - 1]);

    await waitFor(() => {
      expect(httpMethods.del).toHaveBeenCalledWith("/api/items/42", { params: undefined });
    });
    expect(onClose).toHaveBeenCalled();
  });
});
