<!-- docs/features/rights.md -->

## Overview

> 🔔 **Administrator ≠ access to items.** The Administrator privilege in Teampass is a purely administrative role: it grants access to configuration pages (users, roles, folders, settings, logs) but **not** to folder contents or items. Item access is always and exclusively controlled through roles or direct folder grants, for every account without exception.

In Teampass, access to items is controlled at the **folder** level through a layered system:

```
User account
  ├── Roles  (one or more)
  │     └── Folder permissions  (one permission type per folder per role)
  │               └── Effective access  (resolved when the user has multiple roles)
  ├── Allowed folders  (direct grants — always full write, override role-based read-only)
  └── Forbidden folders  (explicit denials — override everything, including direct grants)
```

The three layers are evaluated in priority order:

> **Forbidden folders** (highest priority) › **Direct folder grants** › **Role-based permissions** (lowest priority)

---

## Direct folder grants and forbidden folders

In addition to role-based permissions, an administrator can assign **per-user folder overrides** directly on a user account. These operate independently of roles and take priority over them.

### Allowed folders (direct grants)

An administrator can designate specific folders as **explicitly allowed** for a given user (configured in the Users page, "Folder rights" section).

- The access granted is always **full write (`W`)**, regardless of what any role would give.
- If a role would otherwise restrict that folder to read-only, the direct grant wins and the user gets write access.
- These folders are visible in the "Visible folders" diagnostic modal with a **"Specific"** badge (no role badge) to distinguish them from role-based entries.

> 💡 Use direct grants when a user needs elevated access to one specific folder without changing their roles or creating a dedicated role for the exception.

### Forbidden folders (explicit denials)

An administrator can also designate specific folders as **explicitly forbidden** for a given user.

- A forbidden folder is **completely invisible** to that user — no access whatsoever.
- Forbidden folders override **everything**: role-based permissions and direct grants alike.
- Even if a role grants write access to a folder, adding it to the forbidden list removes all access.

> 🔔 Forbidden folders are the strongest override in the system. They are useful for blocking access to sensitive folders without having to restructure roles.

### Priority summary

| Source | Effective access | Can be overridden by |
|--------|-----------------|---------------------|
| Role-based permission | `W`, `ND`, `NE`, `NDNE`, or `R` | Direct grant (upgrades R→W), forbidden folder (removes all) |
| Direct grant (allowed folder) | Always `W` | Forbidden folder only |
| Forbidden folder | No access | Nothing — highest priority |

### Storage

| Layer | Database table | Column |
|-------|---------------|--------|
| Allowed folders | `teampass_users_groups` | `group_id` (= folder ID) |
| Forbidden folders | `teampass_users_groups_forbidden` | `group_id` (= folder ID) |

---

## Permission types

Each role defines one permission type per folder it covers:

| Type | Label | Create item | Edit item | Delete item |
|------|-------|:-----------:|:---------:|:-----------:|
| `W`    | Write              | ✅ | ✅ | ✅ |
| `ND`   | Write, no delete   | ✅ | ✅ | ❌ |
| `NE`   | Write, no edit     | ✅ | ❌ | ✅ |
| `NDNE` | Write, no edit, no delete | ✅ | ❌ | ❌ |
| `R`    | Read only          | ❌ | ❌ | ❌ |

> 💡 "Create item" means the user can add new items inside the folder. "Edit" and "Delete" refer to existing items.

> 💡 **Moving an item** to another folder requires **Delete** on the folder it leaves and **Edit** on the folder it enters. A user with `ND` cannot move items out of a folder, and a user with `NE` or `NDNE` cannot move items into it. The rule is the same for the move action, the bulk move, the item edit form and the API.

---

## How roles and permissions combine

### Single role

When a user has exactly one role, their access to a folder is simply the permission type defined on that role for that folder. If the role has no entry for a given folder, the user has no access to it.

### Multiple roles — the "least permissive wins" rule

A user can have several roles simultaneously (manually assigned by an administrator, or inherited from LDAP/AD groups — see [Authentication](authentication.md)).

