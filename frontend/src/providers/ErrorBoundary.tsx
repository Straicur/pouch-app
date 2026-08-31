import { Component, type ErrorInfo, type ReactNode } from "react";
import i18n from "../libs/i18n";
import { logger } from "../libs/logger";

interface ErrorBoundaryProps {
  children: ReactNode;
}

interface ErrorBoundaryState {
  hasError: boolean;
}

// Catches render-time errors anywhere below it in the tree (a thrown error in
// an event handler or an async callback is NOT caught here — React's own
// limitation — those still need their own try/catch, see FRONTEND.md's
// "Error handling"). Without this, one uncaught render error unmounts the
// whole app to a blank page instead of a recoverable message. A class
// component is the only way to implement componentDidCatch — no hook
// equivalent exists — so, like the zod schemas (module scope, no hook
// available either), it calls i18n.t(...) directly instead of useTranslation().
export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  public state: ErrorBoundaryState = { hasError: false };

  public static getDerivedStateFromError(): ErrorBoundaryState {
    return { hasError: true };
  }

  public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    logger.error("Uncaught render error:", error, errorInfo);
  }

  public render() {
    if (this.state.hasError) {
      return (
        <main className="error-boundary">
          <h1>{i18n.t("errorBoundary.title")}</h1>
          <p>{i18n.t("errorBoundary.description")}</p>
          <button type="button" onClick={() => window.location.reload()}>
            {i18n.t("errorBoundary.reloadButton")}
          </button>
        </main>
      );
    }

    return this.props.children;
  }
}
