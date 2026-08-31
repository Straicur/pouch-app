import * as Headless from "@headlessui/react";
import clsx from "clsx";
import type React from "react";
import { forwardRef } from "react";
import { Link, type LinkProps } from "./link";
import { TouchTarget } from "./touch-target";

// Ported from e-rezerwacja-frontend's libs/catalyst-ui/src/components/shared/button.tsx —
// same two deliberate simplifications as the rest of src/ui/catalyst/ (see README.md):
// no required `dataTestId` (this project has no data-testid convention), and the
// source's rebranded color tokens (bg-primary/gray-01/gray-02/gray-03/red/orange)
// swapped for the stock zinc/red/orange Tailwind palette already used by
// sidebar.tsx/navbar.tsx, instead of introducing a whole new @theme token set.
const styles = {
  base: [
    "relative",
    "overflow-hidden",
    "inline-flex items-center justify-center",
    "font-medium text-sm",
    "transition-colors duration-200",
    "focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2",
    "disabled:cursor-not-allowed disabled:opacity-50",
    "cursor-pointer",
    "whitespace-nowrap",
  ],
  sizes: {
    normal: ["min-w-[128px] h-[44px]", "px-6 py-[12.5px]"],
    small: ["min-w-[128px] h-[36px]", "px-6 py-[10px]"],
    "x-small": ["min-w-[128px] h-[32px]", "px-6 py-[10px]"],
  },
  plain: ["p-0", "h-10", "w-10", "rounded"],
  variants: {
    primary: [
      "rounded",
      "bg-zinc-900 text-white",
      "hover:bg-zinc-700",
      "active:bg-zinc-700",
      "dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200",
    ],
    red: ["rounded", "bg-red-600 text-white", "hover:bg-red-700", "active:bg-red-700"],
    orange: ["rounded", "bg-orange-500 text-white", "hover:bg-orange-600", "active:bg-orange-600"],
    outline: [
      "rounded",
      "border border-zinc-950/10 dark:border-white/10",
      "bg-transparent text-zinc-950 dark:text-white",
      "hover:bg-zinc-950/5 dark:hover:bg-white/5",
    ],
    framed: [
      "rounded",
      "border border-zinc-900 dark:border-white",
      "bg-white text-zinc-900 dark:bg-transparent dark:text-white",
      "hover:bg-zinc-900 hover:text-white dark:hover:bg-white dark:hover:text-zinc-900",
    ],
    icon: ["rounded-full", "bg-transparent", "p-0"],
  },
};

interface ButtonOwnProps {
  variant?: "primary" | "outline" | "framed" | "icon" | "red" | "orange";
  size?: "normal" | "small" | "x-small";
  plain?: boolean;
  fullWidth?: boolean | "responsive";
  className?: string;
  children: React.ReactNode;
}

type ButtonProps = ButtonOwnProps & (Omit<Headless.ButtonProps, "as" | "className"> | Omit<LinkProps, "className">);

export const Button = forwardRef(function Button(
  { variant = "primary", size = "normal", className, children, plain, fullWidth = false, ...props }: ButtonProps,
  ref: React.ForwardedRef<HTMLElement>,
) {
  const sizeClasses = plain ? styles.plain : styles.sizes[size];
  const variantClasses = plain ? undefined : styles.variants[variant];
  const fullWidthClasses = "responsive" === fullWidth ? "w-full sm:w-auto" : fullWidth ? "w-full" : undefined;

  const classes = clsx(className, styles.base, sizeClasses, variantClasses, fullWidthClasses);
  const content = plain ? children : <TouchTarget>{children}</TouchTarget>;

  return "href" in props ? (
    <Link {...props} className={classes} ref={ref as React.ForwardedRef<HTMLAnchorElement>}>
      {content}
    </Link>
  ) : (
    <Headless.Button {...props} className={classes} ref={ref}>
      {content}
    </Headless.Button>
  );
});
