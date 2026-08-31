# Catalyst UI (ported)

An adaptation of [Tailwind Plus's Catalyst](https://tailwindcss.com/plus/ui-kit)
component kit, ported by hand from `e-rezerwacja-frontend`'s own
`libs/catalyst-ui`/`libs/ui` (itself an adaptation of the original kit), not
installed as a package — Catalyst is distributed as source you own and edit,
not an npm dependency.

Started in Część 11 with just `SidebarLayout`/`Sidebar`/`Navbar`
(`touch-target`, `link`, `navbar`, `sidebar`, `sidebar-layout`). Część 13
brought over the rest of what the app actually uses: `button`, `dialog`,
`dropdown`, `badge`, `text`, `heading`, and `form/` (`fieldset`, `input`,
`textarea`, `select`, `checkbox`, `radio`, `switch`) — still not the *entire*
source kit (no `combobox`/`table`/`pagination` yet — add them the same way if
a real use case shows up).

Two deliberate differences from the source, consistent across every ported
file:
- **No required `dataTestId` prop.** The source requires one on every
  interactive component (for its own e2e suite) — pouch-app has no
  `data-testid` convention anywhere (tests go through RTL by role/label, see
  `LoginPage.test.tsx`), so it'd be pure noise here.
- **Stock Tailwind palette (`zinc`/`blue`/`red`/`orange`/...), not the
  source's rebranded tokens** (`bg-primary`, `text-primary`, `gray-01`,
  `gray-02`, `gray-03`, bare `red`/`orange` instead of `red-600`/`orange-500`,
  `FONT_FAMILY_ROBOTO`). Those tokens come from a `@theme` this project never
  ported — the already-existing `sidebar.tsx`/`navbar.tsx` (Część 11) already
  used the stock palette, so every later port follows that precedent instead
  of introducing a second, half-defined design-token system.
- `checkbox.tsx`'s ARIA-label fallback used the source's own `useTCore()`
  (from `@ereservation/core`, which this project doesn't have) — replaced
  with this app's own `useTranslation()` (`form.checkboxLabel` in
  `locales/pl.ts`), per `FRONTEND.md`'s "Teksty (i18n)".
- `link.tsx` is simplified further: e-rezerwacja's version branches on an
  external-URL regex from its own `@ereservation/core` lib that pouch-app has
  no equivalent of; every link this kit renders here is an internal app
  route, so that branch is gone rather than reimplemented for a case that
  doesn't occur yet — add it back if/when an actual external link shows up.

**No longer scoped to the sidebar layouts** — since Część 13, Catalyst/Tailwind
is the whole app's standard (see `FRONTEND.md`, "Wersje kluczowych
komponentów"); the rest of `src/index.css`'s plain CSS is being migrated away
from incrementally, not kept as a parallel long-term choice.
