import { useState } from "react";
import { useTranslation } from "react-i18next";
import { LoadingIndicator } from "../../../modules/shared/view/LoadingIndicator";
import { TagForm } from "../../../modules/user/tags/forms/TagForm";
import { TagRow } from "../../../modules/user/tags/view/TagRow";
import { useWhoAmIQuery } from "../../../store/api/sessionApi";
import { useListAllTagsQuery } from "../../../store/api/tagApi";
import { Button } from "../../../ui/catalyst/button";
import { Dialog, DialogTitle } from "../../../ui/catalyst/dialog";
import { Heading } from "../../../ui/catalyst/heading";
import { Table, TableBody, TableHead, TableHeader, TableRow } from "../../../ui/catalyst/table";

export function TagsPage() {
  const { t } = useTranslation();
  const { data: currentUser } = useWhoAmIQuery();
  const { data: tags, isLoading, error } = useListAllTagsQuery();
  const [isAddOpen, setIsAddOpen] = useState(false);

  const isAdmin = true === currentUser?.isAdmin;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between gap-2">
        <Heading variant="page">{t("tags.title")}</Heading>
        <Button onClick={() => setIsAddOpen(true)}>{t("tags.addButton")}</Button>
      </div>

      {isLoading && <LoadingIndicator />}
      {undefined !== error && <p className="text-red-600 dark:text-red-400">{t("tags.fetchError")}</p>}
      {undefined !== tags && 0 === tags.length && <p>{t("tags.empty")}</p>}

      {undefined !== tags && tags.length > 0 && (
        <Table>
          <TableHead>
            <TableRow>
              <TableHeader>{t("tags.nameLabel")}</TableHeader>
              <TableHeader />
            </TableRow>
          </TableHead>
          <TableBody>
            {tags.map((tag) => (
              <TagRow key={tag.id} tag={tag} isAdmin={isAdmin} />
            ))}
          </TableBody>
        </Table>
      )}

      <Dialog open={isAddOpen} onClose={setIsAddOpen}>
        <DialogTitle>{t("tags.addTitle")}</DialogTitle>
        {/* Keyed on open state, same reason as CategoryForm/CreateUserForm —
            a fresh form on every re-open instead of carrying over a
            previous submit. */}
        <TagForm key={String(isAddOpen)} onSuccess={() => setIsAddOpen(false)} />
      </Dialog>
    </div>
  );
}
