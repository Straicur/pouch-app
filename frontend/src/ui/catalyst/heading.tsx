import clsx from "clsx";
import type React from "react";

// Ported from e-rezerwacja-frontend's libs/catalyst-ui — stock zinc palette,
// no forced Roboto font (see text.tsx's own comment).
type HeadingVariant = "default" | "section" | "page";

interface HeadingProps extends React.ComponentPropsWithoutRef<"h1" | "h2" | "h3" | "h4" | "h5" | "h6"> {
  level?: 1 | 2 | 3 | 4 | 5 | 6;
  variant?: HeadingVariant;
}

const variantStyles: Record<HeadingVariant, string> = {
  default: "",
  section: "text-lg font-semibold mb-4",
  page: "text-2xl font-extrabold",
};

export function Heading({ className, level = 2, variant = "default", ...props }: HeadingProps) {
  const Element = `h${level}` as const;

  return <Element {...props} className={clsx(className, "text-zinc-950 dark:text-white", variantStyles[variant])} />;
}

export function Subheading({ className, level = 2, ...props }: HeadingProps) {
  const Element = `h${level}` as const;

  return (
    <Element
      {...props}
      className={clsx(className, "text-base/7 font-semibold text-zinc-950 sm:text-sm/6 dark:text-white")}
    />
  );
}
