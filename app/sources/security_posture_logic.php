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
 * Pure decision logic for the Security Posture authorization boundary and password health.
 *
 * These functions have NO DB / session / filesystem dependency, so the whole truth table can be
 * unit-tested in isolation. They are included by both:
 *   - app/sources/main.functions.php                  (production adapters, see below)
 *   - tests/Unit/SecurityPostureLogicTest.php         (unit tests)
 *
 * The adapters in main.functions.php only read the database and hand the resulting sets to these
 * functions; every access *decision* lives here.
 *
 * @file      security_posture_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

declare(strict_types=1);

if (function_exists('securityPostureResolveAuthorizedFolders') === false) {
    /**
     * Resolve the folders a user may read, from the raw grant/denial sets.
     *
     * Mirrors identUserGetPFList() so Security Posture never shows more (nor less) than the item
     * browser:
     *   - a shared folder is readable when granted directly (users_groups) or by a readable role;
     *   - an explicit denial (users_groups_forbidden) beats every shared grant;
     *   - no personal folder is ever reachable through a shared grant, whatever the grant says;
     *   - the user's own personal tree is always readable and is NOT subject to the denial list,
     *     exactly like identUserGetPFList() which only diffs the denials against $allowedFolders.
     *
     * Administrators are deliberately absent from this contract: identAdmin() grants them folder
     * *browsing*, but show_details_item returns show_details = 0 for any non-personal item
     * (items.queries.php), so an administrator never reads item content. Handing them the posture
     * list would disclose labels and folder paths they cannot open — the very leak this boundary
     * exists to close. An administrator therefore resolves to their own personal tree only, and
     * their organisation-wide view stays the admin-only compliance report, which is global by
     * design and not scoped per user.
     *
     * @param int[] $directGrantFolders Folder ids granted through users_groups.
     * @param int[] $roleGrantFolders   Folder ids granted by a readable role (W/ND/NE/NDNE/R).
     * @param int[] $deniedFolders      Folder ids explicitly denied through users_groups_forbidden.
     * @param int[] $ownPersonalFolders The user's own personal tree (root + descendants).
     * @param int[] $allPersonalFolders Every personal folder of the instance, all owners included.
     *
     * @return int[] Readable folder ids, unique and sorted ascending.
     */
    function securityPostureResolveAuthorizedFolders(
        array $directGrantFolders,
        array $roleGrantFolders,
        array $deniedFolders,
        array $ownPersonalFolders,
        array $allPersonalFolders
    ): array {
        $toIntSet = static function (array $values): array {
            return array_values(array_unique(array_map('intval', $values)));
        };

        $denied = $toIntSet($deniedFolders);
        $allPersonal = $toIntSet($allPersonalFolders);
        $ownPersonal = $toIntSet($ownPersonalFolders);

        // Shared grants: direct or role based, minus denials, minus every personal folder.
        $shared = array_merge($toIntSet($directGrantFolders), $toIntSet($roleGrantFolders));
        $shared = array_diff($shared, $denied, $allPersonal);

        $authorized = array_values(array_unique(array_merge($shared, $ownPersonal)));
        sort($authorized, SORT_NUMERIC);

        return $authorized;
    }

    /**
     * Split a semicolon-separated id list into positive integers.
     *
     * @param string|null $list Raw column value (e.g. items.restricted_to).
     *
     * @return int[] Ids found in the list, empty when there is none.
     */
    function securityPostureParseIdList(?string $list): array
    {
        if ($list === null || trim($list) === '') {
            return [];
        }

        $ids = [];
        foreach (explode(';', $list) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk !== '' && ctype_digit($chunk) === true && (int) $chunk > 0) {
                $ids[] = (int) $chunk;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Decide whether a user satisfies an item's own restrictions.
     *
     * Item restrictions only ever narrow folder access; they never grant access to a folder the
     * user cannot read. An item carrying no restriction at all is open to every folder member.
     *
     * The manager_edit derogation (items.queries.php) is intentionally NOT reproduced here:
     * Security Posture reports the credentials a user holds in their own right, not the ones they
     * can reach through a management override.
     *
     * @param string|null $restrictedTo          Semicolon-separated user ids from items.restricted_to.
     * @param int[]       $itemRestrictedRoleIds Role ids from restriction_to_roles for this item.
     * @param int         $userId                User whose access is evaluated.
     * @param int[]       $userRoleIds           Role ids held by that user.
     *
     * @return bool True when the item's restrictions let this user through.
     */
    function securityPostureItemRestrictionAllows(
        ?string $restrictedTo,
        array $itemRestrictedRoleIds,
        int $userId,
        array $userRoleIds
    ): bool {
        $restrictedUsers = securityPostureParseIdList($restrictedTo);
        $restrictedRoles = array_values(array_unique(array_map('intval', $itemRestrictedRoleIds)));

        // No restriction of any kind -> open to every folder member.
        if (count($restrictedUsers) === 0 && count($restrictedRoles) === 0) {
            return true;
        }

        if (in_array($userId, $restrictedUsers, true) === true) {
            return true;
        }

        $heldRoles = array_map('intval', $userRoleIds);

        return count(array_intersect($restrictedRoles, $heldRoles)) > 0;
    }

    /**
     * Classify password health from the metadata recorded with an item.
     *
     * Four mutually exclusive states:
     *   - empty      : the item carries no password at all (a known state, never a warning);
     *   - unassessed : metadata is missing, non numeric or legacy (complexity_level = -1, or a
     *                  ciphertext stored with pw_len = 0). Reported honestly instead of being
     *                  guessed as weak, and never counted against the security score;
     *   - weak       : assessed metadata below the complexity threshold or shorter than the
     *                  configured minimum length;
     *   - healthy    : assessed and above both thresholds.
     *
     * @param int|string|null $complexityLevel         Stored zxcvbn-derived complexity level.
     * @param int|null        $passwordLength          Stored (or live) password length.
     * @param bool            $hasStoredPassword       Whether the item holds password ciphertext.
     * @param int             $weakComplexityThreshold Complexity below which a password is weak.
     * @param int             $minPasswordLength       Length below which a password is weak.
     *
     * @return string One of: empty, unassessed, weak, healthy.
     */
    function securityPasswordHealthClassify(
        int|string|null $complexityLevel,
        ?int $passwordLength,
        bool $hasStoredPassword,
        int $weakComplexityThreshold,
        int $minPasswordLength
    ): string {
        // A genuinely empty password is a known state: no metadata is required to tell that it is
        // neither weak nor unassessed. Checked first so missing metadata cannot mask it.
        if ($hasStoredPassword === false && ($passwordLength === null || $passwordLength === 0)) {
            return 'empty';
        }

        if (
            $complexityLevel === null
            || $complexityLevel === ''
            || is_numeric($complexityLevel) === false
            || (int) $complexityLevel < 0
            || $passwordLength === null
            || $passwordLength <= 0
        ) {
            return 'unassessed';
        }

        if (
            (int) $complexityLevel < $weakComplexityThreshold
            || $passwordLength < $minPasswordLength
        ) {
            return 'weak';
        }

        return 'healthy';
    }
}
