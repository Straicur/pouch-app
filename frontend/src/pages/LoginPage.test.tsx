import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { Provider } from "react-redux";
import { MemoryRouter } from "react-router-dom";
import { store } from "../store/store";
import { LoginPage } from "./LoginPage";

function renderLoginPage() {
  return render(
    <Provider store={store}>
      <MemoryRouter>
        <LoginPage />
      </MemoryRouter>
    </Provider>,
  );
}

describe("LoginPage", () => {
  it("renders the email and password fields", () => {
    renderLoginPage();

    expect(screen.getByLabelText("E-mail")).toBeInTheDocument();
    expect(screen.getByLabelText("Hasło")).toBeInTheDocument();
  });

  it("shows a validation error when submitted empty", async () => {
    renderLoginPage();
    const user = userEvent.setup();

    await user.click(screen.getByRole("button", { name: "Zaloguj się" }));

    expect(await screen.findByText("Nieprawidłowy adres e-mail")).toBeInTheDocument();
  });
});
