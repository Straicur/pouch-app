import { useState } from "react";
import { useTranslation } from "react-i18next";
import { toastUtil } from "../../../libs/toastUtil";
import { useDeleteAdminItemMutation, useListAdminItemsQuery } from "../../../store/api/adminApi";
import { Button } from "../../../ui/catalyst/button";
import { Subheading } from "../../../ui/catalyst/heading";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "../../../ui/catalyst/table";
import { ConfirmDialog } from "../../shared/view/ConfirmDialog";

const formatSize = (bytes: number | null): string => {
  if (null === bytes) {
    return "—";
  }

  return bytes < 1024 * 1024 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

interface AdminItemBrowserProps {
  pouchId: number;
}

// "Zarządzanie itemami/plikami per pouch" — StoragePage do tej pory
// pokazywał tylko zagregowane liczby, bez możliwości przejrzenia/usunięcia
// pojedynczych plików (zgłoszenie usera, Krok 3). Widoczne tylko po wybraniu
// konkretnego pouch w PouchSwitcher — backend wymaga pouchId dla tego endpointu.
export function AdminItemBrowser({ pouchId }: AdminItemBrowserProps) {
  const { t } = useTranslation();
  const [page, setPage] = useState(1);
  const { data, isLoading, error } = useListAdminItemsQuery({ pouchId, page });
  const [deleteItem, { isLoading: isDeleting }] = useDeleteAdminItemMutation();
  const [pendingDeleteId, setPendingDeleteId] = useState<number | null>(null);

  const totalPages = undefined !== data ? Math.max(1, Math.ceil(data.total / data.pageSize)) : 1;

  const handleDelete = async () => {
    if (null === pendingDeleteId) {
      return;
    }

    try {
      await deleteItem(pendingDeleteId).unwrap();
      setPendingDeleteId(null);
    } catch {
      toastUtil.showToast(t("admin.items.deleteError"), "error");
      setPendingDeleteId(null);
    }
  };

  return (
    <section className="flex flex-col gap-4">
      <Subheading>{t("admin.items.title")}</Subheading>

      {isLoading && <p className="text-sm text-zinc-500 dark:text-zinc-400">{t("common.loading")}</p>}
      {undefined !== error && <p className="text-red-600 dark:text-red-400">{t("admin.items.fetchError")}</p>}
      {undefined !== data && 0 === data.items.length && <p>{t("admin.items.empty")}</p>}

      {undefined !== data && data.items.length > 0 && (
        <Table>
          <TableHead>
            <TableRow>
              <TableHeader>{t("admin.items.name")}</TableHeader>
              <TableHeader>{t("admin.items.type")}</TableHeader>
              <TableHeader>{t("admin.items.size")}</TableHeader>
              <TableHeader />
            </TableRow>
          </TableHead>
          <TableBody>
            {data.items.map((item) => (
              <TableRow key={item.id}>
                <TableCell>{item.name}</TableCell>
                <TableCell>{t(`items.type.${item.type}`)}</TableCell>
                <TableCell>{formatSize(item.size)}</TableCell>
                <TableCell>
                  <Button size="small" variant="red" onClick={() => setPendingDeleteId(item.id)}>
                    {t("admin.items.deleteButton")}
                  </Button>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}

      {undefined !== data && totalPages > 1 && (
        <div className="flex items-center justify-center gap-3">
          <Button variant="outline" size="small" onClick={() => setPage((current) => current - 1)} disabled={page <= 1}>
            {t("items.pagerPrevious")}
          </Button>
          <span className="text-sm text-zinc-600 dark:text-zinc-400">
            {t("items.pagerStatus", { page, totalPages })}
          </span>
          <Button
            variant="outline"
            size="small"
            onClick={() => setPage((current) => current + 1)}
            disabled={page >= totalPages}
          >
            {t("items.pagerNext")}
          </Button>
        </div>
      )}

      <ConfirmDialog
        open={null !== pendingDeleteId}
        title={t("admin.items.deleteConfirmTitle")}
        description={t("admin.items.deleteConfirmDescription")}
        confirmLabel={t("admin.items.deleteButton")}
        onConfirm={handleDelete}
        onClose={() => setPendingDeleteId(null)}
        isConfirming={isDeleting}
      />
    </section>
  );
}
