import * as Headless from "@headlessui/react";
import clsx from "clsx";
import type React from "react";
import { forwardRef } from "react";

// Ported from e-rezerwacja-frontend's libs/catalyst-ui — no dataTestId (see
// button.tsx's comment), border/background/focus-ring classes swapped for the
// same stock zinc/blue palette already used by textarea.tsx/radio.tsx/switch.tsx
// instead of the source's custom gray-01/gray-02/gray-03/primary tokens.
export function InputGroup({ children }: React.ComponentPropsWithoutRef<"span">) {
  return (
    <span
      data-slot="control"
      className={clsx(
        "relative isolate block",
        "has-[[data-slot=icon]:first-child]:[&_input]:pl-10 has-[[data-slot=icon]:last-child]:[&_input]:pr-10 sm:has-[[data-slot=icon]:first-child]:[&_input]:pl-8 sm:has-[[data-slot=icon]:last-child]:[&_input]:pr-8",
        "*:data-[slot=icon]:pointer-events-none *:data-[slot=icon]:absolute *:data-[slot=icon]:top-3 *:data-[slot=icon]:z-10 *:data-[slot=icon]:size-5 sm:*:data-[slot=icon]:top-2.5 sm:*:data-[slot=icon]:size-4",
        "[&>[data-slot=icon]:first-child]:left-3 sm:[&>[data-slot=icon]:first-child]:left-2.5 [&>[data-slot=icon]:last-child]:right-3 sm:[&>[data-slot=icon]:last-child]:right-2.5",
        "*:data-[slot=icon]:text-zinc-500 dark:*:data-[slot=icon]:text-zinc-400",
      )}
    >
      {children}
    </span>
  );
}

const dateTypes = ["date", "datetime-local", "month", "time", "week"] as const;
type DateType = (typeof dateTypes)[number];

export const Input = forwardRef(function Input(
  {
    className,
    ...props
  }: {
    className?: string;
    type?: "email" | "number" | "password" | "search" | "tel" | "text" | "url" | DateType;
  } & Omit<Headless.InputProps, "as" | "className">,
  ref: React.ForwardedRef<HTMLInputElement>,
) {
  return (
    <span data-slot="control" className="relative block w-full">
      <Headless.Input
        ref={ref}
        {...props}
        className={clsx([
          className,
          props.type &&
            (dateTypes as readonly string[]).includes(props.type) && [
              "[&::-webkit-datetime-edit-fields-wrapper]:p-0",
              "[&::-webkit-date-and-time-value]:min-h-[1.5em]",
              "[&::-webkit-datetime-edit]:inline-flex",
              "[&::-webkit-datetime-edit]:p-0",
            ],
          "relative block w-full appearance-none rounded-md px-3 py-2.5",
          "text-sm text-zinc-950 placeholder:text-zinc-500 dark:text-white dark:placeholder:text-zinc-400",
          "bg-transparent dark:bg-white/5",
          "border border-zinc-950/10 data-hover:border-zinc-950/20 dark:border-white/10 dark:data-hover:border-white/20",
          "focus:outline-none focus:border-blue-500 focus:ring-0",
          "data-invalid:border-red-500 data-invalid:data-hover:border-red-500 dark:data-invalid:border-red-600",
          "data-disabled:opacity-50 data-disabled:cursor-not-allowed",
        ])}
      />
    </span>
  );
});
