import { useTranslation } from "react-i18next";
import { Button } from "../../../ui/catalyst/button";
import { Dialog, DialogActions, DialogDescription, DialogTitle } from "../../../ui/catalyst/dialog";

interface ConfirmDialogProps {
  open: boolean;
  title: string;
  description: string;
  confirmLabel: string;
  onConfirm: () => void;
  onClose: () => void;
  isConfirming?: boolean;
}

// Część 14 (panel admina) — potwierdzenie przed nieodwracalną/kosztowną akcją
// (backup całości, ręczne uruchomienie GC, masowe przedłużenie wygasania) —
// jeden komponent zamiast powtarzania tego samego Dialogu w każdym miejscu.
export function ConfirmDialog({
  open,
  title,
  description,
  confirmLabel,
  onConfirm,
  onClose,
  isConfirming = false,
}: ConfirmDialogProps) {
  const { t } = useTranslation();

  return (
    <Dialog open={open} onClose={onClose}>
      <DialogTitle>{title}</DialogTitle>
      <DialogDescription>{description}</DialogDescription>
      <DialogActions>
        <Button variant="outline" onClick={onClose} disabled={isConfirming}>
          {t("common.cancel")}
        </Button>
        <Button onClick={onConfirm} disabled={isConfirming}>
          {confirmLabel}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
