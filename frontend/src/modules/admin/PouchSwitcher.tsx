import { useTranslation } from "react-i18next";
import { useListPouchesQuery } from "../../store/api/userApi";
import { Select } from "../../ui/catalyst/form/select";
import { usePouchFilter } from "./pouchFilter";

const ALL_POUCHES_VALUE = "all";

// Renders once, in AdminLayout's navbar — every admin page reads the choice
// back via usePouchFilter() instead of carrying its own selector.
export function PouchSwitcher() {
  const { t } = useTranslation();
  const { data: pouches } = useListPouchesQuery();
  const { pouchId, setPouchId } = usePouchFilter();

  return (
    <Select
      aria-label={t("admin.pouchSwitcher.label")}
      className="w-auto min-w-40"
      value={null === pouchId ? ALL_POUCHES_VALUE : String(pouchId)}
      onChange={(event) => {
        const { value } = event.target;
        setPouchId(ALL_POUCHES_VALUE === value ? null : Number(value));
      }}
    >
      <option value={ALL_POUCHES_VALUE}>{t("admin.pouchSwitcher.allPouches")}</option>
      {(pouches ?? []).map((pouch) => (
        <option key={pouch.id} value={pouch.id}>
          {pouch.name}
        </option>
      ))}
    </Select>
  );
}
