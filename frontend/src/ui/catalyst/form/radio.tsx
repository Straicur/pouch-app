import * as Headless from "@headlessui/react";
import clsx from "clsx";

// Ported near-verbatim from e-rezerwacja-frontend's libs/catalyst-ui — already
// stock Tailwind colors/CSS custom properties, only dataTestId dropped (see
// button.tsx's comment). Trimmed to the colors this app actually uses.
export function RadioGroup({
  className,
  ...props
}: { className?: string } & Omit<Headless.RadioGroupProps, "as" | "className">) {
  return (
    <Headless.RadioGroup
      data-slot="control"
      {...props}
      className={clsx(
        className,
        "space-y-3",
        "**:data-[slot=label]:font-normal",
        "has-data-[slot=description]:space-y-6 has-data-[slot=description]:**:data-[slot=label]:font-medium",
      )}
    />
  );
}

export function RadioField({
  className,
  ...props
}: { className?: string } & Omit<Headless.FieldProps, "as" | "className">) {
  return (
    <Headless.Field
      data-slot="field"
      {...props}
      className={clsx(
        className,
        "grid grid-cols-[1.125rem_1fr] gap-x-4 gap-y-1 sm:grid-cols-[1rem_1fr]",
        "*:data-[slot=control]:col-start-1 *:data-[slot=control]:row-start-1 *:data-[slot=control]:mt-0.75 sm:*:data-[slot=control]:mt-1",
        "*:data-[slot=label]:col-start-2 *:data-[slot=label]:row-start-1",
        "*:data-[slot=description]:col-start-2 *:data-[slot=description]:row-start-2",
        "has-data-[slot=description]:**:data-[slot=label]:font-medium",
      )}
    />
  );
}

const base = [
  "relative isolate flex size-[1.1875rem] shrink-0 rounded-full sm:size-[1.0625rem]",
  "before:absolute before:inset-0 before:-z-10 before:rounded-full before:bg-white before:shadow-sm",
  "group-data-checked:before:bg-(--radio-checked-bg)",
  "dark:before:hidden",
  "dark:bg-white/5 dark:group-data-checked:bg-(--radio-checked-bg)",
  "border border-zinc-950/15 group-data-checked:border-transparent group-data-hover:group-data-checked:border-transparent group-data-hover:border-zinc-950/30 group-data-checked:bg-(--radio-checked-border)",
  "dark:border-white/15 dark:group-data-checked:border-white/5 dark:group-data-hover:group-data-checked:border-white/5 dark:group-data-hover:border-white/30",
  "after:absolute after:inset-0 after:rounded-full after:shadow-[inset_0_1px_--theme(--color-white/15%)]",
  "dark:after:-inset-px dark:after:hidden dark:after:rounded-full dark:group-data-checked:after:block",
  "[--radio-indicator:transparent] group-data-checked:[--radio-indicator:var(--radio-checked-indicator)] group-data-hover:group-data-checked:[--radio-indicator:var(--radio-checked-indicator)] group-data-hover:[--radio-indicator:var(--color-zinc-900)]/10",
  "dark:group-data-hover:group-data-checked:[--radio-indicator:var(--radio-checked-indicator)] dark:group-data-hover:[--radio-indicator:var(--color-zinc-700)]",
  "group-data-focus:outline group-data-focus:outline-2 group-data-focus:outline-offset-2 group-data-focus:outline-blue-500",
  "group-data-disabled:opacity-50",
  "group-data-disabled:border-zinc-950/25 group-data-disabled:bg-zinc-950/5 group-data-disabled:[--radio-checked-indicator:var(--color-zinc-950)]/50 group-data-disabled:before:bg-transparent",
  "dark:group-data-disabled:border-white/20 dark:group-data-disabled:bg-white/[2.5%] dark:group-data-disabled:[--radio-checked-indicator:var(--color-white)]/50 dark:group-data-checked:group-data-disabled:after:hidden",
];

const colors = {
  "dark/zinc": [
    "[--radio-checked-bg:var(--color-zinc-900)] [--radio-checked-border:var(--color-zinc-950)]/90 [--radio-checked-indicator:var(--color-white)]",
    "dark:[--radio-checked-bg:var(--color-zinc-600)]",
  ],
  blue: "[--radio-checked-indicator:var(--color-white)] [--radio-checked-bg:var(--color-blue-600)] [--radio-checked-border:var(--color-blue-700)]/90",
  red: "[--radio-checked-indicator:var(--color-white)] [--radio-checked-bg:var(--color-red-600)] [--radio-checked-border:var(--color-red-700)]/90",
  green:
    "[--radio-checked-indicator:var(--color-white)] [--radio-checked-bg:var(--color-green-600)] [--radio-checked-border:var(--color-green-700)]/90",
};

type Color = keyof typeof colors;

export function Radio({
  color = "dark/zinc",
  className,
  ...props
}: { color?: Color; className?: string } & Omit<Headless.RadioProps, "as" | "className" | "children">) {
  return (
    <Headless.Radio
      data-slot="control"
      {...props}
      className={clsx(className, "group inline-flex focus:outline-hidden")}
    >
      <span className={clsx([base, colors[color]])}>
        <span
          className={clsx(
            "size-full rounded-full border-[4.5px] border-transparent bg-(--radio-indicator) bg-clip-padding",
            "forced-colors:border-[Canvas] forced-colors:group-data-checked:border-[Highlight]",
          )}
        />
      </span>
    </Headless.Radio>
  );
}
