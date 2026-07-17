<!-- docs/features/command-palette.md -->

## Overview

The **command palette** is a keyboard-first global search: press **Ctrl+K** (⌘K on macOS) anywhere in TeamPass and start typing — one box searches **items**, **folders** and the **pages** of the left menu, with full keyboard navigation.

> 🔔 This feature must be enabled by an administrator (**Settings → Options → Command palette**). It is disabled by default.

---

## Usage

| Key | Action |
|-----|--------|
| `Ctrl+K` / `⌘K` | Open (or close) the palette |
| type ≥ 2 characters | Search as you type (debounced) |
| `↑` / `↓` | Move the selection |
| `Enter` | Open the selected result |
| `Esc` | Close |

### What is searched

- **Items** — label, login, URL and tags, from the same ACL-bound search cache the Find page uses. Selecting an item jumps straight to it (`folder + item` deep link).
- **Folders** — by title, within the folders you can see. Selecting one opens it in the items view.
- **Pages** — the entries of your left menu (Items, Search, Import, Favourites…), indexed client-side from the rendered sidebar, so it automatically reflects what *you* are allowed to access.

Results are ranked by relevance: exact match, then prefix, then earliest substring — `gitlab` beats `my gitlab` when you type `git`.

---

## Security

- **Folder ACLs are enforced server-side**: the query is strictly scoped to the session user's accessible folders, and foreign personal folders are excluded with the same rule as the Find page.
- **No secret is ever returned**: the endpoint reads only labels, logins, URLs, tags and folder titles — never a password value.
- The typed term is escaped against `LIKE` wildcard injection (`%`, `_`).
- Admin accounts don't load the palette (they have no item access in TeamPass).

---

## Permissions & settings

| Setting | Default | Purpose |
|---------|---------|---------|
| `command_palette_enabled` | `0` | Master switch for the Ctrl+K palette |
