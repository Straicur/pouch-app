import { useState } from "react";
import { useTranslation } from "react-i18next";
import { themeUtil } from "../../../libs/theme";
import { Label } from "../../../ui/catalyst/form/fieldset";
import { Switch, SwitchField } from "../../../ui/catalyst/form/switch";

// Pierwszy komponent współdzielony między obszarami user/admin (patrz
// FRONTEND.md, "Komponent współdzielony między user i admin") — renderowany
// w SidebarFooter obu layoutów.
export function ThemeSwitch() {
  const { t } = useTranslation();
  const [isDark, setIsDark] = useState(() => "dark" === themeUtil.get());

  const handleChange = (checked: boolean) => {
    themeUtil.set(checked ? "dark" : "light");
    setIsDark(checked);
  };

  return (
    <SwitchField>
      <Label>{t("theme.toggleLabel")}</Label>
      <Switch checked={isDark} onChange={handleChange} />
    </SwitchField>
  );
}
