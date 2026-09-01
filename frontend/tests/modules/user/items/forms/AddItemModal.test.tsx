import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { Mock } from "vitest";
import { httpMethods } from "../../../../../src/libs/httpMethods";
import { AddItemModal } from "../../../../../src/modules/user/items/forms/AddItemModal";
import type { Category } from "../../../../../src/store/api/categoryApi";
import type { ItemDetail } from "../../../../../src/store/types/item";
import { mockApiResponse } from "../../../../testUtils/mockHttp";
import { renderWithProviders } from "../../../../testUtils/renderWithProviders";

vi.mock("../../../../../src/libs/httpMethods", () => ({
  httpMethods: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), del: vi.fn() },
}));

const CATEGORIES: Category[] = [{ id: 1, name: "Dokumenty", parentId: null, hasAccessKey: false }];

function buildItem(overrides: Partial<ItemDetail> = {}): ItemDetail {
  return {
    id: 9,
    categoryId: 1,
    type: "note",
    name: "",
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

describe("AddItemModal", () => {
  beforeEach(() => {
    (httpMethods.get as Mock).mockImplementation((url: string) => {
      return mockApiResponse(url === "/api/categories" ? CATEGORIES : []);
    });
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  it("submits a note once a category has loaded and content is filled in", async () => {
    (httpMethods.post as Mock).mockReturnValue(mockApiResponse(buildItem({ type: "note" })));
    const onClose = vi.fn();

    renderWithProviders(<AddItemModal open onClose={onClose} />);
    const user = userEvent.setup();

    await user.selectOptions(screen.getByLabelText("Typ"), "note");
    // Category select only gets a valid value once useListCategoriesQuery resolves.
    await waitFor(() => {
      expect(screen.getByLabelText("Kategoria")).toHaveValue("1");
    });

    await user.type(screen.getByLabelText("Treść (markdown)"), "Treść notatki");
    await user.click(screen.getByRole("button", { name: "Dodaj notatkę" }));

    await waitFor(() => {
      expect(httpMethods.post).toHaveBeenCalledWith(
        "/api/items/notes",
        expect.objectContaining({ categoryId: 1, content: "Treść notatki", keepForever: true }),
        { params: undefined },
      );
    });
    expect(onClose).toHaveBeenCalled();
  });

  it("submits a file upload as multipart form data", async () => {
    (httpMethods.post as Mock).mockReturnValue(mockApiResponse(buildItem({ type: "file" })));
    const onClose = vi.fn();

    renderWithProviders(<AddItemModal open onClose={onClose} />);
    const user = userEvent.setup();

    await waitFor(() => {
      expect(screen.getByLabelText("Kategoria")).toHaveValue("1");
    });

    // The dialog renders through a Headless UI portal appended to
    // document.body, outside RTL's own `container` — query the whole document.
    const fileInput = document.querySelector<HTMLInputElement>('input[type="file"]');
    if (null === fileInput) {
      throw new Error("file input not found");
    }
    const file = new File(["hello"], "notes.txt", { type: "text/plain" });
    await user.upload(fileInput, file);

    await user.click(screen.getByRole("button", { name: "Wyślij plik" }));

    await waitFor(() => {
      expect(httpMethods.post).toHaveBeenCalledWith("/api/items/files", expect.any(FormData), { params: undefined });
    });
    const formData = (httpMethods.post as Mock).mock.calls[0][1] as FormData;
    expect(formData.get("file")).toBe(file);
    expect(formData.get("categoryId")).toBe("1");
    expect(onClose).toHaveBeenCalled();
  });

  it("shows a validation error and does not submit when no file is chosen", async () => {
    renderWithProviders(<AddItemModal open onClose={vi.fn()} />);

    await waitFor(() => {
      expect(screen.getByLabelText("Kategoria")).toHaveValue("1");
    });

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: "Wyślij plik" }));

    // "Wybierz plik" also labels the file-choice button — scope to the error slot.
    expect(await waitFor(() => document.querySelector('[data-slot="error"]'))).toHaveTextContent("Wybierz plik");
    expect(httpMethods.post).not.toHaveBeenCalled();
  });
});
