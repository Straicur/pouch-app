import i18n from "i18next";
import { initReactI18next } from "react-i18next";
import pl from "../locales/pl";

// Single-locale for now (product doc: this is a personal/few-user tool) —
// i18next is still the right tool over a hand-rolled t(), since it gives
// components (useTranslation) and non-component code (imported `i18n`
// directly, see toastUtil.ts) the same key-lookup API for free.
void i18n.use(initReactI18next).init({
  resources: {
    pl: { translation: pl },
  },
  lng: "pl",
  fallbackLng: "pl",
  interpolation: {
    escapeValue: false,
  },
});

export default i18n;
