import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { Mock } from "vitest";
import { httpMethods } from "../../../../../src/libs/httpMethods";
import { ItemFilters } from "../../../../../src/modules/user/items/view/ItemFilters";
import { mockApiResponse } from "../../../../testUtils/mockHttp";
import { renderWithProviders } from "../../../../testUtils/renderWithProviders";

vi.mock("../../../../../src/libs/httpMethods", () => ({
  httpMethods: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), del: vi.fn() },
}));

beforeEach(() => {
  (httpMethods.get as Mock).mockReturnValue(mockApiResponse([]));
});

afterEach(() => {
  vi.clearAllMocks();
});

describe("ItemFilters — debounced search", () => {
  it("waits for the debounce before calling onChange, once per burst of keystrokes", async () => {
    const onChange = vi.fn();
    renderWithProviders(<ItemFilters filters={{}} onChange={onChange} />);
    const user = userEvent.setup();

    await user.type(screen.getByPlaceholderText("Szukaj po nazwie, tagach, treści, OCR… (min. 2 znaki)"), "poj");

    // Nothing fires while still typing — the debounce hasn't elapsed yet.
    expect(onChange).not.toHaveBeenCalled();

    await waitFor(() => {
      expect(onChange).toHaveBeenCalledWith({ q: "poj" });
    });
    expect(onChange).toHaveBeenCalledTimes(1);
  });

  it("clears an active search once the query drops below the minimum length", async () => {
    const onChange = vi.fn();
    renderWithProviders(<ItemFilters filters={{ q: "existing" }} onChange={onChange} />);
    const user = userEvent.setup();
    const input = screen.getByPlaceholderText("Szukaj po nazwie, tagach, treści, OCR… (min. 2 znaki)");

    await user.clear(input);
    await user.type(input, "a");

    await waitFor(() => {
      expect(onChange).toHaveBeenCalledWith({ q: undefined });
    });
  });

  it("toggles the favorites-only filter immediately, without debounce", async () => {
    const onChange = vi.fn();
    renderWithProviders(<ItemFilters filters={{}} onChange={onChange} />);
    const user = userEvent.setup();

    await user.click(screen.getByRole("checkbox"));

    expect(onChange).toHaveBeenCalledWith({ favorite: true });
  });

  it("shows a clear-filters button only when a filter is active, and it resets everything", async () => {
    const onChange = vi.fn();
    const { rerender } = renderWithProviders(<ItemFilters filters={{}} onChange={onChange} />);

    expect(screen.queryByRole("button", { name: "Wyczyść filtry" })).not.toBeInTheDocument();

    rerender(<ItemFilters filters={{ favorite: true }} onChange={onChange} />);
    const user = userEvent.setup();

    await user.click(screen.getByRole("button", { name: "Wyczyść filtry" }));

    expect(onChange).toHaveBeenCalledWith({
      categoryIds: undefined,
      tags: undefined,
      favorite: undefined,
      q: undefined,
    });
  });
});
