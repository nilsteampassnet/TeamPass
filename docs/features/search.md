<!-- docs/features/search.md -->

## Overview

The **Search** page lets you find items across every folder you have access to, combining a
text search with a panel of filters (*facets*). Results are displayed in a table and support
mass operations.

---

## Running a search

1. Click **Search** in the left navigation menu.
2. Type your search terms in the search bar, and/or open **Filters** to narrow the results.
3. Results update as you type.

A search starts once you have typed **at least 2 characters** or selected **at least one
filter**. This keeps an unfiltered scan of the whole vault from running on every keystroke.

### Text search

By default the text search covers **Label**, **Login**, **URL** and **Tags**. Use the
**Search in** section of the filter panel to add **Description** or to narrow the search to a
single field.

Several words are combined with **AND**: searching `backup prod` returns only the items that
match *both* words, instead of everything containing either one.

> 🔔 Passwords are **never** searched or returned. Encrypted values — passwords, TOTP secrets
> and encrypted custom fields — cannot be searched at all: the server would have to decrypt
> every item to compare them.

---

## Filters

Each section appears only when the corresponding feature is enabled by your administrator.

| Section | Filters |
|---------|---------|
| **Classification** | Filter by sensitivity label, including *Unclassified*. See [Classification](classification.md). |
| **Security** | Weak, Breached, Overdue, No expiry, Widely shared — plus Reused and Unreadable. Same vocabulary as the security dashboard; see also [Breach detection](breach-detection.md). |
| **Attachments** | Has an attachment, attachment name contains…, and filter by extension. |
| **Dates and rotation** | Created/modified between two dates, flagged for rotation, automatic rotation enabled. |
| **Content and scope** | Tags, personal vs shared, favourites, recently viewed, and custom field values. |

Active filters appear as removable **chips** above the results. Click a chip to drop that
filter, or **Clear all** to start over. Your filters and current page are remembered when you
come back to the page.

### Notes on specific filters

- **Reused** and **Unreadable** rely on data produced by a security scan. If you have never run
  one from the security dashboard, these two filters return nothing. The other security filters
  are computed live and need no scan.
- **Attachment name** searches the *file name*, not the file contents — attachment contents are
  encrypted and cannot be searched.
- **Custom field values** are only searchable for fields that are **not encrypted**. Encrypted
  custom fields are excluded by design, and a field restricted to roles you do not hold is never
  searched: a match would reveal its value.

---

## Results table

| Column | Content |
|--------|---------|
| **Label** | Item name, with its classification badge when the feature is enabled |
| **Login** | Account identifier |
| **Description** | Item description (truncated) |
| **Tags** | Tags attached to the item |
| **URL** | Link to the associated service |
| **Folder** | Folder the item belongs to |

Click the eye icon to expand an item's details without leaving the page.

---

## Scope of search results

Search results are filtered by your permissions:

- Only items in folders you have at least read access to are returned.
- Folders explicitly denied to you are excluded, even if a role would otherwise grant them.
- Other users' personal folders are never searched.
- Items restricted to specific users or roles (see
  [Items — Restricted items](items.md#restricted-items)) are only returned if you are in the
  allowed list, or hold one of the allowed roles.

The filter dropdowns (such as the tag list) are built from the same restricted scope, so they
never reveal the existence of items you cannot see.

---

## Mass operations

You can select multiple items from the results and apply an action to all of them at once:

1. Check the boxes next to the items you want to act on.
2. A **mass operations** menu appears.
3. Choose the action to apply (available actions depend on your permissions).

The checkbox is only offered on items in folders where you have write access.

> 🔔 Mass operations follow the same permission rules as individual item actions. You can only
> apply an operation to items on which you have the required permission level.
