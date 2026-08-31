import * as Headless from "@headlessui/react";
import clsx from "clsx";
import type React from "react";
import { useTranslation } from "react-i18next";

// Ported from e-rezerwacja-frontend's libs/catalyst-ui — no dataTestId, stock
// zinc/blue palette (see button.tsx's comment). The source's `useTCore()` ARIA
// label fallback (from @ereservation/core, which this project doesn't have)
// is replaced with this app's own useTranslation() — see "Teksty (i18n)" in
// FRONTEND.md.
export function CheckboxGroup({ className, ...props }: React.ComponentPropsWithoutRef<"div">) {
  return (
    <div
      data-slot="control"
      {...props}
      className={clsx(
        className,
        "space-y-3",
        "has-data-[slot=description]:space-y-6 has-data-[slot=description]:**:data-[slot=label]:font-medium",
      )}
    />
  );
}

export function CheckboxField({
  className,
  ...props
}: { className?: string } & Omit<Headless.FieldProps, "as" | "className">) {
  return (
    <Headless.Field
      data-slot="field"
      {...props}
      className={clsx(
        className,
        "grid grid-cols-[1.125rem_1fr] gap-x-2 gap-y-1 sm:grid-cols-[1rem_1fr]",
        "*:data-[slot=control]:col-start-1 *:data-[slot=control]:row-start-1 *:data-[slot=control]:mt-0.75 sm:*:data-[slot=control]:mt-1",
        "*:data-[slot=label]:col-start-2 *:data-[slot=label]:row-start-1",
        "*:data-[slot=description]:col-start-2 *:data-[slot=description]:row-start-2",
        "has-data-[slot=description]:**:data-[slot=label]:font-medium",
      )}
    />
  );
}

const base = [
  "relative flex h-[14px] w-[14px] items-center justify-center rounded-[2px]",
  "bg-white dark:bg-white/5",
  "border border-zinc-400 dark:border-white/20",
  "group-data-hover:border-zinc-900 dark:group-data-hover:border-white/40",
  "group-data-checked:border-zinc-900 group-data-checked:bg-zinc-900 dark:group-data-checked:border-white dark:group-data-checked:bg-white",
  "group-data-focus:outline-2 group-data-focus:outline-offset-2 group-data-focus:outline-blue-500",
  "group-data-disabled:opacity-50",
  "transition-colors",
];

export function Checkbox({
  className,
  ...props
}: { className?: string } & Omit<Headless.CheckboxProps, "as" | "className">) {
  const { t } = useTranslation();

  return (
    <Headless.Checkbox
      data-slot="control"
      aria-label={t("form.checkboxLabel")}
      {...props}
      className={clsx(className, "group inline-flex text-sm focus:outline-hidden")}
    >
      <span className={clsx(base)}>
        <svg
          className="h-[10px] w-[10px] stroke-white opacity-0 group-data-checked:opacity-100 dark:stroke-zinc-900"
          viewBox="0 0 14 14"
          fill="none"
          aria-hidden="true"
        >
          <title>Checkmark</title>
          <path
            className="opacity-100 group-data-indeterminate:opacity-0"
            d="M3 8L6 11L11 3.5"
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
          />
          <path
            className="opacity-0 group-data-indeterminate:opacity-100"
            d="M3 7H11"
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </svg>
      </span>
    </Headless.Checkbox>
  );
}
