<!-- docs/features/security-posture.md -->

## Overview

The **security posture** features help every user keep the passwords they can read healthy. They answer one question: *which of my credentials need attention, and why?*

Three features build on each other:

| Feature | What it gives | Main setting |
|---------|---------------|--------------|
| [Breach detection](#breach-detection) | Checks item passwords against the [Have I Been Pwned](https://haveibeenpwned.com/Passwords) database and flags compromised ones | `hibp_enabled` |
| [Security posture page](#security-posture-page) | A personal page listing weak, reused, breached, overdue and widely shared credentials, with a 0–100 security score | `security_dashboard_enabled` |
| [Proactive nudges](#proactive-nudges) | A banner, a marker in the item list and an optional email digest that bring the issues to the user | `security_nudges_enabled` |

All three are disabled by default. They are configured in **Settings → Options → Security & authentication**, in the *Password expiration & breach detection* and *Security posture & user coaching* groups (or search for `hibp` or `posture` in the settings search box).

> 🔔 **Administrators do not see the security posture page, the score or the nudges.** An administrator has no access to items, so there is nothing to assess. The organisation-wide view is the **Vault posture summary** of the [Compliance reports](../manage/compliance-reports.md): counts only, never a password or an item name.

---

## What gets flagged

| Flag | Meaning | Source |
|------|---------|--------|
| **Weak** | The password strength is below *Strong*, or the password is shorter than the minimum length (12 characters by default) | Live |
| **Not assessed** | The password strength has never been measured, typically on items created before strength tracking existed. Opening the item or running a scan measures it | Live |
| **Reused** | The same password is used by several of the items you can read | Last scan |
| **Breached** | The password was found in Have I Been Pwned | Live (stored breach status) |
| **Overdue** | The renewal period has elapsed since the last password change (see [Renewal](renewal.md)) | Live |
| **No expiry** | The item is eligible for renewal but no renewal period applies to it | Live |
| **Widely shared** | More users hold a decryption key for the item than the *widely-shared threshold* (10 by default) | Live |
| **Unreadable** | Your decryption key for this item does not work: you see the item but cannot open its password (see [Tools → Restore missing sharekeys](../manage/tools.md)) | Last scan |

- **Live** flags are recomputed from item metadata every time they are displayed. Nothing is decrypted.
- **Last scan** flags need the password in clear text, which only your own session can decrypt. They are refreshed when you [run a scan](#running-a-scan).

An item with an empty password is neither weak nor unassessed.

Only items you can actually read are assessed: active, not deleted, inside the folders you are currently granted, and not restricted away from you. Your own personal items are included.

---

## Breach detection

Breach detection checks item passwords against the Have I Been Pwned *Pwned Passwords* database, which lists passwords exposed in known data breaches.

### Enabling breach detection

1. Go to **Settings → Options → Security & authentication**, group *Password expiration & breach detection*.
2. Enable **Enable HaveIBeenPwned password check**.
3. Set **Re-check interval (days)**: the number of days before a password is checked again. Default: 7.

### How the check works

When a user opens an item, TeamPass shows the stored breach status right away. If the password has never been checked, or its last check is older than the re-check interval, a new check starts in the background without delaying the display:

1. The server decrypts the password with the key of the user who opened the item.
2. It computes the SHA-1 hash of the password and sends only the **first 5 characters** of that hash to `api.pwnedpasswords.com`.
3. The API returns every known hash suffix starting with those 5 characters. TeamPass compares them with the rest of the hash locally.
4. The result (status, number of occurrences, check date) is stored on the item.

> 🔒 **Privacy — k-anonymity.** Have I Been Pwned never receives the password nor its full hash, only a 5-character prefix shared by hundreds of unrelated hashes. TeamPass also asks for padded responses, so the size of the answer reveals nothing either.

A check that fails (no network, timeout, API error) is silent: the item is not flagged and its previous status is kept.

### Breach status

| Status | What the user sees |
|--------|--------------------|
| **Compromised** | A warning badge next to the item title, with the number of times the password appears in breaches |
| **Not compromised** | No badge |
| **Not checked yet** | No badge — the check starts the next time the item is opened |

The status is stored on the item, so it is shared by every user who can read it. Change a compromised password and save the item: saving clears the stored status, and the next opening checks the new password.

### Requirements

- The server must reach `api.pwnedpasswords.com` over HTTPS (port 443).
- The PHP `curl` extension must be enabled.
- The user opening the item must hold a valid decryption key for it.

---

## Security posture page

When enabled, every non-administrator user gets a **Security posture** entry in the left menu and a **security score** badge in the top bar of every page.

To enable it: **Settings → Options → Security & authentication**, group *Security posture & user coaching*, enable **Security posture dashboard**.

### Running a scan

Click **Scan my passwords**. The scan runs in your own session — a background task could not do it, because it never holds your private key. It processes your items in batches of 50 with a progress bar and, for each password you can read:

- decrypts it with your own key;
- measures its strength when it is not known yet;
- computes a **reuse fingerprint** to detect passwords used more than once;
- if **Also check Have I Been Pwned** is ticked, refreshes its breach status (same check as [above](#how-the-check-works)). The option is only offered when [breach detection](#enabling-breach-detection) is enabled: the scan never calls Have I Been Pwned otherwise;
- records the flags for you, then forgets the clear-text password.

When the scan completes, the reuse flags are computed, the page and your score are refreshed and, when the [Notification centre](notification-center.md) is enabled, a *Security scan finished* notification is added to your inbox. A password whose strength cannot be measured is reported at the end of the scan and kept as *Not assessed*.

> 🔒 **Reuse detection never compares passwords between users.** The fingerprint is a keyed hash (HMAC-SHA-256) salted with a secret of the instance and with your user id. Two users holding the same password get two unrelated fingerprints, so nothing stored in the database can link the passwords of different people. The clear-text password is never stored.

Reuse is evaluated within the set of items *you* can read. When you save a new password, your own flags for that item are refreshed immediately; other users who can read the same item get their reuse flag refreshed at their next scan.

### The security score

The score runs from **0** (worst) to **100** (best). It reflects the share of the passwords you can read that are affected by an issue, weighted by severity:

| Issue | Weight |
|-------|--------|
| Breached | 10 |
| Reused | 4 |
| Weak | 3 |
| Overdue | 2 |

`score = 100 − (sum of weight × number of items) / (number of items you can read × 10) × 100`, rounded and never below 0. A user with no item scores 100.

| Score | Band |
|-------|------|
| 90 and above | Excellent |
| 70 – 89 | Good |
| 40 – 69 | Needs attention |
| Below 40 | Critical |

*Not assessed*, *No expiry*, *Widely shared* and *Unreadable* are shown on the page for information but do not affect the score. Reused passwords are only known after a scan, and breach figures only cover passwords already checked: until your first scan, the page invites you to run one.

Next to the score, the page shows:

- **Top 3 things to fix** — the issues weighing most on your score;
- **Fix the most urgent** — opens the most critical item directly in edit mode;
- the **change since your previous scan** (for example *+12 since last scan*), hidden when the score did not move.

### Working through the flagged items

Each indicator card shows a count. Click a card to filter the list below on that flag; click it again, or **Clear filter**, to remove the filter.

The **Items needing attention** list shows, for each item, its location, the date of the last password change and its issues. It can be narrowed and ordered with:

- a free-text search on name, login, URL or folder;
- a folder selector listing the folders that hold flagged items;
- a sort order: *Most critical first*, *Oldest password first*, *Name (A → Z)* or *Folder*.

The wrench icon on each row opens the item in edit mode. Long lists load 100 rows at a time with **Load more**.

---

## Proactive nudges

Nudges bring the posture results to the user instead of waiting for a visit to the security posture page. They require the security posture page to be enabled.

To enable them: **Settings → Options → Security & authentication**, group *Security posture & user coaching*, enable **Proactive health nudges**.

### In-app banner

Once per session, users with at least one **breached, reused, weak or overdue** password see a banner at the top of the page: *N of your passwords need attention*, with a **Review** link to the security posture page and a **Fix the most urgent** button. **Dismiss** hides it for 24 hours in that browser.

When the user's last scan is older than the **Stale scan threshold** (14 days by default), the banner also invites them to run a new scan.

With [WebSocket](../install/websocket.md) enabled, the score badge refreshes and a short notification appears as soon as a scan finishes, without reloading the page.

### Marker in the item list

Items that are breached, weak, not assessed, reused or overdue carry a shield marker in the item list — *This credential needs attention* — so at-risk credentials stand out while browsing folders.

### Email digest

When **Email digest of at-risk passwords** is also enabled, a daily job (03:30) emails each user who has at least one breached, weak, reused or overdue password. A user receives at most one digest every **Email digest frequency (days)** (7 by default).

The digest contains **counts only** — no password and no item name — and a link to the security posture page. Its text can be customised per language in [Email templates](../manage/email-templates.md).

---

## Where else the flags appear

| Place | What it shows |
|-------|---------------|
| **Item card** | A *needs attention* marker listing *Weak*, *Not assessed* or *Reused* (reuse only when the security posture page is enabled), plus the breach badge. Weak and unassessed markers do not need the security posture page |
| **[Search](search.md)** | A *Security* filter section using the same vocabulary |
| **[Notification centre](notification-center.md)** | *Security scan finished*, with the number of items needing attention |
| **[Compliance reports](../manage/compliance-reports.md)** | The **Vault posture summary**: organisation-wide counts for administrators and auditors |
| **[Rotation tracking](../manage/rotation-tracking.md)** | Overdue rotations, computed with the same rule as the *Overdue* flag |

---

## Settings reference

All settings are in **Settings → Options → Security & authentication**.

| Setting | Default | Description |
|---------|---------|-------------|
| **Enable HaveIBeenPwned password check** (`hibp_enabled`) | Off | Checks item passwords against Have I Been Pwned when they are opened |
| **Re-check interval (days)** (`hibp_check_interval_days`) | 7 | Days before a password is checked again when its item is opened (1 to 365) |
| **Security posture dashboard** (`security_dashboard_enabled`) | Off | Security posture page, security score badge, and the prerequisite of the nudges |
| **Widely-shared threshold (users)** (`security_dashboard_overshared_threshold`) | 10 | An item shared with more users than this is flagged *Widely shared* |
| **Minimum password length (characters)** (`security_dashboard_min_password_length`) | 12 | A shorter password is reported as weak on the item card, the item list, the security posture page and the compliance report. Empty or 0 uses 12 |
| **Proactive health nudges** (`security_nudges_enabled`) | Off | In-app banner and item list marker |
| **Email digest of at-risk passwords** (`security_nudges_email_enabled`) | Off | Periodic counts-only email to each user concerned |
| **Email digest frequency (days)** (`security_nudges_email_frequency_days`) | 7 | Minimum number of days between two digests for the same user |
| **Stale scan threshold (days)** (`security_nudges_stale_scan_days`) | 14 | Beyond this age, the banner invites the user to run a new scan |
