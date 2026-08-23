<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 *
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * Decision logic of the item revision journal, kept free of any database or session
 * access so it can be unit-tested on its own — same pattern as security_posture_logic.php.
 *
 * It is included by both:
 *   - app/sources/main.functions.php              (production adapters: bumpItemRevision())
 *   - tests/Unit/ItemRevisionsLogicTest.php       (unit tests)
 *
 * The adapters only talk to the database; every decision about *whether* a change is a
 * revision, and *which* journal action it maps to, lives here.
 *
 * @file      item_revisions_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Audit actions that describe a change to the item content a client caches.
 *
 * Read actions (at_shown, at_password_shown, at_password_copied, at_export, at_access) and
 * annotations (at_manual) are deliberately absent: bumping on those would make every offline
 * client re-download an item nobody modified.
 */
const ITEM_REVISION_BUMP_LOG_ACTIONS = [
    'at_creation',
    'at_modification',
    'at_delete',
    'at_restored',
    'at_copy',
    'at_import',
];

/**
 * Journal actions that close the life of an item for a synchronizing client.
 *
 * They must always be recorded, even when a revision was already allocated for that item in
 * the same request, otherwise the client would never learn the item is gone.
 */
const ITEM_REVISION_TERMINAL_ACTIONS = [
    'deleted',
    'purged',
];

/**
 * Prefix of the reason qualifying a move inside an at_modification log entry.
 */
const ITEM_REVISION_MOVE_REASON_PREFIX = 'at_moved';

/**
 * Tell whether an audit action must allocate a new item revision.
 *
 * @param string $action Audit action, as passed to logItems()
 *
 * @return bool True when the action changes the item content
 */
function itemRevisionShouldBump(string $action): bool
{
    return in_array($action, ITEM_REVISION_BUMP_LOG_ACTIONS, true);
}

/**
 * Translate an audit action into the journal action stored alongside the revision.
 *
 * A copy and an import both materialize a new item, so they are journalled as a creation.
 * A modification is a move when its reason says so — the distinction matters because a move
 * is the only change that can push an item out of a client's visible folders.
 *
 * @param string      $logAction Audit action, as passed to logItems()
 * @param string|null $raison    Audit reason, which may carry the ' | tp_src=api' marker
 *
 * @return string One of created|updated|deleted|restored|moved|purged
 */
function itemRevisionJournalAction(string $logAction, ?string $raison = null): string
{
    switch ($logAction) {
        case 'at_creation':
        case 'at_copy':
        case 'at_import':
            return 'created';
        case 'at_delete':
            return 'deleted';
        case 'at_restored':
            return 'restored';
        case 'at_modification':
            return itemRevisionReasonIsMove($raison) === true ? 'moved' : 'updated';
        default:
            return 'updated';
    }
}

/**
 * Tell whether an audit reason describes a folder move.
 *
 * @param string|null $raison Audit reason
 *
 * @return bool True when the reason is a move
 */
function itemRevisionReasonIsMove(?string $raison): bool
{
    if ($raison === null) {
        return false;
    }

    return str_starts_with(ltrim($raison), ITEM_REVISION_MOVE_REASON_PREFIX);
}

/**
 * Tell whether a journal action must be recorded even when the item already has a
 * revision allocated in the current request.
 *
 * @param string $journalAction Journal action
 *
 * @return bool True for a terminal action
 */
function itemRevisionIsTerminalAction(string $journalAction): bool
{
    return in_array($journalAction, ITEM_REVISION_TERMINAL_ACTIONS, true);
}

/**
 * Tell whether a revision already allocated in the current request must be rewritten
 * because a later call carries information the stored row does not have.
 *
 * Two cases: the item is being deleted after having been modified, and a move reporting the
 * folder the item came from — that source folder is what lets the delta feed tell a client an
 * item left its visible scope.
 *
 * @param string   $journalAction     Journal action of the later call
 * @param int|null $previousFolderId  Source folder of a move, null when not a move
 *
 * @return bool True when the journal row must be updated in place
 */
function itemRevisionShouldUpgradeEntry(string $journalAction, ?int $previousFolderId): bool
{
    return itemRevisionIsTerminalAction($journalAction) === true
        || ($previousFolderId !== null && $previousFolderId > 0);
}

/**
 * Default and maximum number of journal entries scanned in one delta call.
 */
const ITEM_REVISION_DEFAULT_LIMIT = 200;
const ITEM_REVISION_MAX_LIMIT = 1000;

/**
 * Clamp the number of journal entries a client may scan in one call.
 *
 * @param int|null $limit Requested limit, null when not supplied
 *
 * @return int Effective limit
 */
function itemRevisionNormalizeLimit(?int $limit): int
{
    if ($limit === null || $limit <= 0) {
        return ITEM_REVISION_DEFAULT_LIMIT;
    }

    return min($limit, ITEM_REVISION_MAX_LIMIT);
}

