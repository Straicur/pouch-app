import * as Headless from "@headlessui/react";
import clsx from "clsx";

interface MultiSelectOption {
  value: string;
  label: string;
}

interface MultiSelectProps {
  value: string[];
  onChange: (value: string[]) => void;
  options: MultiSelectOption[];
  placeholder: string;
  className?: string;
}

// A real dropdown counterpart to Select's `multiple` prop (a fixed-height
// inline listbox, not a popover — see ItemFilters' original attempt at
// this). Not ported from Catalyst's own source (it has no multi-select of
// its own) — built from Headless UI's Listbox, styled the same way as this
// folder's other components (dropdown.tsx's menu, select.tsx's trigger).
export function MultiSelect({ value, onChange, options, placeholder, className }: MultiSelectProps) {
  const labelByValue = new Map(options.map((option) => [option.value, option.label]));
  const summary =
    0 === value.length ? placeholder : value.map((selected) => labelByValue.get(selected) ?? selected).join(", ");

  return (
    <Headless.Listbox value={value} onChange={onChange} multiple>
      <Headless.ListboxButton
        className={clsx(
          className,
          "relative block w-full truncate rounded-md py-2.5 pr-10 pl-3 text-left text-sm",
          "border border-zinc-950/10 data-hover:border-zinc-950/20 dark:border-white/10 dark:data-hover:border-white/20",
          "bg-transparent dark:bg-white/5",
          0 === value.length ? "text-zinc-500 dark:text-zinc-400" : "text-zinc-950 dark:text-white",
          "focus:outline-none data-focus:border-blue-500",
        )}
      >
        {summary}
        <span className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
          <svg
            className="size-4 stroke-zinc-500 dark:stroke-zinc-400"
            viewBox="0 0 16 16"
            fill="none"
            aria-hidden="true"
          >
            <path d="M4 6L8 10L12 6" strokeWidth={1.5} strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </span>
      </Headless.ListboxButton>
      <Headless.ListboxOptions
        anchor="bottom start"
        transition
        className={clsx(
          "w-[var(--button-width)] max-h-60 overflow-y-auto rounded-md [--anchor-gap:4px]",
          "border border-zinc-950/10 dark:border-white/10",
          "bg-white shadow-lg dark:bg-zinc-900",
          "p-1",
          "transition data-closed:opacity-0 data-leave:duration-100 data-leave:ease-in",
        )}
      >
        {0 === options.length && (
          <div className="px-2 py-1.5 text-sm text-zinc-500 dark:text-zinc-400">{placeholder}</div>
        )}
        {options.map((option) => (
          <Headless.ListboxOption
            key={option.value}
            value={option.value}
            className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm text-zinc-950 select-none data-focus:bg-zinc-950/5 dark:text-white dark:data-focus:bg-white/10"
          >
            {({ selected }) => (
              <>
                <span
                  className={clsx(
                    "flex size-4 shrink-0 items-center justify-center rounded-[3px] border",
                    selected
                      ? "border-zinc-900 bg-zinc-900 dark:border-white dark:bg-white"
                      : "border-zinc-400 dark:border-white/30",
                  )}
                >
                  {selected && (
                    <svg
                      className="size-3 stroke-white dark:stroke-zinc-900"
                      viewBox="0 0 14 14"
                      fill="none"
                      aria-hidden="true"
                    >
                      <path d="M3 8L6 11L11 3.5" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                  )}
                </span>
                {option.label}
              </>
            )}
          </Headless.ListboxOption>
        ))}
      </Headless.ListboxOptions>
    </Headless.Listbox>
  );
}
