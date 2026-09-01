import { useState } from "react";
import { useTranslation } from "react-i18next";
import ReactMarkdown from "react-markdown";
import { toastUtil } from "../../../../libs/toastUtil";
import { useSetItemKeyMutation, useUnlockItemMutation } from "../../../../store/api/accessKeyApi";
import type { Category } from "../../../../store/api/categoryApi";
import { useListCategoriesQuery } from "../../../../store/api/categoryApi";
import {
  useDeleteItemMutation,
  useGetItemDownloadLinkMutation,
  useGetItemQuery,
  useMoveItemMutation,
  useUpdateNoteMutation,
  useUpdateTagsMutation,
} from "../../../../store/api/itemApi";
import type { ItemDetail } from "../../../../store/types/item";
import { Badge } from "../../../../ui/catalyst/badge";
import { Button } from "../../../../ui/catalyst/button";
import { Dialog, DialogActions, DialogBody, DialogTitle } from "../../../../ui/catalyst/dialog";
import { ErrorMessage, Field, Label } from "../../../../ui/catalyst/form/fieldset";
import { Select } from "../../../../ui/catalyst/form/select";
import { Textarea } from "../../../../ui/catalyst/form/textarea";
import { ConfirmDialog } from "../../../shared/view/ConfirmDialog";
import { LoadingIndicator } from "../../../shared/view/LoadingIndicator";
import { AccessKeyPanel } from "../../shared/AccessKeyPanel";
import { FavoriteStar } from "./FavoriteStar";
import { useItemThumbnailUrl } from "./ItemCard";
import { ShareButton } from "./ShareButton";
import { TagsInput } from "./TagsInput";
import { VersionHistory } from "./VersionHistory";

interface ItemDetailsModalProps {
  itemId: number;
  open: boolean;
  onClose: () => void;
}

interface NoteBodyProps {
  item: ItemDetail;
}