/**
 * Tell whether a client must rebuild its whole cache instead of applying a delta.
 *
 * Three situations lead there, and they collapse into one rule:
 *   - a first synchronization (cursor 0): items untouched since tracking was installed still
 *     sit at revision 0 and have no journal entry, so a delta would silently miss them;
 *   - a cursor older than the retained window: the entries proving what changed are pruned;
 *   - an empty journal asked from cursor 0, same reason as the first case.
 *
 * @param int      $since              Client cursor
 * @param int|null $minRetainedRevision Lowest revision still in the journal, null when empty
 *
 * @return bool True when a full resynchronization is required
 */
function itemRevisionNeedsFullSync(int $since, ?int $minRetainedRevision): bool
{
    if ($since <= 0) {
        return true;
    }

    if ($minRetainedRevision === null) {
        // Nothing was ever journalled: there is simply nothing to report.
        return false;
    }

    // The client is served correctly only if every revision above its cursor is still stored.
    return $since < $minRetainedRevision - 1;
}

/**
 * Keep, for each item, only the last journal entry of the scanned window.
 *
 * An item modified three times since the client's cursor is one thing to download, not three.
 *
 * @param array<int, array<string, mixed>> $rows Journal rows, each with item_id and revision
 *
 * @return array<int, array<string, mixed>> Winning row per item id, keyed by item id
 */
function itemRevisionDedupeScan(array $rows): array
{
    $winners = [];

    foreach ($rows as $row) {
        $itemId = (int) ($row['item_id'] ?? 0);
        if ($itemId <= 0) {
            continue;
        }

        $revision = (int) ($row['revision'] ?? 0);
        if (isset($winners[$itemId]) === false || $revision > (int) $winners[$itemId]['revision']) {
            $winners[$itemId] = $row;
        }
    }

    return $winners;
}

/**
 * Decide what a client must do with an item that changed.
 *
 * @param int                 $itemId          Item id
 * @param array<int, bool>    $visibleItemIds  Items currently readable by the caller, as a set
 * @param bool                $stillExists     False once the item row is gone
 * @param bool                $isDeleted       True when soft deleted
 *
 * @return array{classification: string, reason: string} changed, or removed with its reason
 */
function itemRevisionClassifyScanRow(
    int $itemId,
    array $visibleItemIds,
    bool $stillExists,
    bool $isDeleted
): array {
    if ($stillExists === false) {
        return ['classification' => 'removed', 'reason' => 'purged'];
    }

    if ($isDeleted === true) {
        return ['classification' => 'removed', 'reason' => 'deleted'];
    }

    if (isset($visibleItemIds[$itemId]) === false) {
        // The item still exists but the caller can no longer read it — typically moved into
        // a folder they have no access to. Their cached copy has to go.
        return ['classification' => 'removed', 'reason' => 'out_of_scope'];
    }

    return ['classification' => 'changed', 'reason' => ''];
}

/**
 * Compute the cursor a client must store after applying a delta.
 *
 * Two constraints: never go backwards, and never step over a change that could not be
 * delivered. The second one matters right after an item is created, while the background
 * task is still distributing the encryption keys: the item is visible but not yet readable,
 * and stepping over it would hide it from that client for good.
 *
 * @param int             $since             Client cursor
 * @param array<int, int> $scannedRevisions  Every revision read in this window
 * @param array<int, int> $undeliverable     Revisions of changes that could not be materialized
 *
 * @return int Cursor to store
 */
function itemRevisionResolveCursor(int $since, array $scannedRevisions, array $undeliverable = []): int
{
    $cursor = $since;

    if ($scannedRevisions !== []) {
        $cursor = max($since, max($scannedRevisions));
    }

    if ($undeliverable !== []) {
        // Stop just before the first change we could not hand over, so it is offered again.
        $cursor = min($cursor, min($undeliverable) - 1);
    }

    return max($since, $cursor);
}

/**
 * Default offline synchronization window, in days.
 */
const OFFLINE_SYNC_DEFAULT_WINDOW_DAYS = 90;

/**
 * Resolve the configured offline synchronization window.
 *
 * This is NOT a data retention: no item, password or history depends on it. It bounds how
 * long a device may stay offline and still catch up incrementally — past it, the device is
 * told to rebuild its cache, which costs bandwidth and nothing else.
 *
 * Zero, or anything negative, means the journal is never trimmed. A missing or unreadable
 * setting falls back to the default rather than to "never", because the journal grows with
 * every change and an unbounded one is nobody's intent.
 *
 * @param mixed $rawSetting Value read from the settings
 *
 * @return int Window in days, 0 to never trim
 */
function offlineSyncResolveWindowDays($rawSetting): int
{
    if ($rawSetting === null || $rawSetting === '' || is_numeric($rawSetting) === false) {
        return OFFLINE_SYNC_DEFAULT_WINDOW_DAYS;
    }

    $days = (int) $rawSetting;

    return $days > 0 ? $days : 0;
}

/**
 * Oldest change entry the journal keeps to serve incremental synchronization.
 *
 * @param int $windowDays Window in days, 0 to never trim
 * @param int $now        Current timestamp
 *
 * @return int|null Cut-off timestamp, null when the journal is never trimmed
 */
function offlineSyncPruneCutoff(int $windowDays, int $now): ?int
{
    if ($windowDays <= 0) {
        return null;
    }

    return $now - ($windowDays * 86400);
}
