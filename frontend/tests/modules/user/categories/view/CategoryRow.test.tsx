import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { Mock } from "vitest";
import { httpMethods } from "../../../../../src/libs/httpMethods";
import { CategoryRow } from "../../../../../src/modules/user/categories/view/CategoryRow";
import type { Category } from "../../../../../src/store/api/categoryApi";
import { mockApiError, mockApiResponse } from "../../../../testUtils/mockHttp";
import { renderWithProviders } from "../../../../testUtils/renderWithProviders";

vi.mock("../../../../../src/libs/httpMethods", () => ({
  httpMethods: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), del: vi.fn() },
}));

const DOCUMENTS: Category = { id: 1, name: "Dokumenty", parentId: null, hasAccessKey: false };
const RECEIPTS: Category = { id: 2, name: "Paragony", parentId: null, hasAccessKey: false };

describe("CategoryRow", () => {
  afterEach(() => {
    vi.clearAllMocks();
  });

  it("renames the category and closes the dialog on success", async () => {
    (httpMethods.patch as Mock).mockReturnValue(mockApiResponse({ ...DOCUMENTS, name: "Dokumenty ważne" }));

    renderWithProviders(<CategoryRow category={DOCUMENTS} subcategories={[]} />);
    const user = userEvent.setup();

    await user.click(screen.getByRole("button", { name: "Zmień nazwę" }));
    const input = await screen.findByLabelText("Nazwa");
    await user.clear(input);
    await user.type(input, "Dokumenty ważne");
    await user.click(screen.getByRole("button", { name: "Zapisz" }));

    await waitFor(() => {
      expect(httpMethods.patch).toHaveBeenCalledWith(
        "/api/categories/1/rename",
        { name: "Dokumenty ważne" },
        { params: undefined },
      );
    });
    await waitFor(() => {
      expect(screen.queryByLabelText("Nazwa")).not.toBeInTheDocument();
    });
  });

  it("shows an error toast and keeps the dialog open when rename fails", async () => {
    (httpMethods.patch as Mock).mockImplementation(() => mockApiError(500, {}));

    renderWithProviders(<CategoryRow category={DOCUMENTS} subcategories={[]} />);
    const user = userEvent.setup();

    await user.click(screen.getByRole("button", { name: "Zmień nazwę" }));
    await user.click(screen.getByRole("button", { name: "Zapisz" }));

    expect(await screen.findByText("Nie udało się zmienić nazwy kategorii.")).toBeInTheDocument();
    expect(screen.getByLabelText("Nazwa")).toBeInTheDocument();
  });

  it("moves the category to another root and closes the dialog on success", async () => {
    (httpMethods.get as Mock).mockReturnValue(mockApiResponse([DOCUMENTS, RECEIPTS]));
    (httpMethods.patch as Mock).mockReturnValue(mockApiResponse({ ...DOCUMENTS, parentId: RECEIPTS.id }));

    renderWithProviders(<CategoryRow category={DOCUMENTS} subcategories={[]} />);
    const user = userEvent.setup();

    await user.click(screen.getByRole("button", { name: "Przenieś" }));
    const select = await screen.findByLabelText("Nowa kategoria nadrzędna");
    await waitFor(() => {
      expect(screen.getByRole("option", { name: "Paragony" })).toBeInTheDocument();
    });
    await user.selectOptions(select, "2");
    await user.click(screen.getByRole("button", { name: "Przenieś" }));

    await waitFor(() => {
      expect(httpMethods.patch).toHaveBeenCalledWith(
        "/api/categories/1/move",
        { parentId: RECEIPTS.id },
        { params: undefined },
      );
    });
    await waitFor(() => {
      expect(screen.queryByLabelText("Nowa kategoria nadrzędna")).not.toBeInTheDocument();
    });
  });

  it("shows an error toast when moving the category fails", async () => {
    (httpMethods.get as Mock).mockReturnValue(mockApiResponse([DOCUMENTS, RECEIPTS]));
    (httpMethods.patch as Mock).mockImplementation(() => mockApiError(400, {}));

    renderWithProviders(<CategoryRow category={DOCUMENTS} subcategories={[]} />);
    const user = userEvent.setup();

    await user.click(screen.getByRole("button", { name: "Przenieś" }));
    await screen.findByLabelText("Nowa kategoria nadrzędna");
    await user.click(screen.getByRole("button", { name: "Przenieś" }));

    expect(await screen.findByText("Nie udało się przenieść kategorii.")).toBeInTheDocument();
  });
});
