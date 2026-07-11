<!-- docs/misc/accessibility.md -->

## Overview

TeamPass ships an incremental **accessibility and responsive baseline** on top of AdminLTE 3. It is always on — accessibility is not a feature toggle.

---

## Keyboard navigation

- **Skip link**: the first `Tab` on any page reveals a *Skip to main content* link jumping past the menus.
- **Visible focus**: keyboard focus draws a clear outline on links, buttons and form fields (`:focus-visible` — mouse clicks stay outline-free). The outline adapts to dark mode.
- **Theme toggle**: the dark-mode switch in the top bar is a real focusable button, operable with `Enter`.
- With the [Command palette](../features/command-palette.md) enabled, `Ctrl+K` gives a fully keyboard-driven way to reach any item, folder or page.

## Screen readers

- Icon-only navbar controls (menu burger, control sidebar, theme toggle, notification bell) carry explicit, localised `aria-label`s; their decorative icons are `aria-hidden`.
- The page content area is a `role="main"` landmark.
- The login form fields (login, password, session duration) have `aria-label`s — placeholders are not labels.

## Dark mode

- Toggled from the top bar, persisted in a cookie, and applied **server-side at page generation** (no flash of the wrong theme).
- On a **first visit** (no cookie yet), TeamPass now respects the operating system preference (`prefers-color-scheme`).

## Small screens

The most-used flows (login, search, item view/copy) were reviewed on small viewports:

- Item action buttons wrap with spacing instead of overflowing.
- Modals, dropdown panels, toasts and the command palette stay within the viewport.
- The folder tree stacks above the items list with a visual gap.
- Page headers scale down.

---

## Regression guard

A sentinel unit test (`tests/Unit/AccessibilitySentinelTest.php`) fails the build if the skip link, the ARIA labels, the focusable theme toggle, the OS theme preference, the focus-visible CSS or the login labels are removed during a refactor.
