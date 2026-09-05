import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import { navigationUtil } from "../../src/libs/navigationUtil";
import { TechnicalBreakPage } from "../../src/pages/TechnicalBreakPage";

function renderTechnicalBreakPage(state?: { message?: string }) {
  return render(
    <MemoryRouter initialEntries={[{ pathname: "/technical-break", state }]}>
      <Routes>
        <Route path="/technical-break" element={<TechnicalBreakPage />} />
      </Routes>
    </MemoryRouter>,
  );
}

describe("TechnicalBreakPage", () => {
  it("shows the admin's own message when it was passed via router state", () => {
    renderTechnicalBreakPage({ message: "Wracamy o 20:00" });

    expect(screen.getByText("Wracamy o 20:00")).toBeInTheDocument();
  });

  it("falls back to the generic message with no router state (direct/refreshed visit)", () => {
    renderTechnicalBreakPage();

    expect(screen.getByText("Trwa przerwa techniczna. Spróbuj ponownie później.")).toBeInTheDocument();
  });

  it("reloads the page when the retry button is clicked", async () => {
    const reloadSpy = vi.spyOn(navigationUtil, "reload").mockImplementation(() => {});
    renderTechnicalBreakPage();
    const user = userEvent.setup();

    await user.click(screen.getByRole("button", { name: "Ponów" }));

    expect(reloadSpy).toHaveBeenCalledOnce();
  });
});
