import { createContext, type ReactNode, useContext, useMemo, useState } from "react";

// Shared by every /admin page — the admin picks one pouch (or "every pouch")
// once, here, instead of each page carrying its own separate pouchId state.
// null = every pouch, the same "omit pouchId" default every backend
// endpoint already falls back to.
interface PouchFilterValue {
  pouchId: number | null;
  setPouchId: (pouchId: number | null) => void;
}

const PouchFilterContext = createContext<PouchFilterValue | null>(null);

export function PouchFilterProvider({ children }: { children: ReactNode }) {
  const [pouchId, setPouchId] = useState<number | null>(null);
  const value = useMemo(() => ({ pouchId, setPouchId }), [pouchId]);

  return <PouchFilterContext.Provider value={value}>{children}</PouchFilterContext.Provider>;
}

export function usePouchFilter(): PouchFilterValue {
  const context = useContext(PouchFilterContext);
  if (null === context) {
    throw new Error("usePouchFilter() must be used within a PouchFilterProvider (see AdminLayout)");
  }

  return context;
}