When two or more roles define a permission on the **same folder**, Teampass applies a deterministic resolution rule:

> **The least permissive permission type always wins.**

The priority order is: `R` > `NDNE` > `NE` = `ND` > `W`

**Special case — `ND` + `NE`:** if one role grants `ND` (no delete) and another grants `NE` (no edit) on the same folder, the result is `NDNE` (no edit, no delete). Both restrictions are combined.

#### Examples

| Role A on folder X | Role B on folder X | Effective access |
|--------------------|-------------------|-----------------|
| `W`                | `R`               | `R` — read only wins |
| `ND`               | `R`               | `R` — read only wins |
| `ND`               | `NE`              | `NDNE` — both restrictions apply |
| `R`                | `R`               | `R` — read only |
| `W`                | `ND`              | `ND` — restriction wins |

#### Practical implication

> 🔔 **A write role cannot override a more restrictive role on the same folder.** If a user belongs to a broad group that grants `W` and a more specific group that grants `R`, the effective access will be `R`.

To grant write access, you must ensure that **none** of the user's roles grants a more restrictive type (`R`, `NDNE`, `NE`, or `ND`) on that folder.

---

## Permission sources

A user's roles can come from two sources, which are merged together:

| Source | How it is assigned | Stored as |
|--------|-------------------|-----------|
| **Manual** | Administrator assigns a role directly to the user in the Users page | `source = 'manual'` in `teampass_users_roles` |
| **LDAP / AD groups** | At login, the user's AD group memberships are looked up and mapped to Teampass roles via the LDAP group mapping table | `source = 'ad'` in `teampass_users_roles` |

Both sources are combined before resolving effective permissions. The same "least permissive wins" rule applies regardless of source.

> 💡 LDAP group mapping is configured in **Settings → LDAP** and **Roles → LDAP synchronization**. See [Roles](roles.md) for setup instructions.

---

## Folder visibility

A user only sees folders they have at least one permission on — whether from a role, a direct grant, or both. Folders with no matching entry are invisible.

Forbidden folders are never shown, even if a role or direct grant would otherwise give access. They are completely absent from the user's folder tree.

The folder tree also reflects read-only status: folders where the effective permission is `R` are shown but the "Add item" button is hidden and item edit/delete actions are disabled.

---

## Diagnosing permissions for a user

An administrator can inspect the exact permissions a specific user has on every folder directly from the **Users** page:

1. Open **Users** in the administration menu.
2. Click the action menu next to a user and select **Visible folders**.
3. A modal opens with the full list of folders the user can access, including:
   - The **effective permission type** (resolved from all roles and direct grants).
   - The **contributing roles** — each role that has an entry for that folder, shown as a badge with its individual type.
   - A **"Specific"** badge (no role name) for folders granted via a direct user assignment rather than a role.

This view makes it straightforward to identify which role or direct grant is responsible for a given level of access. For example, if a user has `W` access to a folder that all their roles restrict to `R`, a "Specific" badge will confirm it comes from a direct grant.

> 💡 Use the **filter bar** at the top of the modal to isolate folders by permission type. For instance, filtering on `W` shows only folders where the user has full write access.

---

## Summary

```
User
 ├── Role A  ──► Folder 1: W,  Folder 2: R
 ├── Role B  ──► Folder 1: R,  Folder 3: ND
 ├── Allowed folder (direct grant) ──► Folder 2: W  (overrides role R → W)
 └── Forbidden folder ──► Folder 3: no access  (overrides role ND → blocked)

Result:
  Folder 1: R   (R beats W — least permissive role wins)
  Folder 2: W   (direct grant overrides role-based R)
  Folder 3: —   (forbidden folder overrides role-based ND)
```

The effective permission on each folder is calculated independently, applying all three layers in priority order:

1. **Forbidden folder** — blocks the folder entirely, no matter what roles or direct grants say.
2. **Direct grant** — gives full write (`W`), overriding any role-based read-only on that folder.
3. **Role-based permission** — least-permissive role wins when multiple roles apply.
