<!-- docs/features/roles.md -->

## Overview

A **role** is a named set of folder permissions. Roles are the only way to grant users access to folders (apart from the direct folder overrides on user accounts, which are meant for exceptions).

The relationship is:

```
Role
 └── Folder A: Write
 └── Folder B: Read only
 └── Folder C: No delete

User
 └── Role 1
 └── Role 2   ← permissions from both roles are merged
```

For the rules governing how permissions from multiple roles combine, see [Rights](rights.md).

---

## Roles list

The dropdown at the top of the page lists all existing roles. Selecting one loads the **permission matrix** for that role: a tree of all folders with the current permission type shown for each.

---

## Creating a role

Click **New role** to open the definition form.

| Field | Description |
|-------|-------------|
| **Label** | Name of the role, displayed everywhere in the interface |
| **Password complexity** | Minimum password complexity required for items managed by users in this role. Teampass takes the highest complexity requirement across all of a user's roles |
| **Can edit any visible item** | See below |

### "Can edit any visible item"

When this option is checked, users of this role can modify any item they can open, even if another role they hold would normally restrict editing.

> 🔔 This option is a broad privilege override. Use it only for roles explicitly designed for power users. Checking it effectively ignores `NE` and `NDNE` restrictions for the users of this role.

---

## Configuring folder permissions

After selecting a role, the **permission matrix** shows the full folder tree with a permission badge on each row.

### Editing a single folder

Click any folder row to open the **edit sidebar** on the right.

**Step 1 — Choose an access type:**

| Option | Result |
|--------|--------|
| **Write** | Users can create, edit, and delete items |
| **Read** | Users can only view items |
| **No access** | The folder is hidden from users of this role |

**Step 2 — Apply modifiers (Write only):**

When *Write* is selected, two optional restrictions become available:

| Modifier | Effect |
|----------|--------|
| **No delete** | Users can create and edit items but cannot delete them (`ND`) |
| **No edit** | Users can create and delete items but cannot edit them (`NE`) |

Combining both modifiers results in `NDNE`: users can see and create items but cannot modify or delete existing ones.

**Step 3 — Propagate to sub-folders:**

The **Propagate to descendants** checkbox applies the same permission type to all child folders of the selected folder. This avoids having to configure each sub-folder individually on deep trees.

> 💡 Propagation overwrites existing permissions on descendant folders. Use it on a newly created subtree or when you want to reset an entire branch to a uniform access level.

### Bulk operations

Check several folder rows using the checkboxes, then use the **edit sidebar** to apply the same permission to all selected folders at once. The propagation option applies to each selected folder's descendants independently.

---

## Comparing two roles

The **Compare** dropdown (in the filter bar above the matrix) overlays the permissions of a second role on top of the current role's matrix. Folders where the two roles differ are highlighted. This is useful for reviewing inconsistencies before merging roles or adjusting a user's assignment.

---

## Deleting a role

Click **Delete role** and confirm in the modal. A role can only be deleted if no users are currently assigned to it.

> 🔔 Deleting a role immediately removes all folder permissions defined on it. Users who were assigned only this role lose all their folder access.

---

## LDAP group mapping

When LDAP authentication is enabled and the option *AD user roles mapped with their AD groups* is active (in Settings → LDAP), an additional **LDAP synchronization** button appears in the toolbar.

### How it works

1. Teampass reads all groups from the configured AD/LDAP directory.
2. Each AD group can be mapped to one Teampass role.
3. At every login, the user's current AD group memberships are checked and translated into Teampass roles automatically.

This means that adding or removing a user from an AD group changes their Teampass permissions **at their next login** without any manual action required in Teampass.

### Setting up the mapping

1. Click **LDAP synchronization** in the toolbar.
2. The modal shows all AD groups found in the directory.
3. For each group you want to map, select the corresponding Teampass role in the dropdown on that row.
4. Click **Submit** to save the mapping.

Groups that are not mapped to any role are ignored — users in those groups receive no additional permissions from them.

> 💡 A single AD group can only be mapped to one Teampass role. However, a user can belong to multiple AD groups, each mapped to a different role — all the resulting roles are applied simultaneously.

### LDAP mapping and manual roles

Roles assigned via LDAP group mapping coexist with roles assigned manually by an administrator. Both sources are merged when computing effective permissions. The [Rights — most permissive wins](rights.md#multiple-roles--the-most-permissive-wins-rule) rule applies regardless of whether a role came from LDAP or a manual assignment.

> 🔔 A common pitfall: a broad AD group (e.g. "All employees") mapped to a role with wide `Write` permissions on many folders will override more restrictive roles assigned to specific sub-groups. Review the permissions of any role attached to a large AD group carefully.

---

## Password complexity on roles

Each role carries a **password complexity** setting. When a user has multiple roles, Teampass uses the **highest** complexity requirement across all their roles as the effective minimum for that user.

| Role | Complexity |
|------|-----------|
| Viewers | 0 — None |
| Editors | 2 — Medium |
| Admins | 4 — Very strong |

A user with both *Viewers* and *Admins* roles will be required to use very strong passwords (`4`).
