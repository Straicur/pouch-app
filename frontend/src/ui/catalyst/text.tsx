import clsx from "clsx";
import type React from "react";
import { type LinkProps as BaseLinkProps, Link } from "./link";

// Ported from e-rezerwacja-frontend's libs/catalyst-ui — same simplifications as
// button.tsx (stock zinc/red palette, no forced Roboto font — this app's default
// font stack, set in index.css, applies as-is).
interface TextProps extends React.ComponentPropsWithoutRef<"p"> {
  variant?: "primary" | "red";
  size?: "normal" | "small";
}

export function Text({ className, variant = "primary", size = "normal", ...props }: TextProps) {
  const variantClasses = "red" === variant ? "text-red-600 dark:text-red-500" : "text-zinc-950 dark:text-white";
  const sizeClasses = "small" === size ? "text-xs" : "text-sm";

  return <p data-slot="text" {...props} className={clsx(className, variantClasses, sizeClasses)} />;
}

interface TextLinkProps extends BaseLinkProps {
  variant?: "primary" | "red";
  size?: "normal" | "small";
}

export function TextLink({ className, variant = "primary", size = "normal", ...props }: TextLinkProps) {
  const variantClasses =
    "red" === variant
      ? "text-red-600 hover:text-red-700"
      : "text-zinc-950 hover:text-zinc-700 dark:text-white dark:hover:text-zinc-300";
  const sizeClasses = "small" === size ? "text-xs" : "text-sm";

  return <Link {...props} className={clsx(className, variantClasses, sizeClasses, "no-underline hover:underline")} />;
}

export function Strong({ className, ...props }: React.ComponentPropsWithoutRef<"strong">) {
  return <strong {...props} className={clsx(className, "font-medium text-zinc-950 dark:text-white")} />;
}

export function Code({ className, ...props }: React.ComponentPropsWithoutRef<"code">) {
  return (
    <code
      {...props}
      className={clsx(
        className,
        "rounded-sm border border-zinc-950/10 bg-zinc-950/[2.5%] px-0.5 text-sm font-medium text-zinc-950 sm:text-[0.8125rem] dark:border-white/20 dark:bg-white/5 dark:text-white",
      )}
    />
  );
}
