import { useState } from "react";
import { useTranslation } from "react-i18next";
import { toastUtil } from "../../../../libs/toastUtil";
import type { TagResource } from "../../../../store/api/tagApi";
import { useDeleteTagMutation } from "../../../../store/api/tagApi";
import { Button } from "../../../../ui/catalyst/button";
import { Dialog, DialogTitle } from "../../../../ui/catalyst/dialog";
import { TableCell, TableRow } from "../../../../ui/catalyst/table";
import { ConfirmDialog } from "../../../shared/view/ConfirmDialog";
import { TagForm } from "../forms/TagForm";

interface TagRowProps {
  tag: TagResource;
  isAdmin: boolean;
}

export function TagRow({ tag, isAdmin }: TagRowProps) {
  const { t } = useTranslation();
  const [deleteTag, { isLoading: isDeleting }] = useDeleteTagMutation();
  const [isRenameOpen, setIsRenameOpen] = useState(false);
  const [isDeleteConfirmOpen, setIsDeleteConfirmOpen] = useState(false);

  const handleDelete = async () => {
    try {
      await deleteTag(tag.id).unwrap();
      setIsDeleteConfirmOpen(false);
    } catch {
      toastUtil.showToast(t("tags.deleteError"), "error");
      setIsDeleteConfirmOpen(false);
    }
  };

  return (
    <TableRow>
      <TableCell>{tag.name}</TableCell>
      <TableCell>
        <div className="flex gap-2">
          <Button size="small" variant="outline" onClick={() => setIsRenameOpen(true)}>
            {t("tags.renameButton")}
          </Button>
          {isAdmin && (
            <Button size="small" variant="red" onClick={() => setIsDeleteConfirmOpen(true)}>
              {t("tags.deleteButton")}
            </Button>
          )}
        </div>

        <Dialog open={isRenameOpen} onClose={setIsRenameOpen}>
          <DialogTitle>{t("tags.renameTitle")}</DialogTitle>
          <TagForm key={String(isRenameOpen)} tag={tag} onSuccess={() => setIsRenameOpen(false)} />
        </Dialog>

        <ConfirmDialog
          open={isDeleteConfirmOpen}
          title={t("tags.deleteConfirmTitle")}
          description={t("tags.deleteConfirmDescription", { name: tag.name })}
          confirmLabel={t("tags.deleteButton")}
          onConfirm={handleDelete}
          onClose={() => setIsDeleteConfirmOpen(false)}
          isConfirming={isDeleting}
        />
      </TableCell>
    </TableRow>
  );
}
