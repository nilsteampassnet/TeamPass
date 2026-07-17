<!-- docs/features/collaboration.md -->

## Overview

When the [WebSocket server](../install/websocket.md) is enabled, TeamPass becomes a **real-time collaborative** application: everyone looking at the same folder sees changes, locks and presence as they happen — no manual refresh.

> 🔔 Requires the WebSocket server to be installed and enabled (`websocket_enabled`). Everything degrades gracefully when it is not running: TeamPass keeps working, simply without the live layer.

---

## What you see

### Live item updates

When someone creates, modifies, moves, copies or deletes an item in the folder you are viewing, the list refreshes automatically and a toast tells you **what** changed and **who** did it. If the item you have open is modified by someone else, its detail panel reloads itself.

### Edition locks — *who is editing*

When a user opens an item for edition, everyone else in the folder sees a **lock badge** (🔒 with the editor's name) on the item row, and in the item detail panel. If you try to edit a locked item, TeamPass tells you who holds the lock — and notifies you the moment the item **becomes available** again.

Locks are released automatically when the editor saves, cancels, or disconnects.

### Consultation presence — *who is viewing*

An **eye badge** (👁) on the item row and detail panel shows who currently has the item open in read-only consultation. Multiple viewers are collapsed (`alice +2`); hovering lists the names. The same presence exists on knowledge base articles.

### State is complete, not event-driven only

Presence and locks are shown **even for sessions that started before you arrived**: subscribing to a folder returns the currently locked and viewed items, and the badges are re-applied after every list redraw — the indicators never silently disappear because the list refreshed.

### Connection indicator

A small dot at the bottom-right of the page shows the real-time layer status: green (connected) or red (reconnecting). Reconnection is automatic with exponential backoff.

---

## Privacy & permissions

- Presence and lock events are delivered **only to users subscribed to the folder**, and folder subscription is validated server-side against the user's folder ACL.
- Presence payloads carry the user id, login and display name — never any item content.
- Your own presence is not shown back to you (no "viewed by you" noise).

---

## Related

- [WebSocket server installation](../install/websocket.md) — setup, systemd unit, reverse proxy
- [Notification centre](notification-center.md) — persistent inbox for the events that target you personally
