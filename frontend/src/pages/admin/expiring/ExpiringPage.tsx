import { useState } from "react";
import { useTranslation } from "react-i18next";
import { toastUtil } from "../../../libs/toastUtil";
import { ConfirmDialog } from "../../../modules/shared/view/ConfirmDialog";
import { useExtendExpiryMutation, useListExpiringSoonQuery } from "../../../store/api/adminApi";
import { Button } from "../../../ui/catalyst/button";
import { Checkbox } from "../../../ui/catalyst/form/checkbox";
import { Heading } from "../../../ui/catalyst/heading";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "../../../ui/catalyst/table";

// Part 10: "lista itemów wygasających w ciągu najbliższych 24h + masowe przedłużenie".
// Część 14 post-review fix: gołe <ul><li><label><input></label></ul> wyglądało
// niechlujnie — teraz Catalyst Table + Checkbox. Przycisk przedłużenia
// przeniesiony pod listę (nie tuż pod nagłówkiem) i potwierdzany modalem —
// masowe przedłużenie wielu itemów naraz to łatwa pomyłka do cofnięcia.
export function ExpiringPage() {
  const { t } = useTranslation();
  const { data: items } = useListExpiringSoonQuery(24);
  const [extendExpiry, { isLoading }] = useExtendExpiryMutation();
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [isConfirmOpen, setIsConfirmOpen] = useState(false);

  const toggleSelected = (id: number, checked: boolean) => {
    setSelectedIds((current) => (checked ? [...current, id] : current.filter((existing) => existing !== id)));
  };

  const handleExtend = async () => {
    setIsConfirmOpen(false);

    try {
      await extendExpiry({ itemIds: selectedIds, keepForever: false, ttlPreset: "7d" }).unwrap();
      setSelectedIds([]);
      toastUtil.showToast(t("admin.expiringSoon.extendSuccess"), "success");
    } catch {
      toastUtil.showToast(t("admin.expiringSoon.extendError"), "error");
    }
  };

  return (
    <section className="flex flex-col gap-4">
      <Heading variant="page">{t("admin.expiringSoon.title")}</Heading>

      {undefined !== items && 0 === items.length && <p>{t("admin.expiringSoon.empty")}</p>}

      {undefined !== items && items.length > 0 && (
        <>
          <Table>
            <TableHead>
              <TableRow>
                <TableHeader />
                <TableHeader>{t("admin.expiringSoon.itemName")}</TableHeader>
                <TableHeader>{t("admin.expiringSoon.expiresAt")}</TableHeader>
              </TableRow>
            </TableHead>
            <TableBody>
              {items.map((item) => (
                <TableRow key={item.id}>
                  <TableCell>
                    <Checkbox
                      checked={selectedIds.includes(item.id)}
                      onChange={(checked) => toggleSelected(item.id, checked)}
                    />
                  </TableCell>
                  <TableCell>{item.name}</TableCell>
                  <TableCell>{null !== item.expiresAt ? new Date(item.expiresAt).toLocaleString() : ""}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>

          <Button onClick={() => setIsConfirmOpen(true)} disabled={0 === selectedIds.length} className="w-fit">
            {t("admin.expiringSoon.extendButton")}
          </Button>
        </>
      )}

      <ConfirmDialog
        open={isConfirmOpen}
        title={t("admin.expiringSoon.confirmTitle")}
        description={t("admin.expiringSoon.confirmDescription", { count: selectedIds.length })}
        confirmLabel={t("admin.expiringSoon.extendButton")}
        onConfirm={handleExtend}
        onClose={() => setIsConfirmOpen(false)}
        isConfirming={isLoading}
      />
    </section>
  );
}
