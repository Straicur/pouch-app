import { useState } from "react";
import { useTranslation } from "react-i18next";
import { LoadingIndicator } from "../../../modules/shared/view/LoadingIndicator";
import { TrashItemRow } from "../../../modules/user/items/view/TrashItemRow";
import { useListTrashQuery } from "../../../store/api/itemApi";
import { Button } from "../../../ui/catalyst/button";
import { Heading } from "../../../ui/catalyst/heading";
import { Table, TableBody, TableHead, TableHeader, TableRow } from "../../../ui/catalyst/table";

// Wyświetlane, ale nienawigowalne przez kliknięcie karty jak reszta list —
// GET /api/items/{id} 404uje na skasowanym itemie (ItemService::getById()),
// więc ItemDetailsModal/ItemCard tu nie działają. Płaska tabela z jedyną
// dostępną akcją: przywróceniem (PATCH /api/items/{id}/restore).
const TRASH_PAGE_SIZE = 24;

export function TrashPage() {
  const { t } = useTranslation();
  const [page, setPage] = useState(1);
  const { data, isLoading, error } = useListTrashQuery({ page, pageSize: TRASH_PAGE_SIZE });

  const totalPages = undefined !== data ? Math.max(1, Math.ceil(data.total / data.pageSize)) : 1;

  return (
    <div className="flex flex-col gap-4">
      <Heading variant="page">{t("nav.trash")}</Heading>

      {isLoading && <LoadingIndicator />}
      {undefined !== error && <p className="text-red-600 dark:text-red-400">{t("trash.fetchError")}</p>}
      {undefined !== data && 0 === data.items.length && <p>{t("trash.empty")}</p>}

      {undefined !== data && data.items.length > 0 && (
        <Table>
          <TableHead>
            <TableRow>
              <TableHeader>{t("trash.nameLabel")}</TableHeader>
              <TableHeader>{t("trash.typeLabel")}</TableHeader>
              <TableHeader>{t("trash.trashedAtLabel")}</TableHeader>
              <TableHeader />
            </TableRow>
          </TableHead>
          <TableBody>
            {data.items.map((item) => (
              <TrashItemRow key={item.id} item={item} />
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
    </div>
  );
}