// "Edycja po fakcie" — jedyna rzecz, którą można zrobić z NOTE, a nie da się
// z żadnym innym typem (updateNoteContent() na backendzie odrzuca resztę).
function NoteBody({ item }: NoteBodyProps) {
  const { t } = useTranslation();
  const [isEditing, setIsEditing] = useState(false);
  const [draft, setDraft] = useState(item.noteContent ?? "");
  const [updateNote, { isLoading }] = useUpdateNoteMutation();
  const [error, setError] = useState<string | null>(null);

  const startEditing = () => {
    setDraft(item.noteContent ?? "");
    setError(null);
    setIsEditing(true);
  };

  const handleSave = async () => {
    setError(null);

    try {
      await updateNote({ id: item.id, content: draft }).unwrap();
      setIsEditing(false);
    } catch {
      setError(t("notes.updateError"));
    }
  };

  if (isEditing) {
    return (
      <div className="flex flex-col gap-2">
        <Textarea value={draft} onChange={(event) => setDraft(event.target.value)} rows={8} />
        {null !== error && <ErrorMessage>{error}</ErrorMessage>}
        <div className="flex gap-2">
          <Button size="small" onClick={handleSave} disabled={isLoading}>
            {isLoading ? t("notes.saving") : t("notes.save")}
          </Button>
          <Button size="small" variant="outline" onClick={() => setIsEditing(false)} disabled={isLoading}>
            {t("notes.cancel")}
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-2">
      <div className="text-sm text-zinc-700 dark:text-zinc-300">
        <ReactMarkdown>{item.noteContent ?? ""}</ReactMarkdown>
      </div>
      <Button size="small" variant="outline" onClick={startEditing} className="w-fit">
        {t("notes.edit")}
      </Button>
    </div>
  );
}

interface TagEditorProps {
  item: ItemDetail;
}

function TagEditor({ item }: TagEditorProps) {
  const { t } = useTranslation();
  const [updateTags] = useUpdateTagsMutation();
  const [error, setError] = useState<string | null>(null);

  const handleChange = async (tags: string[]) => {
    setError(null);

    try {
      await updateTags({ id: item.id, tags }).unwrap();
    } catch {
      setError(t("tags.updateError"));
    }
  };

  return (
    <div className="flex flex-col gap-1">
      <TagsInput value={item.tags} onChange={handleChange} />
      {null !== error && <ErrorMessage>{error}</ErrorMessage>}
    </div>
  );
}

interface DownloadButtonProps {
  item: ItemDetail;
}

function DownloadButton({ item }: DownloadButtonProps) {
  const { t } = useTranslation();
  const [getDownloadLink, { isLoading }] = useGetItemDownloadLinkMutation();

  const handleDownload = async () => {
    try {
      const link = await getDownloadLink(item.id).unwrap();
      // Same-tab navigation, not window.open() — the signed URL responds
      // with Content-Disposition: attachment, so this still downloads
      // rather than navigating away.
      window.location.assign(link.url);
    } catch {
      toastUtil.showToast(t("items.downloadError"), "error");
    }
  };

  return (
    <Button size="small" onClick={handleDownload} disabled={isLoading}>
      {isLoading ? t("items.downloading") : t("items.download")}
    </Button>
  );
}

interface DeleteButtonProps {
  item: ItemDetail;
  onDeleted: () => void;
}

// Za ConfirmDialogiem — usunięcie itemu jest łatwe do przypadkowego kliknięcia.
function DeleteButton({ item, onDeleted }: DeleteButtonProps) {
  const { t } = useTranslation();
  const [deleteItem, { isLoading }] = useDeleteItemMutation();
  const [isConfirmOpen, setIsConfirmOpen] = useState(false);

  const handleDelete = async () => {
    try {
      await deleteItem(item.id).unwrap();
      setIsConfirmOpen(false);
      onDeleted();
    } catch {
      toastUtil.showToast(t("items.deleteError"), "error");
    }
  };

  return (
    <>
      <Button size="small" variant="red" onClick={() => setIsConfirmOpen(true)}>
        {t("items.deleteButton")}
      </Button>
      <ConfirmDialog
        open={isConfirmOpen}
        title={t("items.deleteConfirmTitle")}
        description={t("items.deleteConfirmDescription", { name: item.name })}
        confirmLabel={t("items.deleteButton")}
        onConfirm={handleDelete}
        onClose={() => setIsConfirmOpen(false)}
        isConfirming={isLoading}
      />
    </>
  );
}

interface MoveItemButtonProps {
  item: ItemDetail;
  categories: Category[];
}

function MoveItemButton({ item, categories }: MoveItemButtonProps) {
  const { t } = useTranslation();
  const [moveItem, { isLoading }] = useMoveItemMutation();
  const [isOpen, setIsOpen] = useState(false);
  const [target, setTarget] = useState(String(item.categoryId));

  const handleMove = async () => {
    try {
      await moveItem({ id: item.id, categoryId: Number(target) }).unwrap();
      setIsOpen(false);
    } catch {
      toastUtil.showToast(t("items.moveError"), "error");
    }
  };

  return (
    <>
      <Button size="small" variant="outline" onClick={() => setIsOpen(true)}>
        {t("items.moveButton")}
      </Button>
      <Dialog open={isOpen} onClose={setIsOpen}>
        <DialogTitle>{t("items.moveTitle", { name: item.name })}</DialogTitle>
        <DialogBody>
          <Field>
            <Label>{t("items.moveTargetLabel")}</Label>
            <Select value={target} onChange={(event) => setTarget(event.target.value)}>
              {categories.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.name}
                </option>
              ))}
            </Select>
          </Field>
        </DialogBody>
        <DialogActions>
          <Button onClick={() => void handleMove()} disabled={isLoading || target === String(item.categoryId)}>
            {isLoading ? t("items.moving") : t("items.moveSubmit")}
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
}

