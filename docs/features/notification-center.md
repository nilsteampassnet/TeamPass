<!-- docs/features/notification-center.md -->

## Overview

The **notification centre** adds a bell to the top bar collecting the events that concern *you*: it turns fire-and-forget toasts into a persistent, per-user **inbox** that survives page reloads and works whether or not the real-time WebSocket server is running.

> 🔔 This feature must be enabled by an administrator (**Settings → Options → In-app notification centre**). It is disabled by default.

---

## What lands in the inbox

| Event | Meaning |
|-------|---------|
| Security scan finished | The [Security Posture Dashboard](breach-detection.md) deep scan completed, with the number of items needing attention. Clicking opens the dashboard. |
| Background task completed / failed | A background task you triggered (e.g. item encryption keys generation) finished. |
| Your encryption keys are ready | Account provisioning completed — you can start using TeamPass. |
| Folder access rights updated | An administrator or manager changed a role/folder grant that affects you. |
| Local TeamPass password expiry | A local account password reaches 14, 7, 3, or 1 day before expiry, or is expired. Administrators are included; LDAP and OAuth2 accounts are excluded. Clicking opens the profile page. |
| Knowledge-base article published | A new article was created. It is sent to active non-admin users except the author. Clicking opens the article directly. |

Transient events are deliberately **not** stored: session-expiry (you are being logged out) and task progress heartbeats.

Each user keeps their **latest 50** notifications; older ones are pruned automatically.

---

## How it works

- Server-side, every user-targeted event emitted through the WebSocket layer is checked against a **whitelist**; matching events are persisted in the `teampass_user_notifications` companion table **before** the WebSocket gate — so the inbox fills up even on installations without the WebSocket daemon.
- New business events use `tpNotifyUser()`, a channel-neutral dispatch point. The first supported durable channel is in-app; this boundary is intended to support per-user email/in-app preferences later without changing event producers.
- Stored payloads are **sanitized per event type**: only the fields the inbox needs are kept. Internal routing metadata is never stored, and strings are length-bounded.
- Optional `dedupe_key` values make retries idempotent. Password warnings are unique per password cycle and milestone; knowledge-base notifications are unique per article and recipient.
- The bell shows an **unread badge**; opening it lists the latest notifications with their timestamps. *Mark all as read* clears the badge; clicking a notification marks it read individually.
- When the [WebSocket server](../install/websocket.md) is enabled, the same events refresh the inbox **live** — no reload needed.

Local-password warnings are populated by a daily background sweep at 03:15 in the TeamPass timezone. A once-per-day check during an authenticated page load is a fallback for installations whose background task handler is unavailable. Both paths use the same dedupe key.

---

## Permissions & settings

| Setting | Default | Purpose |
|---------|---------|---------|
| `notification_center_enabled` | `0` | Master switch for the bell + inbox |

Notifications are strictly **per-user**: the AJAX handlers only ever read or update rows belonging to the session user. Password values and item data are never stored. A knowledge-base publication stores only its numeric article id and its already-public label so the inbox can render a direct link.
