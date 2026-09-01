import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { Mock } from "vitest";
import { httpMethods } from "../../../../libs/httpMethods";
import type { ItemSummary } from "../../../../store/types/item";
import { mockApiError, mockApiResponse } from "../../../../testUtils/mockHttp";
import { renderWithProviders } from "../../../../testUtils/renderWithProviders";
import { LockedItemCard } from "./LockedItemCard";

vi.mock("../../../../libs/httpMethods", () => ({
  httpMethods: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), del: vi.fn() },
}));

const LOCKED_ITEM: ItemSummary = {
  id: 7,
  categoryId: 1,
  type: "note",
  name: "Zablokowana notatka",
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
  locked: true,
  snippet: null,
};

describe("LockedItemCard", () => {
  afterEach(() => {
    vi.clearAllMocks();
  });

  it("unlocks the item after a correct key is submitted", async () => {
    (httpMethods.post as Mock).mockReturnValue(
      mockApiResponse({ resource: "item-key:7:v1:u1", expires: 9_999_999_999, signature: "sig" }),
    );

    renderWithProviders(<LockedItemCard item={LOCKED_ITEM} />);
    const user = userEvent.setup();

    await user.type(screen.getByLabelText("Klucz"), "correct-key");
    await user.click(screen.getByRole("button", { name: "Odblokuj" }));

    expect(await screen.findByText("Odblokowano.")).toBeInTheDocument();
    expect(httpMethods.post).toHaveBeenCalledWith("/api/items/7/unlock", { key: "correct-key" }, { params: undefined });
    // Input clears back to empty after a successful unlock.
    expect(screen.getByLabelText("Klucz")).toHaveValue("");
  });

  it("shows an error toast and keeps the field filled when the key is wrong", async () => {
    (httpMethods.post as Mock).mockImplementation(() =>
      mockApiError(401, {
        status: 401,
        title: "Unauthorized",
        detail: "",
        context: { uuid: "b3d2f6a1-7c4e-4f6b-8a99-0d1e2c3b4a55" },
      }),
    );

    renderWithProviders(<LockedItemCard item={LOCKED_ITEM} />);
    const user = userEvent.setup();

    await user.type(screen.getByLabelText("Klucz"), "wrong-key");
    await user.click(screen.getByRole("button", { name: "Odblokuj" }));

    expect(await screen.findByText("Nieprawidłowy klucz.")).toBeInTheDocument();
    expect(screen.getByLabelText("Klucz")).toHaveValue("wrong-key");
  });
});
