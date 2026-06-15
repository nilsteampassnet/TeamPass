<?php
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
 * ---
 * Pure decision logic for personal-item sharekey distribution and remediation (SEC-8).
 *
 * These functions have NO DB / session / filesystem dependency, so they can be unit-tested in
 * isolation. They are included by both:
 *   - app/scripts/remediate_personal_sharekeys.php   (production remediation tool)
 *   - tests/Unit/PersonalSharekeysLogicTest.php       (unit tests)
 *
 * The storeUsersShareKey() owner-only branch in app/sources/main.functions.php implements the
 * same contract as isPersonalSharekeyDistribution(); the condition is kept inline there because
 * that file cannot be loaded without a live DB connection.
 *
 * @file      personal_sharekeys_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

declare(strict_types=1);

if (function_exists('isPersonalSharekeyDistribution') === false) {
    /**
     * SEC-8 contract: a sharekey distribution is owner-only when the target folder is personal.
     *
     * The legacy $onlyForUser flag is intentionally NOT a trigger: callers historically passed it
     * as true on public paths too, so only the personal-folder flag is authoritative.
     *
     * @param int $postFolderIsPersonal 1 = personal object (owner-only), 0 = public (all users).
     */
    function isPersonalSharekeyDistribution(int $postFolderIsPersonal): bool
    {
        return $postFolderIsPersonal === 1;
    }

    /**
     * Resolve the owner id from the absolute root node of a personal item's folder tree.
     *
     * Intermediate sub-folders may carry an inconsistent personal_folder flag (e.g. after a copy),
     * so the decision relies on the absolute tree root: it must be flagged personal and carry a
     * numeric (user id) title that is not a system account. Returns null when the owner cannot be
     * resolved safely.
     *
     * @param array{personal_folder?:int|string,title?:string}|null $rootNode      Absolute tree root.
     * @param int[]                                                  $systemUserIds System account ids.
     *
     * @return int|null Owner user id, or null when unresolved.
     */
    function personalRootOwnerId(?array $rootNode, array $systemUserIds): ?int
    {
        if ($rootNode === null) {
            return null;
        }
        if ((int) ($rootNode['personal_folder'] ?? 0) !== 1) {
            return null;
        }
        $title = (string) ($rootNode['title'] ?? '');
        if ($title === '' || ctype_digit($title) === false) {
            return null;
        }
        $ownerId = (int) $title;
        if (in_array($ownerId, array_map('intval', $systemUserIds), true) === true) {
            return null;
        }
        return $ownerId;
    }

    /**
     * Conflict decision: a resolved folder owner that disagrees with the at_creation user id.
     * When the at_creation user is unknown (null/empty), the folder owner stands (no conflict).
     *
     * @param int                  $folderOwner      Owner resolved from the folder hierarchy.
     * @param int|string|null      $atCreationUserId User id from the at_creation log, if any.
     */
    function personalOwnerConflictsWithCreator(int $folderOwner, int|string|null $atCreationUserId): bool
    {
        if ($atCreationUserId === null || $atCreationUserId === '') {
            return false;
        }
        return (int) $atCreationUserId !== $folderOwner;
    }

    /**
     * The set of user ids that must KEEP a sharekey on a personal item: the owner plus the
     * system/recovery accounts (TP_USER_ID, API_USER_ID, OTV_USER_ID, SSH_USER_ID).
     *
     * @param int   $ownerId       Item owner id.
     * @param int[] $systemUserIds System account ids.
     *
     * @return int[]
     */
    function personalSharekeyKeepList(int $ownerId, array $systemUserIds): array
    {
        return array_values(array_unique(array_merge([$ownerId], array_map('intval', $systemUserIds))));
    }

    /**
     * Foreign sharekey holders = existing holders that are not in the keep list.
     * This is the spec implemented in SQL by the "user_id NOT IN (...)" deletes of the
     * remediation tool and of EnsurePersonalItemHasOnlyKeysForOwner().
     *
     * @param array<int|string> $existingUserIds Current sharekey holders for the object.
     * @param int[]              $keepUserIds     Users allowed to keep their sharekey.
     *
     * @return int[] User ids whose sharekey is foreign (to be removed).
     */
    function foreignSharekeyUserIds(array $existingUserIds, array $keepUserIds): array
    {
        $keep = array_map('intval', $keepUserIds);
        $foreign = [];
        foreach ($existingUserIds as $uid) {
            if (in_array((int) $uid, $keep, true) === false) {
                $foreign[] = (int) $uid;
            }
        }
        return array_values(array_unique($foreign));
    }
}
