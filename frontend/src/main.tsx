import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import "./index.css";
import "./libs/i18n";
import App from "./App.tsx";
import { themeUtil } from "./libs/theme";

themeUtil.init();

createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
