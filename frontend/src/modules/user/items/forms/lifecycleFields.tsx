import type { FieldErrors, UseFormRegister } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { z } from "zod";
import type { ItemLifecycleFields } from "../../../../store/types/item";

// Post-review fix (CR finding #6): FileUploadForm and NoteForm both only sent
// {categoryId, ...}, so every item silently got the backend's 1-day default
// TTL — the product doc requires choosing this at creation time. One radio
// group covering the same choices ItemLifecycleOptions actually accepts
// (keepForever > custom date > ttlPreset > default), shared so both forms
// stay in sync rather than drifting.
const LIFECYCLE_MODES = ["default", "keepForever", "1h", "7d", "30d", "custom"] as const;
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
// actually send — "default" sends nothing at all, matching the pre-existing
// behaviour (backend's own 1-day fallback).
export const toLifecyclePayload = (values: LifecycleFormValues): ItemLifecycleFields => {
  switch (values.lifecycleMode) {
    case "keepForever":
      return { keepForever: true };
    case "custom":
      return undefined !== values.customExpiresAt && "" !== values.customExpiresAt
        ? { expiresAt: values.customExpiresAt }
        : {};
    case "1h":
    case "7d":
    case "30d":
      return { ttlPreset: values.lifecycleMode };
    default:
      return {};
  }
};

interface LifecycleFieldsInputProps<T extends LifecycleFormValues> {
  idPrefix: string;
  register: UseFormRegister<T>;
  errors: FieldErrors<T>;
  mode: LifecycleMode;
}

export function LifecycleFieldsInput<T extends LifecycleFormValues>({
  idPrefix,
  register,
  errors,
  mode,
}: LifecycleFieldsInputProps<T>) {
  const { t } = useTranslation();

  return (
    <div className="lifecycle-fields">
      <span className="lifecycle-fields-label">{t("lifecycle.label")}</span>
      <label htmlFor={`${idPrefix}-lifecycle-default`}>
        <input
          id={`${idPrefix}-lifecycle-default`}
          type="radio"
          value="default"
          // biome-ignore lint/suspicious/noExplicitAny: shared across differently-shaped form value types
          {...(register as UseFormRegister<any>)("lifecycleMode")}
        />
        {t("lifecycle.default")}
      </label>
      <label htmlFor={`${idPrefix}-lifecycle-keep-forever`}>
        <input
          id={`${idPrefix}-lifecycle-keep-forever`}
          type="radio"
          value="keepForever"
          // biome-ignore lint/suspicious/noExplicitAny: shared across differently-shaped form value types
          {...(register as UseFormRegister<any>)("lifecycleMode")}
        />
        {t("lifecycle.keepForever")}
      </label>
      <label htmlFor={`${idPrefix}-lifecycle-1h`}>
        <input
          id={`${idPrefix}-lifecycle-1h`}
          type="radio"
          value="1h"
          // biome-ignore lint/suspicious/noExplicitAny: shared across differently-shaped form value types
          {...(register as UseFormRegister<any>)("lifecycleMode")}
        />
        {t("lifecycle.ttl1h")}
      </label>
      <label htmlFor={`${idPrefix}-lifecycle-7d`}>
        <input
          id={`${idPrefix}-lifecycle-7d`}
          type="radio"
          value="7d"
          // biome-ignore lint/suspicious/noExplicitAny: shared across differently-shaped form value types
          {...(register as UseFormRegister<any>)("lifecycleMode")}
        />
        {t("lifecycle.ttl7d")}
      </label>
      <label htmlFor={`${idPrefix}-lifecycle-30d`}>
        <input
          id={`${idPrefix}-lifecycle-30d`}
          type="radio"
          value="30d"
          // biome-ignore lint/suspicious/noExplicitAny: shared across differently-shaped form value types
          {...(register as UseFormRegister<any>)("lifecycleMode")}
        />
        {t("lifecycle.ttl30d")}
      </label>
      <label htmlFor={`${idPrefix}-lifecycle-custom`}>
        <input
          id={`${idPrefix}-lifecycle-custom`}
          type="radio"
          value="custom"
          // biome-ignore lint/suspicious/noExplicitAny: shared across differently-shaped form value types
          {...(register as UseFormRegister<any>)("lifecycleMode")}
        />
        {t("lifecycle.custom")}
      </label>

      {"custom" === mode && (
        <div className="lifecycle-fields-custom-date">
          <label htmlFor={`${idPrefix}-lifecycle-custom-date`}>{t("lifecycle.customDateLabel")}</label>
          <input
            id={`${idPrefix}-lifecycle-custom-date`}
            type="date"
            // biome-ignore lint/suspicious/noExplicitAny: shared across differently-shaped form value types
            {...(register as UseFormRegister<any>)("customExpiresAt")}
          />
          {undefined !== errors.customExpiresAt && (
            <p className="field-error">{String(errors.customExpiresAt.message)}</p>
          )}
        </div>
      )}
    </div>
  );
}
