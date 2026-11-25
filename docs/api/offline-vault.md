<!-- docs/api/offline-vault.md -->

# Offline vault of the browser extension

The browser extension can keep an **encrypted copy of a user's items** on their computer, so
they can search, view, copy and fill without a connection to TeamPass — on a plane, during an
outage, behind a network that does not reach the server.

The copy is **read-only**, encrypted with the key of the extension's vault lock, and the
extension refuses to make one until the user has set a PIN. It is **erased without any action
from the user** once it has gone too long without a successful synchronization, and as soon as
TeamPass answers that it is no longer allowed.

## Prerequisites (administrator)

| Setting | Where | Why |
| ------- | ----- | --- |
| **API access enabled**, and the **read** right for the user | Settings → API | The copy is read through the API |
| **Allow users to keep an offline vault** (`offline_vault_enabled`) | Settings → API → *Browser extension* | Explicit opt-in. Off by default on new installations and after an upgrade |

Administrators never get an offline vault: they have no access to items.

## Settings

| Setting | Default | Meaning |
| ------- | ------- | ------- |
| Roles allowed (`offline_vault_roles`) | empty = every role | Users holding none of the listed roles are refused |
| Longest retention without a connection (`offline_vault_max_retention_days`) | 14 days (1–365) | Past it without a successful synchronization, the extension erases its copy. Users may choose a shorter time |
| Shortest interval between automatic synchronizations (`offline_vault_min_sync_minutes`) | 60 (15–1440) | Bounds the load each extension puts on the server |
| Include personal folders (`offline_vault_personal_folders`) | on | Lets users copy their personal folders too |
| Include TOTP secrets (`offline_vault_totp_allowed`) | off | On: one-time codes are computed offline. Off: the copy holds no TOTP secret |

Each user then chooses, in the extension's options, which folders to copy, how long to keep the
copy (up to the maximum above) and when to synchronize it.

## What the server sees

- **`GET /api/v1/offlineVault/policy`** at every synchronization. It always answers `200`:

  ```json
  { "enabled": true, "allowed": true, "reason": null,
    "retention_days": { "max": 14, "default": 7 },
    "totp_allowed": false, "personal_folders_allowed": true,
    "min_sync_interval_minutes": 60 }
  ```

  `reason` says why `allowed` is `false`: `disabled`, `admin` or `role_not_allowed`.
- The first synchronization reads the selected folders through `item/inFolders`; the following
  ones read only what changed through `item/changes`. **Every item delivered is logged as shown**
  in its history, exactly like any other API read.

## Withdrawing access

Switching the setting off, or removing the user's role, makes the next synchronization answer
`allowed: false`, and the extension erases its copy at once. A copy on a computer that never
reconnects cannot be reached: it erases itself when its retention runs out. Disabling the user's
account or API access stops the synchronizations; the copy then lives at most until its
retention runs out.

The retention is enforced by the extension on the user's computer. It protects against a lost or
forgotten laptop and a departing employee's machine; it does not stop a user who knows their PIN
from noting their passwords down while they still have access.
