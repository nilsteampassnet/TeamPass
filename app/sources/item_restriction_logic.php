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
 * Canonical decision for the item-level restriction (items.restricted_to +
 * restriction_to_roles).
 *
 * An item restriction only ever NARROWS folder access: it never grants access to a folder the
 * user cannot read, and an item carrying no restriction at all stays open to every folder member.
 * Callers therefore combine this predicate with their own folder authorization, never replace it.
 *
 * This module is DB-free and session-free so the whole truth table can be unit-tested in
 * isolation, and so the very same decision serves the web request, the CLI worker and the REST
 * API. It exists because the two enforcement paths had drifted: the web denied a restricted read
 * while the API served the plaintext password (GHSA-gxc6-rgv6-wx99).
 *
 * It also normalizes the restriction an API client requests on item creation
 * (itemRestrictionNormalizeRequestedIds / itemRestrictionUsersWithAuthor).
 *
 * Included by:
 *   - app/sources/main.functions.php            (web + API adapters)
 *   - app/sources/security_posture_logic.php    (posture wrappers, kept for their callers)
 *   - tests/Unit/ItemRestrictionLogicTest.php   (unit tests)
 *
 * The manager_edit derogation found in items.queries.php (show_details_item / showDetailsStep2)
 * is deliberately NOT reproduced here. That derogation only ever widened the item CARD; the
 * password itself is released by get_item_password, which goes through getCurrentAccessRights()
 * -> getItemRestrictedUsersList() and grants no such override. Every consumer of this module
 * hands out secret material, so it mirrors the strict variant.
 *
 * @file      item_restriction_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

declare(strict_types=1);

if (function_exists('itemRestrictionParseIdList') === false) {
    /**
     * Split a semicolon-separated id list into positive integers.
     *
     * @param string|null $list Raw column value (e.g. items.restricted_to).
     *
     * @return int[] Ids found in the list, empty when there is none.
     */
    function itemRestrictionParseIdList(?string $list): array
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
     * @param string|null $restrictedTo          Semicolon-separated user ids from items.restricted_to.
     * @param int[]       $itemRestrictedRoleIds Role ids from restriction_to_roles for this item.
     * @param int         $userId                User whose access is evaluated.
     * @param int[]       $userRoleIds           Role ids held by that user (manual AND AD/LDAP).
     *
     * @return bool True when the item's restrictions let this user through.
     */
    function itemRestrictionAllows(
        ?string $restrictedTo,
        array $itemRestrictedRoleIds,
        int $userId,
        array $userRoleIds
    ): bool {
        $restrictedUsers = itemRestrictionParseIdList($restrictedTo);
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
     * Build the SQL form of the same decision, for list queries.
     *
     * Shape: the item's own restriction is checked against the small, item_id-indexed
     * restriction_to_roles table; the user's role set is resolved once in PHP and embedded.
     * The caller must already have the items table joined under $itemAlias, and must AND this
     * predicate with its own folder authorization.
     *
     * Fails closed — '(1 = 0)' — on any input that cannot be safely interpolated.
     *
     * @param int      $userId           User whose access is evaluated.
     * @param int[]    $userRoleIds      Role ids held by that user.
     * @param string   $itemAlias        SQL alias of the items table in the caller's query.
     * @param string   $restrictionTable Fully-qualified restriction_to_roles table name.
     *
     * @return string Parenthesized SQL predicate without a leading AND.
     */
    function itemRestrictionSqlPredicate(
        int $userId,
        array $userRoleIds,
        string $itemAlias,
        string $restrictionTable
    ): string {
        if ($userId <= 0
            || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $itemAlias) !== 1
            || preg_match('/^[A-Za-z0-9_]+$/', $restrictionTable) !== 1
        ) {
            return '(1 = 0)';
        }

        $roleIds = [];
        foreach ($userRoleIds as $roleId) {
            $roleId = (int) $roleId;
            if ($roleId > 0) {
                $roleIds[$roleId] = $roleId;
            }
        }

        $roleRestrictionClause = '';
        if (count($roleIds) > 0) {
            $roleRestrictionClause = ' OR EXISTS (SELECT 1 FROM ' . $restrictionTable
                . ' AS tp_held_restricted_role'
                . ' WHERE tp_held_restricted_role.item_id = ' . $itemAlias . '.id'
                . ' AND tp_held_restricted_role.role_id IN (' . implode(',', $roleIds) . '))';
        }

        // Everything interpolated below is an int-cast id or a validated identifier. The LIKE
        // pattern carries no MeekroDB placeholder ('%;' and ';%' are not in its parameter map).
        return '('
            . '(COALESCE(' . $itemAlias . '.restricted_to, \'\') = \'\''
            . ' AND NOT EXISTS (SELECT 1 FROM ' . $restrictionTable . ' AS tp_any_restricted_role'
            . ' WHERE tp_any_restricted_role.item_id = ' . $itemAlias . '.id))'
            . ' OR CONCAT(\';\', COALESCE(' . $itemAlias . '.restricted_to, \'\'), \';\')'
            . ' LIKE \'%;' . $userId . ';%\''
            . $roleRestrictionClause
            . ')';
    }

    /**
     * Normalize a restriction id list received from an API client (restricted_to or
     * restricted_to_roles on item creation).
     *
     * Strict on purpose: a value that is not a list of positive integers is refused rather
     * than silently dropped, because a dropped restriction leaves the item open to every
     * folder member — the opposite of what the client asked for.
     *
     * @param mixed  $raw       Decoded request value; null means "no restriction".
     * @param string $fieldName Request field name, used in the error message.
     * @param int    $maxCount  Largest accepted list (a wider audience belongs in a role).
     *
     * @return int[] Unique ids in request order, empty when no restriction is requested.
     *
     * @throws InvalidArgumentException When the value is not a list of positive integers.
     */
    function itemRestrictionNormalizeRequestedIds($raw, string $fieldName, int $maxCount = 100): array
    {
        if ($raw === null) {
            return [];
        }
        if (is_array($raw) === false) {
            throw new InvalidArgumentException($fieldName . ' must be an array of positive integer IDs.');
        }

        $ids = [];
        foreach ($raw as $value) {
            if (is_string($value) === true && ctype_digit(trim($value)) === true) {
                $value = (int) trim($value);
            }
            if (is_int($value) === false || $value <= 0) {
                throw new InvalidArgumentException($fieldName . ' must be an array of positive integer IDs.');
            }
            $ids[$value] = $value;
        }

        if (count($ids) > $maxCount) {
            throw new InvalidArgumentException($fieldName . ' accepts at most ' . $maxCount . ' IDs.');
        }

        return array_values($ids);
    }

    /**
     * User list stored on an item created with a restriction: the author is always kept in it.
     *
     * Same rule as the web form (items.js.php adds the author to any user restriction), extended
     * to a role-only restriction: without it, the account that created the item would be refused
     * its own item at the next read, update or delete.
     *
     * @param int[] $userIds  Restricted user ids, already normalized.
     * @param int[] $roleIds  Restricted role ids, already normalized.
     * @param int   $authorId User creating the item.
     *
     * @return int[] User ids to store in items.restricted_to, empty when nothing is restricted.
     */
    function itemRestrictionUsersWithAuthor(array $userIds, array $roleIds, int $authorId): array
    {
        if (count($userIds) === 0 && count($roleIds) === 0) {
            return [];
        }
        if ($authorId > 0 && in_array($authorId, $userIds, true) === false) {
            $userIds[] = $authorId;
        }

        return array_values($userIds);
    }
}
