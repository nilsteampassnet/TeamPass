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

Transient events are deliberately **not** stored: session-expiry (you are being logged out) and task progress heartbeats.

Each user keeps their **latest 50** notifications; older ones are pruned automatically.

---

## How it works

- Server-side, every user-targeted event emitted through the WebSocket layer is checked against a **whitelist**; matching events are persisted in the `teampass_user_notifications` companion table **before** the WebSocket gate — so the inbox fills up even on installations without the WebSocket daemon.
- Stored payloads are **sanitized per event type**: only the fields the inbox needs (counts, task type, status) are kept. Internal routing metadata and folder id lists are never stored.
- The bell shows an **unread badge**; opening it lists the latest notifications with their timestamps. *Mark all as read* clears the badge; clicking a notification marks it read individually.
- When the [WebSocket server](../install/websocket.md) is enabled, the same events refresh the inbox **live** — no reload needed.

---

## Permissions & settings

| Setting | Default | Purpose |
|---------|---------|---------|
| `notification_center_enabled` | `0` | Master switch for the bell + inbox |

Notifications are strictly **per-user**: the AJAX handlers only ever read or update rows belonging to the session user. No password value or item name is stored — payloads are counts and statuses only.
