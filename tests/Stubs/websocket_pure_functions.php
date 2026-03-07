<?php

declare(strict_types=1);

/**
 * Pure functions extracted verbatim (or as minimal adaptations) from production
 * WebSocket and item-locking code, for unit testing without DB / session / globals.
 *
 * Sources:
 *   - sources/main.functions.php  (emitEditionLockEvent, emitWebSocketEvent)
 *   - sources/items.queries.php   (isItemLocked logic, move_item payload)
 *
 * MAINTENANCE: keep in sync with their originals. If the production logic
 * changes, update this file and its corresponding tests accordingly.
 */

// ---------------------------------------------------------------------------
// Edition-lock helpers (from emitEditionLockEvent / emitWebSocketEvent)
// ---------------------------------------------------------------------------

/**
 * Build the payload array for an item_edition_started / item_edition_stopped event.
 *
 * Mirrors the $payload construction inside emitEditionLockEvent().
 *
 * @return array{item_id:int,folder_id:int,user_login:string,user_id:int}
 */
function buildEditionLockEventPayload(
    int    $itemId,
    int    $folderId,
    string $userLogin,
    int    $userId
): array {
    return [
        'item_id'    => $itemId,
        'folder_id'  => $folderId,
        'user_login' => $userLogin,
        'user_id'    => $userId,
    ];
}

/**
 * Return the user ID that should be excluded from receiving the event, or null.
 *
 * For 'started': exclude the editor (they know they are editing).
 * For 'stopped': no exclusion (everyone should see the release).
 *
 * Mirrors the $excludeUserId logic inside emitEditionLockEvent().
 */
function computeEditionLockExcludeUserId(string $action, int $userId): ?int
{
    return ($action === 'started') ? $userId : null;
}

/**
 * Embed exclude_user_id into the payload if not null, exactly as
 * emitWebSocketEvent() does before persisting to the DB.
 *
 * @param array    $payload       Original payload array.
 * @param int|null $excludeUserId User ID to embed, or null to leave unchanged.
 * @return array                  Payload, possibly with 'exclude_user_id' added.
 */
function embedExcludeUserIdInPayload(array $payload, ?int $excludeUserId): array
{
    if ($excludeUserId !== null) {
        $payload['exclude_user_id'] = $excludeUserId;
    }
    return $payload;
}

// ---------------------------------------------------------------------------
// Item-move payload (from move_item / mass_move_items cases)
// ---------------------------------------------------------------------------

/**
 * Build the payload array for an item_moved WebSocket event.
 *
 * Mirrors the $movePayload construction in both move_item and
 * mass_move_items cases of items.queries.php.
 *
 * @return array{item_id:int,from_folder_id:int,to_folder_id:int,label:string,moved_by:string}
 */
function buildItemMovedEventPayload(
    int    $itemId,
    int    $fromFolderId,
    int    $toFolderId,
    string $label,
    string $userLogin
): array {
    return [
        'item_id'        => $itemId,
        'from_folder_id' => $fromFolderId,
        'to_folder_id'   => $toFolderId,
        'label'          => $label,
        'moved_by'       => $userLogin,
    ];
}

// ---------------------------------------------------------------------------
// Edition-lock status determination (pure core of isItemLocked())
// ---------------------------------------------------------------------------

/**
 * Determine what action should be taken given the current edition locks.
 *
 * Returns one of:
 *   'no_lock'           — no existing lock; caller should create one if editing
 *   'own_lock'          — current user already holds the lock; refresh timestamp
 *   'locked'            — another user holds a valid (non-expired) lock
 *   'expired'           — lock exists but has exceeded the heartbeat timeout;
 *                         caller may take ownership
 *
 * This is the pure logical core extracted from isItemLocked() in
 * sources/items.queries.php, with all DB calls removed.
 *
 * @param array  $editionLocks    Rows from teampass_items_edition, newest first.
 *                                Each row must contain 'user_id' and 'timestamp'.
 * @param int    $currentUserId   ID of the requesting user.
 * @param int    $now             Current Unix timestamp.
 * @param int    $heartbeatTimeout Seconds before a lock is considered stale.
 */
function determineEditionLockAction(
    array $editionLocks,
    int   $currentUserId,
    int   $now,
    int   $heartbeatTimeout
): string {
    if (count($editionLocks) === 0) {
        return 'no_lock';
    }

    $lastLock = $editionLocks[0];

    if (intval($lastLock['user_id']) === $currentUserId) {
        return 'own_lock';
    }

    $elapsed = abs($now - intval($lastLock['timestamp']));

    if ($elapsed > $heartbeatTimeout) {
        return 'expired';
    }

    return 'locked';
}
