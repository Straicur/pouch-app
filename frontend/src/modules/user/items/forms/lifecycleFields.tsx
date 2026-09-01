import type { FieldErrors, UseFormRegister } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { z } from "zod";
import type { ItemLifecycleFields } from "../../../../store/types/item";
import { ErrorMessage, Label } from "../../../../ui/catalyst/form/fieldset";
import { Input } from "../../../../ui/catalyst/form/input";

// The product doc requires choosing a TTL at creation time, not silently
// falling back to the backend's 1-day default — one radio group covering the
// same choices ItemLifecycleOptions actually accepts, shared by
// FileUploadForm and NoteForm so they stay in sync rather than drifting.
// "Domyślnie" *is* "przechowuj zawsze" (one option, not two) — new items are
// kept forever unless a TTL is picked explicitly, per the product doc.
const LIFECYCLE_MODES = ["default", "1h", "1d", "7d", "30d", "custom"] as const;
export type LifecycleMode = (typeof LIFECYCLE_MODES)[number];

export const lifecycleFieldsSchema = {
  lifecycleMode: z.enum(LIFECYCLE_MODES).default("default"),
  customExpiresAt: z.string().optional(),
};

export interface LifecycleFormValues {
  lifecycleMode?: LifecycleMode;
  customExpiresAt?: string;
}

// Turns the form's radio choice into the subset of fields itemApi's mutations
// actually send.
export const toLifecyclePayload = (values: LifecycleFormValues): ItemLifecycleFields => {
  switch (values.lifecycleMode) {
    case "custom":
      return undefined !== values.customExpiresAt && "" !== values.customExpiresAt
        ? { expiresAt: values.customExpiresAt }
        : {};
    case "1h":
    case "1d":
    case "7d":
    case "30d":
      return { ttlPreset: values.lifecycleMode };
    default:
      return { keepForever: true };
  }
};

interface LifecycleFieldsInputProps<T extends LifecycleFormValues> {
  idPrefix: string;
  register: UseFormRegister<T>;
  errors: FieldErrors<T>;
  mode: LifecycleMode;
}

// Kolejność ustalona wprost przez usera — nie zmieniać bez powodu.
const RADIO_OPTIONS: { value: LifecycleMode; labelKey: string }[] = [
  { value: "default", labelKey: "lifecycle.default" },
  { value: "1h", labelKey: "lifecycle.ttl1h" },
  { value: "1d", labelKey: "lifecycle.ttl1d" },
  { value: "7d", labelKey: "lifecycle.ttl7d" },
  { value: "30d", labelKey: "lifecycle.ttl30d" },
  { value: "custom", labelKey: "lifecycle.custom" },
];

export function LifecycleFieldsInput<T extends LifecycleFormValues>({
  idPrefix,
  register,
  errors,
  mode,
}: LifecycleFieldsInputProps<T>) {
  const { t } = useTranslation();
  // biome-ignore lint/suspicious/noExplicitAny: shared across differently-shaped form value types
  const registerAny = register as UseFormRegister<any>;

  return (
    <div className="flex flex-col gap-2">
      <span className="text-sm font-medium text-zinc-950 dark:text-white">{t("lifecycle.label")}</span>
      {/* Stacked vertically, not side by side — a row of six inline <label>
          elements wraps unpredictably. */}
      <div className="flex flex-col gap-2">
        {RADIO_OPTIONS.map((option) => (
          <label
            key={option.value}
            htmlFor={`${idPrefix}-lifecycle-${option.value}`}
            className="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300"
          >
            <input
              id={`${idPrefix}-lifecycle-${option.value}`}
              type="radio"
              value={option.value}
              className="size-4 accent-zinc-900 dark:accent-white"
              {...registerAny("lifecycleMode")}
            />
            {t(option.labelKey)}
          </label>
        ))}
      </div>

      {"custom" === mode && (
        <div className="mt-1">
          <Label htmlFor={`${idPrefix}-lifecycle-custom-date`}>{t("lifecycle.customDateLabel")}</Label>
          <Input id={`${idPrefix}-lifecycle-custom-date`} type="date" {...registerAny("customExpiresAt")} />
          {undefined !== errors.customExpiresAt && (
            <ErrorMessage>{String(errors.customExpiresAt.message)}</ErrorMessage>
          )}
        </div>
      )}
    </div>
  );
}
