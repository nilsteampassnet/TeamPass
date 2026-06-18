<!-- docs/features/users.md -->

## Overview

The **Users** page is the central place for managing accounts in Teampass. An administrator can create, edit, disable, or delete users, assign roles, control folder access, and inspect the permissions currently in effect for any user.

---

## Users list

The page opens on a table listing all accounts. Each row shows the login, full name, email address, assigned role label, account status, admin flag, and MFA status.

The gear icon at the left of each row opens the **action menu** for that user.

### Action menu

| Action | Effect |
|--------|--------|
| **Edit** | Opens the edit form for that user |
| **Change password** | Resets the local login password (local accounts only) |
| **Bruteforce reset** | Unlocks an account blocked after too many failed attempts |
| **Generate new keys** | Regenerates the user's encryption key pair (used after a forced password reset) |
| **See logs** | Displays the audit log of that user's actions |
| **Email Google Auth QR** | Sends the TOTP setup QR code by email |
| **Visible folders** | Opens the [permissions inspector](#inspecting-a-users-permissions) modal |
| **Disable / Enable** | Toggles account active state without deleting it |
| **Delete** | Permanently removes the account |

---

## Toolbar

| Button | Effect |
|--------|--------|
| **New** | Opens the user creation form |
| **Propagate** | Copies the role and folder configuration of one user to a selection of others |
| **LDAP sync** | Opens the LDAP synchronization assistant (visible if LDAP is enabled) |
| **OAuth2 sync** | Opens the Azure Entra synchronization assistant (visible if OAuth2 is enabled) |
| **Inactive users** | Lists accounts without recent web login or functional API/extension activity for 90, 180, or 365+ days, with a batch deletion option |
| **Deleted users** | Lists previously deleted accounts with an option to restore them |

---

## Creating or editing a user

### Identity

| Field | Description |
|-------|-------------|
| **Name** | First name |
| **Last name** | Last name |
| **Login** | Username used to authenticate. Auto-suggested from name + last name, must be unique |
| **Email** | Email address, used for notifications and MFA enrollment |

### Privilege level

The privilege level is a single choice among five mutually exclusive options:

| Level | Description |
|-------|-------------|
| **Administrator** | Full access to all administration and configuration pages (users, roles, folders, settings, logs…). **Administrators do not have access to items or folder contents by design** — item access is always controlled through roles, regardless of privilege level |
| **HR Manager** | Can manage all users (create, edit, delete), including standard users and managers |
| **Manager** | Can manage users assigned to them via the *Managed by* field |
| **User** | Standard account; folder access is determined entirely by assigned roles |
| **Read-only** | Can view items in accessible folders but cannot create, edit, or delete anything |

> 🔔 Only an Administrator can assign the Administrator or HR Manager privilege to another account.

### Roles

The **Roles** field (multi-select) links the user to one or more Teampass roles. Roles define which folders the user can access and with what permission level. See [Rights](rights.md) for the full explanation of how multiple roles combine.

### Folder overrides

Beyond roles, two fields allow direct folder-level adjustments:

| Field | Effect |
|-------|--------|
| **Authorized folders** | Grants access to specific folders regardless of the user's roles. Access type is Write (`W`) |
| **Forbidden folders** | Explicitly blocks access to specific folders, overriding any role that would otherwise grant access |

> 💡 Folder overrides are meant for exceptional cases. Prefer using roles for systematic access control.

### Feature flags

| Option | Effect |
|--------|--------|
| **Can create root folder** | Allows this user to create top-level folders |
| **Create personal folder** | Creates a private folder visible only to this user. Enabled automatically if the global personal folder feature is on |
| **MFA enabled** | Requires the user to authenticate with a second factor (TOTP or Duo). Shown only if an MFA method is configured globally |

---

## Account lifecycle and LDAP

### Local accounts

Created and managed entirely within Teampass. Password changes, key regeneration, and all privilege adjustments are done manually by an administrator.

### LDAP / Active Directory accounts

When LDAP authentication is enabled, users are authenticated against the AD server at every login. Teampass does **not** automatically create accounts from the directory — an administrator must synchronize them manually via **LDAP sync**.

Once created, an LDAP user's group memberships are read at each login. If AD group mapping is configured (see [Roles](roles.md#ldap-group-mapping)), those groups are translated into Teampass roles automatically. The roles assigned via LDAP appear alongside any manually assigned roles.

> 🔔 If an LDAP user is removed from an AD group, their corresponding Teampass role is revoked at the **next login**, not immediately.

### OAuth2 / Azure Entra accounts

Users authenticated through Azure Entra are created automatically on first login. Their AD group memberships (if mapped) are applied the same way as LDAP users. See [Authentication](authentication.md) for the full OAuth2 setup.

---

## Inactive and deleted users

### Inactive users

The **Inactive users** view groups accounts by last recorded activity: 90 days, 180 days, and over one year. Web logins and functional API/extension item actions count as activity; authentication, token refresh, settings refresh, and folder list refreshes do not count on their own. This helps identify accounts that should be disabled or removed.

A batch deletion button is available to purge selected accounts permanently.

### Deleted users

Accounts deleted through the action menu are kept in an archive for a period before being fully purged. The **Deleted users** view allows restoring an account if the deletion was accidental.

---

## Inspecting a user's permissions

The **Visible folders** action opens a modal showing exactly which folders the user can access and with what effective permission.

The modal has three columns:

| Column | Content |
|--------|---------|
| **Folder** | Folder name with hierarchy indentation |
| **Accesses** | Effective permission icons (create / edit / delete) |
| **Roles** | Badges showing each role that contributes to this folder, with its individual permission type |

The **filter bar** at the top of the modal allows narrowing the list to one or more permission types (W, ND, NE, NDNE, R). This is useful for quickly finding all folders where a user has full write access, or all read-only folders.

> 💡 When a user has unexpectedly high access to a folder, the Roles column immediately reveals which role is responsible. See [Rights — least permissive wins](rights.md#multiple-roles--the-least-permissive-wins-rule) for the underlying logic.

---

## Propagating rights

The **Propagate** action copies the complete role and folder access configuration from a source user to one or more target users. This is useful when onboarding several users with identical access requirements.

> 🔔 Propagation replaces the target users' existing role assignments. Make sure the source user's configuration is correct before applying.
