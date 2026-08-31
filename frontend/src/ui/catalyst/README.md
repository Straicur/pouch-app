# Catalyst UI (ported)

A trimmed adaptation of [Tailwind Plus's Catalyst](https://tailwindcss.com/plus/ui-kit)
component kit — specifically the `SidebarLayout` piece, per an explicit
"worth adopting" note from reviewing `e-rezerwacja-frontend` (Część 11's
"lista bez implementacji" follow-up). Ported by hand from that project's own
`libs/catalyst-ui` (itself an adaptation of the original kit), not installed
as a package — Catalyst is distributed as source you own and edit, not an
npm dependency.

**Deliberately smaller than the source it came from**: only what
`SidebarLayout`/`Sidebar`/`Navbar` actually need (`touch-target`, `link`,
`navbar`, `sidebar`, `sidebar-layout`) — not the full kit (`button`, `dialog`,
`combobox`, `table`, etc.). `link.tsx` is simplified further: e-rezerwacja's
version branches on an external-URL regex from its own `@ereservation/core`
lib that pouch-app has no equivalent of; every link this kit renders here is
an internal app route, so that branch is gone rather than reimplemented for
a case that doesn't occur yet — add it back if/when an actual external link
shows up.

Scoped to `src/modules/*/UserLayout.tsx` / `AdminLayout.tsx` for now — the
rest of the app's plain CSS (`src/index.css`) is untouched, this isn't a full
migration to Tailwind.
