import { useTranslation } from "react-i18next";
import { useLocation } from "react-router-dom";
import { navigationUtil } from "../libs/navigationUtil";
import { Button } from "../ui/catalyst/button";
import { Heading } from "../ui/catalyst/heading";

interface TechnicalBreakLocationState {
  message?: string;
}

// httpClient's response interceptor redirects here on any 503 carrying
// ExceptionUuid.TECHNICAL_BREAK, passing the admin's own message (if any)
// as router state — reachable only that way, not a nav-bar destination, so a
// direct/refreshed visit falls back to the generic copy below instead.
export function TechnicalBreakPage() {
  const { t } = useTranslation();
  const location = useLocation();
  const state = location.state as TechnicalBreakLocationState | null;

  return (
    <main className="flex min-h-[calc(100vh-4rem)] flex-col items-center justify-center gap-4 px-6 text-center">
      <Heading variant="page">{t("technicalBreak.title")}</Heading>
      <p className="max-w-md text-zinc-500 dark:text-zinc-400">
        {state?.message ?? t("technicalBreak.defaultMessage")}
      </p>
      <Button onClick={() => navigationUtil.reload()}>{t("common.retry")}</Button>
    </main>
  );
}
