import * as Headless from "@headlessui/react";
import clsx from "clsx";
import type React from "react";
import { forwardRef, useState } from "react";

// Ported from e-rezerwacja-frontend's libs/catalyst-ui — no dataTestId, stock
// zinc/blue palette instead of gray-01/gray-02/gray-03/primary/red (see
// button.tsx's comment).
export const Select = forwardRef(function Select(
  { className, multiple, ...props }: { className?: string } & Omit<Headless.SelectProps, "as" | "className">,
  ref: React.ForwardedRef<HTMLSelectElement>,
) {
  const [isOpen, setIsOpen] = useState(false);

  return (
    <span data-slot="control" className={clsx([className, "relative block w-full"])}>
      <Headless.Select
        ref={ref}
        multiple={multiple}
        {...props}
        onClick={() => setIsOpen((prev) => !prev)}
        onChange={(event) => {
          setIsOpen(false);
          props.onChange?.(event);
        }}
        onBlur={() => setIsOpen(false)}
        className={clsx([
          "relative block w-full appearance-none rounded-md",
          "h-9 min-h-9",
          multiple ? "px-3" : "pr-10 pl-3",
          "py-1 pt-1.5",
          "[&_optgroup]:font-semibold",
          "text-sm text-zinc-950 dark:text-white",
          "bg-transparent dark:*:bg-zinc-800",
          "border border-zinc-950/10 dark:border-white/10",
          "focus:outline-none focus:border-blue-500 focus:ring-0",
          "data-invalid:border-red-500 data-invalid:focus:border-red-500",
          "data-disabled:opacity-50 data-disabled:cursor-not-allowed",
        ])}
      />
      {!multiple && (
        <span className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
          <svg
            className={clsx(
              "size-4 stroke-zinc-500 transition-transform duration-200 dark:stroke-zinc-400",
              isOpen && "rotate-180",
            )}
            viewBox="0 0 16 16"
            aria-hidden="true"
            fill="none"
          >
            <path d="M4 6L8 10L12 6" strokeWidth={1.5} strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </span>
      )}
    </span>
  );
});
