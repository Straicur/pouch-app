import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { Mock } from "vitest";
import { httpMethods } from "../../../../../src/libs/httpMethods";
import { VersionHistory } from "../../../../../src/modules/user/items/view/VersionHistory";
import type { ItemVersion } from "../../../../../src/store/types/item";
import { mockApiError, mockApiResponse } from "../../../../testUtils/mockHttp";
import { renderWithProviders } from "../../../../testUtils/renderWithProviders";

vi.mock("../../../../../src/libs/httpMethods", () => ({
  httpMethods: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), del: vi.fn() },
}));

const ONE_VERSION: ItemVersion[] = [
  {
    version: 1,
    originalFilename: "umowa-v1.pdf",
    mimeType: "application/pdf",
    size: 2048,
    createdAt: "2026-01-01T00:00:00Z",
  },
];

describe("VersionHistory", () => {
  afterEach(() => {
    vi.clearAllMocks();
  });

  it("shows an empty state when there is no previous version", async () => {
    (httpMethods.get as Mock).mockReturnValue(mockApiResponse([]));

    renderWithProviders(<VersionHistory itemId={5} />);

    expect(await screen.findByText("Brak wcześniejszych wersji.")).toBeInTheDocument();
  });

  it("lists previous versions and downloads a chosen one", async () => {
    (httpMethods.get as Mock).mockReturnValue(mockApiResponse(ONE_VERSION));
    (httpMethods.post as Mock).mockReturnValue(mockApiResponse({ url: "https://example.test/signed" }));
    const assignSpy = vi.fn();
    vi.spyOn(window, "location", "get").mockReturnValue({ assign: assignSpy } as unknown as Location);

    renderWithProviders(<VersionHistory itemId={5} />);

    expect(await screen.findByText("v1 — umowa-v1.pdf")).toBeInTheDocument();
    expect(screen.getByText("2.0 KB")).toBeInTheDocument();

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: "Pobierz tę wersję" }));

    await waitFor(() => {
      expect(httpMethods.post).toHaveBeenCalledWith("/api/items/5/versions/1/download-link", {}, { params: undefined });
    });
    await waitFor(() => {
      expect(assignSpy).toHaveBeenCalledWith("https://example.test/signed");
    });
  });

  it("uploads a new version and shows a success toast", async () => {
    (httpMethods.get as Mock).mockReturnValue(mockApiResponse([]));
    (httpMethods.post as Mock).mockReturnValue(mockApiResponse({ id: 5 }));

    renderWithProviders(<VersionHistory itemId={5} />);
    await screen.findByText("Brak wcześniejszych wersji.");

    const file = new File(["content"], "umowa-v2.pdf", { type: "application/pdf" });
    const input = document.querySelector('input[type="file"]');
    if (null === input) {
      throw new Error("file input not found");
    }
    const user = userEvent.setup();
    await user.upload(input as HTMLInputElement, file);

    expect(await screen.findByText("Plik nadpisany, poprzednia wersja trafiła do historii.")).toBeInTheDocument();
    expect(httpMethods.post).toHaveBeenCalledWith("/api/items/5/file", expect.any(FormData), { params: undefined });
  });

  it("shows the server-provided error detail when overwrite fails", async () => {
    (httpMethods.get as Mock).mockReturnValue(mockApiResponse([]));
    (httpMethods.post as Mock).mockImplementation(() =>
      mockApiError(422, {
        status: 422,
        title: "Unprocessable",
        detail: "Plik jest za duży.",
        context: { uuid: "e1f2a3b4-5c6d-4e7f-8123-abcdef123456" },
      }),
    );

    renderWithProviders(<VersionHistory itemId={5} />);
    await screen.findByText("Brak wcześniejszych wersji.");

    const file = new File(["content"], "umowa-v2.pdf", { type: "application/pdf" });
    const input = document.querySelector('input[type="file"]');
    if (null === input) {
      throw new Error("file input not found");
    }
    const user = userEvent.setup();
    await user.upload(input as HTMLInputElement, file);

    expect(await screen.findByText("Plik jest za duży.")).toBeInTheDocument();
  });
});
