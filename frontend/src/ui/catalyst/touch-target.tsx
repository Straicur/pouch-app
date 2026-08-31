import type React from "react";

// Expands the hit area to at least 44x44px on touch devices, without
// affecting the visible size of small nav items — see sidebar.tsx/navbar.tsx.
export function TouchTarget({ children }: { children: React.ReactNode }) {
  return (
    <>
      <span
        className="pointer-fine:hidden absolute top-1/2 left-1/2 size-[max(100%,2.75rem)] -translate-x-1/2 -translate-y-1/2"
        aria-hidden="true"
      />
      {children}
    </>
  );
}
