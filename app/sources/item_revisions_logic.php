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
