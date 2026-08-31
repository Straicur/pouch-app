import * as Headless from "@headlessui/react";
import clsx from "clsx";
import type React from "react";
import { Button } from "./button";
import { Link } from "./link";

// Ported from e-rezerwacja-frontend's libs/catalyst-ui — same simplifications as
// button.tsx/dialog.tsx. Trimmed to what this app actually uses (a user-menu
// style dropdown): Dropdown/DropdownButton/DropdownMenu/DropdownItem/
// DropdownDivider — not the full stock-Catalyst set (sections/headings/
// shortcuts), add those back if a real use case shows up.
export function Dropdown(props: Headless.MenuProps) {
  return <Headless.Menu {...props} />;
}

export function DropdownButton<T extends React.ElementType = typeof Button>({
  as = Button,
  className,
  ...props
}: { className?: string } & Omit<Headless.MenuButtonProps<T>, "className">) {
  return <Headless.MenuButton as={as} className={clsx(className, "gap-2.5 justify-center")} {...props} />;
}

export function DropdownMenu({
  anchor = "bottom",
  className,
  ...props
}: { className?: string } & Omit<Headless.MenuItemsProps, "as" | "className">) {
  return (
    <Headless.MenuItems
      {...props}
      transition
      anchor={anchor}
      modal={false}
      className={clsx(
        className,
        "min-w-[var(--button-width)] max-h-60 overflow-y-auto",
        "rounded-md border border-zinc-950/10 dark:border-white/10",
        "bg-white dark:bg-zinc-900",
        "shadow-lg",
        "py-1",
        "transition data-leave:duration-100 data-leave:ease-in data-closed:data-leave:opacity-0",
      )}
    />
  );
}

export function DropdownItem({
  className,
  ...props
}: { className?: string } & (
  | Omit<Headless.MenuItemProps<"button">, "as" | "className">
  | Omit<Headless.MenuItemProps<typeof Link>, "as" | "className">
)) {
  const classes = clsx(
    className,
    "flex w-full items-center gap-2 px-3.5 py-2.5 text-left text-sm text-zinc-950 hover:bg-zinc-950/5 focus:outline-none dark:text-white dark:hover:bg-white/5",
    "data-disabled:opacity-50",
  );

  return "href" in props ? (
    <Headless.MenuItem as={Link} {...props} className={classes} />
  ) : (
    <Headless.MenuItem as="button" type="button" {...props} className={classes} />
  );
}

export function DropdownDivider({
  className,
  ...props
}: { className?: string } & Omit<Headless.MenuSeparatorProps, "as" | "className">) {
  return (
    <Headless.MenuSeparator
      {...props}
      className={clsx(className, "my-1 h-px border-0 bg-zinc-950/5 dark:bg-white/10")}
    />
  );
}