// Jeden modal ze wszystkimi detalami, otwierany kliknięciem karty (ItemCard)
// — wszystko poniżej pojawia się od razu, bez dodatkowego klikania (poza
// samą edycją treści/tagów, co jest osobną, świadomą akcją).
export function ItemDetailsModal({ itemId, open, onClose }: ItemDetailsModalProps) {
  const { t } = useTranslation();
  const { data: item, error, refetch } = useGetItemQuery(itemId, { skip: !open });
  const { data: categories } = useListCategoriesQuery();
  const [unlockItem] = useUnlockItemMutation();
  const [setItemKey] = useSetItemKeyMutation();
  const thumbnailUrl = useItemThumbnailUrl(item ?? { id: itemId, hasThumbnail: false });

  const categoryName = categories?.find((category) => category.id === item?.categoryId)?.name ?? null;
  const title = undefined !== item && "url" === item.type ? (item.pageTitle ?? item.name) : item?.name;

  return (
    <Dialog open={open} onClose={onClose}>
      {undefined !== error ? (
        <DialogBody>
          <div className="flex flex-col items-start gap-3 py-6">
            <ErrorMessage>{t("items.detailsLoadError")}</ErrorMessage>
            <div className="flex gap-2">
              <Button size="small" variant="outline" onClick={() => void refetch()}>
                {t("common.retry")}
              </Button>
              <Button size="small" variant="outline" onClick={onClose}>
                {t("common.close")}
              </Button>
            </div>
          </div>
        </DialogBody>
      ) : undefined === item ? (
        <DialogBody>
          <LoadingIndicator className="py-6" />
        </DialogBody>
      ) : (
        <>
          <div className="flex items-start justify-between gap-3">
            <DialogTitle>{title}</DialogTitle>
            <FavoriteStar itemId={item.id} favorite={item.favorite} className="mt-1 shrink-0" />
          </div>
          <DialogBody>
            <div className="flex flex-col gap-4">
              <div className="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                <Badge>{t(`items.type.${item.type}`)}</Badge>
                {null !== categoryName && <span>{categoryName}</span>}
              </div>

              {null !== thumbnailUrl && (
                <img src={thumbnailUrl} alt="" className="max-h-80 w-full rounded-lg object-contain" />
              )}

              {"url" === item.type && null !== item.url && (
                <a
                  href={item.url}
                  target="_blank"
                  rel="noreferrer"
                  className="text-sm text-blue-600 hover:underline dark:text-blue-400"
                >
                  {item.url}
                </a>
              )}
              {"url" === item.type && null !== item.pageDescription && (
                <p className="text-sm text-zinc-600 dark:text-zinc-400">{item.pageDescription}</p>
              )}

              {"note" === item.type && <NoteBody item={item} />}
              {"note" !== item.type && null !== item.noteContent && (
                <div className="text-sm text-zinc-700 dark:text-zinc-300">
                  <ReactMarkdown>{item.noteContent}</ReactMarkdown>
                </div>
              )}

              {"pending" === item.processingStatus && (
                <p className="text-sm text-zinc-500 dark:text-zinc-400">{t("items.processing")}</p>
              )}
              {"failed" === item.processingStatus && (
                <p className="text-sm text-red-600 dark:text-red-400">
                  {t("items.processingError")}
                  {null !== item.processingError ? `: ${item.processingError}` : ""}
                </p>
              )}

              <TagEditor item={item} />

              <div className="flex flex-wrap items-center gap-2">
                {/* Pobieranie wymaga realnej zawartości pliku — tylko file/photo.
                    Udostępnianie działa dla każdego typu (backend nie ogranicza). */}
                {("file" === item.type || "photo" === item.type) && <DownloadButton item={item} />}
                <ShareButton itemId={item.id} />
                {undefined !== categories && <MoveItemButton item={item} categories={categories} />}
                <DeleteButton item={item} onDeleted={onClose} />
              </div>

              {"file" === item.type && <VersionHistory itemId={item.id} />}

              <AccessKeyPanel
                hasKey={item.hasAccessKey}
                showUnlock={false}
                onUnlock={(key) => unlockItem({ itemId: item.id, key }).unwrap()}
                onSetKey={(key) => setItemKey({ itemId: item.id, key }).unwrap()}
              />
            </div>
          </DialogBody>
        </>
      )}
    </Dialog>
  );
}
