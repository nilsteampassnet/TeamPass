<!-- docs/features/breach-detection.md -->

## Overview

The **breach detection** feature checks item passwords against the [Have I Been Pwned Pwned Passwords](https://haveibeenpwned.com/Passwords) database. When a match is found, a warning is displayed on the item so the password can be changed.

The check is performed server-side using k-anonymity: only the first 5 characters of the SHA-1 hash of the password are transmitted to the HIBP API. The plaintext password never leaves your server.

> 🔒 **Privacy guarantee.** The HIBP API returns a list of hash suffixes matching the sent prefix. The comparison is done locally. HIBP never sees the full hash, let alone the password itself.

---

## Enabling breach detection

Breach detection is disabled by default. To enable it:

1. Go to **Admin → Settings → Breach Detection** (or search for `hibp` in the settings search box).
2. Toggle **Enable HIBP breach detection** to on.
3. Set the **Check interval** (number of days between re-checks for each item). Default: 7.
4. Click **Save**.

| Setting | Description |
|---------|-------------|
| **Enable HIBP breach detection** (`hibp_enabled`) | Activates the feature globally |
| **Check interval (days)** (`hibp_check_interval_days`) | Minimum number of days between two HIBP checks on the same item. Default: 7 |

---

## How it works

When a user opens an item that has breach detection enabled and whose last check is older than the configured interval, Teampass:

1. Decrypts the item password in the user's browser session (server-side only — the plaintext is never sent to the client during this step).
2. Computes the SHA-1 hash and sends the first 5 characters to `api.pwnedpasswords.com`.
3. Compares the returned hash suffixes locally to determine if the full hash matches.
4. Stores the result (`hibp_status`, `hibp_count`, `hibp_checked_at`) in `teampass_items`.

The check is silent on network failure — if the HIBP API is unreachable, the item is not flagged and the existing status is preserved.

---

## Breach status

| Status | Meaning |
|--------|---------|
| **Safe** | Password not found in any known breach |
| **Pwned** | Password found in at least one breach — the breach count is displayed |
| *(no status)* | Item has not been checked yet, or the feature was disabled at check time |

When an item is flagged as **Pwned**, a warning badge is shown on the item row and in the item detail panel. Change the password and save to trigger a fresh check.

---

## Requirements

- The server must be able to reach `api.pwnedpasswords.com` over HTTPS (port 443).
- The PHP `curl` extension must be enabled.
- Items must have at least one sharekey available for the viewing user (i.e. the encryption layer must be intact).
