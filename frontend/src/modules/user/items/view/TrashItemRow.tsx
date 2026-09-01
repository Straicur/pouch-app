import { useTranslation } from "react-i18next";
import { toastUtil } from "../../../../libs/toastUtil";
import { useRestoreItemMutation } from "../../../../store/api/itemApi";
import type { ItemSummary } from "../../../../store/types/item";
import { Badge } from "../../../../ui/catalyst/badge";
import { Button } from "../../../../ui/catalyst/button";
import { TableCell, TableRow } from "../../../../ui/catalyst/table";

interface TrashItemRowProps {
  item: ItemSummary;
}

export function TrashItemRow({ item }: TrashItemRowProps) {
  const { t } = useTranslation();
  const [restoreItem, { isLoading }] = useRestoreItemMutation();

  const handleRestore = async () => {
    try {
      await restoreItem(item.id).unwrap();
    } catch {
      toastUtil.showToast(t("trash.restoreError"), "error");
    }
  };

  return (
    <TableRow>
      <TableCell>{item.name}</TableCell>
      <TableCell>
        <Badge>{t(`items.type.${item.type}`)}</Badge>
      </TableCell>
      <TableCell>{null !== item.trashedAt ? new Date(item.trashedAt).toLocaleString() : ""}</TableCell>
      <TableCell>
        <Button size="small" onClick={() => void handleRestore()} disabled={isLoading}>
          {isLoading ? t("trash.restoring") : t("trash.restoreButton")}
        </Button>
      </TableCell>
    </TableRow>
  );
}
