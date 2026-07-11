<!-- docs/features/micro-learning.md -->

## Overview

**Micro-learning** educates non-technical users *in situ*, where it sticks: short, plain-language security tips appear at the **moment of action** — not in a manual nobody reads. It is designed to never get in the way: every tip is dismissible for good, and the whole feature can be muted in one click.

> 🔔 This feature must be enabled by an administrator (**Settings → Options → In-context security micro-learning**). It is disabled by default.

---

## When tips appear

### At the moment of action

| Moment | Tip |
|--------|-----|
| Opening the new-item form | Why a **unique password** per account matters |
| Focusing the password field | **Passphrases**: long beats complex |
| Opening the one-time-view share dialog | Why a **one-time link** beats email/chat |

### One tip a day

A small rotation of general tips (what is MFA, password reuse, phishing, the browser extension, breach checks, rotation, locking your screen) — at most **one per day**, deterministic, and only until the user has dismissed them.

---

## Never blocking

- Tips are toasts: they never interrupt input and auto-close.
- **Got it — don't show again** hides that tip permanently.
- **Mute all tips** switches the feature off entirely for that browser.
- Dismissals are stored client-side (localStorage) — deliberately **data-light**: no server table, no tracking of who read what.

Expert users mute once and never see a tip again.

---

## Localisation

All tips live in the standard language files (`app/includes/language/*.php`) under `microlearning_tip_*` keys. A sentinel unit test guarantees every tip in the catalogue has a non-empty English and French translation.

---

## Permissions & settings

| Setting | Default | Purpose |
|---------|---------|---------|
| `micro_learning_enabled` | `0` | Master switch for the contextual tips + daily rotation |

Tips are pure content: nothing is read from or written to the vault.
