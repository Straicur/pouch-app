import { type FormEvent, useState } from "react";
import { useTranslation } from "react-i18next";
import { toastUtil } from "../../../lib/toastUtil";
import { useExtendExpiryMutation, useListExpiringSoonQuery } from "../../../store/api/adminApi";

// Part 10: "lista itemów wygasających w ciągu najbliższych 24h + masowe przedłużenie".
export function ExpiringModule() {
  const { t } = useTranslation();
  const { data: items } = useListExpiringSoonQuery(24);
  const [extendExpiry, { isLoading }] = useExtendExpiryMutation();
  const [selectedIds, setSelectedIds] = useState<number[]>([]);

  const toggleSelected = (id: number) => {
    setSelectedIds((current) =>
      current.includes(id) ? current.filter((existing) => existing !== id) : [...current, id],
    );
  };

  const handleExtend = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (0 === selectedIds.length) {
      return;
    }

    try {
      await extendExpiry({ itemIds: selectedIds, keepForever: false, ttlPreset: "7d" }).unwrap();
      setSelectedIds([]);
      toastUtil.showToast(t("admin.expiringSoon.extendSuccess"), "success");
    } catch {
      toastUtil.showToast(t("admin.expiringSoon.extendError"), "error");
    }
  };

  return (
    <section className="admin-section">
      <h1>{t("admin.expiringSoon.title")}</h1>

      {undefined !== items && 0 === items.length && <p>{t("admin.expiringSoon.empty")}</p>}

      {undefined !== items && items.length > 0 && (
        <form onSubmit={handleExtend}>
          <ul className="admin-expiring-list">
            {items.map((item) => (
              <li key={item.id}>
                <label>
                  <input
                    type="checkbox"
                    checked={selectedIds.includes(item.id)}
                    onChange={() => toggleSelected(item.id)}
                  />
                  {item.name} — {null !== item.expiresAt ? new Date(item.expiresAt).toLocaleString() : ""}
                </label>
              </li>
            ))}
          </ul>
          <button type="submit" disabled={isLoading || 0 === selectedIds.length}>
            {isLoading ? t("admin.expiringSoon.extending") : t("admin.expiringSoon.extendButton")}
          </button>
        </form>
      )}
    </section>
  );
}
