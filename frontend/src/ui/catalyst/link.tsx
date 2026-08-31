import * as Headless from "@headlessui/react";
import type React from "react";
import { forwardRef } from "react";
import { Link as RouterLink } from "react-router-dom";

export type LinkProps = { href: string } & Omit<React.ComponentPropsWithoutRef<"a">, "href">;

// Simplified from e-rezerwacja's version: every link this kit renders is an
// internal app route (no marketing/external links in pouch-app yet), so this
// skips the external-URL branch entirely rather than reimplementing it for a
// case that doesn't occur — see this directory's README.
export const Link = forwardRef(function Link({ href, ...rest }: LinkProps, ref: React.ForwardedRef<HTMLAnchorElement>) {
  return (
    <Headless.DataInteractive>
      <RouterLink to={href} ref={ref} {...rest} />
    </Headless.DataInteractive>
  );
});
