import * as Headless from "@headlessui/react";
import clsx from "clsx";
import type React from "react";
import { Text } from "./text";

// Ported from e-rezerwacja-frontend's libs/catalyst-ui — same two simplifications
// as button.tsx (no dataTestId, stock zinc palette instead of custom tokens).
interface DialogOwnProps {
  className?: string;
  children: React.ReactNode;
  dismissible?: boolean;
}

export function Dialog({
  className,
  children,
  dismissible = true,
  ...props
}: DialogOwnProps &
  Omit<Headless.DialogProps, "as" | "className" | "onClose"> & { onClose?: Headless.DialogProps["onClose"] }) {
  const safeOnClose = props.onClose ?? (() => {});

  return (
    <Headless.Dialog {...props} onClose={dismissible ? safeOnClose : () => {}}>
      <Headless.DialogBackdrop
        transition
        className="fixed inset-0 flex w-screen justify-center overflow-y-auto bg-zinc-950/25 px-2 py-2 transition duration-100 focus:outline-0 data-closed:opacity-0 data-enter:ease-out data-leave:ease-in sm:px-6 sm:py-8 lg:px-8 lg:py-16"
      />

      <div className="fixed inset-0 w-screen overflow-y-auto pt-6 sm:pt-0">
        <div className="grid min-h-full grid-rows-[1fr_auto] justify-items-center sm:grid-rows-[1fr_auto_3fr] sm:p-4">
          <Headless.DialogPanel
            transition
            className={clsx(
              className,
              "row-start-2 h-auto w-full max-w-[752px] min-w-0 gap-5 rounded-2xl bg-white p-4 shadow-lg ring-1 ring-zinc-950/10 sm:p-8 dark:bg-zinc-900 dark:ring-white/10 forced-colors:outline",
              "transition duration-100 will-change-transform data-closed:translate-y-12 data-closed:opacity-0 data-enter:ease-out data-leave:ease-in sm:data-closed:translate-y-0 sm:data-closed:data-enter:scale-95",
            )}
          >
            {children}
          </Headless.DialogPanel>
        </div>
      </div>
    </Headless.Dialog>
  );
}

export function DialogTitle({
  className,
  centered = false,
  ...props
}: { className?: string; centered?: boolean } & Omit<Headless.DialogTitleProps, "as" | "className">) {
  return (
    <Headless.DialogTitle
      {...props}
      className={clsx(className, "text-xl font-bold text-zinc-950 dark:text-white", centered && "text-center")}
    />
  );
}

export function DialogDescription({
  className,
  ...props
}: { className?: string } & Omit<Headless.DescriptionProps<typeof Text>, "as" | "className">) {
  return (
    <Headless.Description as={Text} {...props} className={clsx(className, "mt-1 text-zinc-500 dark:text-zinc-400")} />
  );
}

export function DialogBody({
  className,
  centered = false,
  ...props
}: React.ComponentPropsWithoutRef<"div"> & { centered?: boolean }) {
  return (
    <div {...props} className={clsx(className, "mt-6 text-zinc-700 dark:text-zinc-300", centered && "text-center")} />
  );
}

export function DialogActions({ className, ...props }: React.ComponentPropsWithoutRef<"div">) {
  return (
    <div
      {...props}
      className={clsx(
        className,
        "mt-8 flex flex-col-reverse items-center justify-end gap-3 *:w-full sm:flex-row sm:*:w-auto",
      )}
    />
  );
}
