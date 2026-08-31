import { type FormEvent, useState } from "react";
import { useTranslation } from "react-i18next";
import { toastUtil } from "../../../../libs/toastUtil";
import { useUnlockItemMutation } from "../../../../store/api/accessKeyApi";
import type { ItemSummary } from "../../../../store/types/item";
import { Badge } from "../../../../ui/catalyst/badge";
import { Button } from "../../../../ui/catalyst/button";
import { Input } from "../../../../ui/catalyst/form/input";
import { unlockErrorMessage } from "../../shared/AccessKeyPanel";

interface LockedItemCardProps {
  item: ItemSummary;
}

// Część 13: zablokowany item (własny klucz, kategoria odblokowana) pokazuje
// się na liście po samej nazwie zamiast znikać całkiem — z polem odblokuj
// wprost na karcie. Po sukcesie nic więcej nie trzeba robić ręcznie:
// useUnlockItemMutation's onQueryStarted (accessKeyApi.ts) zapisuje grant i
// unieważnia cache "Item", więc lista odświeży się sama i (mając już ważny
// grant) backend zwróci pełne dane tego itemu.
export function LockedItemCard({ item }: LockedItemCardProps) {
  const { t } = useTranslation();
  const [unlockItem, { isLoading }] = useUnlockItemMutation();
  const [key, setKey] = useState("");

  const handleUnlock = async (event: FormEvent) => {
    event.preventDefault();

    try {
      await unlockItem({ itemId: item.id, key }).unwrap();
      setKey("");
      toastUtil.showToast(t("accessKey.unlockSuccess"), "success");
    } catch (error) {
      toastUtil.showToast(unlockErrorMessage(error, t), "error");
    }
  };

  return (
    <article className="flex flex-col gap-3 rounded-lg p-4 ring-1 ring-zinc-950/10 dark:ring-white/10">
      <div className="flex items-center justify-between gap-2">
        <h3 className="text-base font-semibold text-zinc-950 dark:text-white">{item.name}</h3>
        <Badge color="orange">{t("items.lockedLabel")}</Badge>
      </div>

      <form className="flex items-center gap-2" onSubmit={handleUnlock}>
        <Input
          type="password"
          value={key}
          onChange={(event) => setKey(event.target.value)}
          placeholder={t("accessKey.unlockPlaceholder")}
          aria-label={t("accessKey.unlockPlaceholder")}
        />
        <Button type="submit" size="small" disabled={isLoading}>
          {isLoading ? t("accessKey.unlocking") : t("accessKey.unlockButton")}
        </Button>
      </form>
    </article>
  );
}
