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
 * DB-free decisions of the OAuth2 self-registration: which groups the setting
 * oauth_self_register_groups lists, whether a user signing in for the first time may get an
 * account, and which of their groups are then matched against the TeamPass role titles.
 *
 * @file      oauth2_self_register_logic.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Entry of the setting that lets every user of the identity provider self-register.
 */
const OAUTH2_SELF_REGISTER_ANYONE = '*';

/**
 * Split the setting oauth_self_register_groups into the list of allowed groups.
 *
 * The setting is saved through FILTER_SANITIZE_FULL_SPECIAL_CHARS, so "R&D" or "Équipe" are
 * stored as HTML entities: they are decoded first, otherwise such names could never match the
 * raw names the provider sends. Entries are trimmed, empty ones dropped, duplicates (ignoring
 * case) kept once. The wildcard '*' is kept as is.
 *
 * @param string $setting Value of oauth_self_register_groups
 *
 * @return array<int, string> Allowed group names or ids; [] when self-registration is disabled
 */
function oauth2ParseAllowedGroups(string $setting): array
{
    $decoded = html_entity_decode($setting, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $allowed = [];
    foreach (explode(',', $decoded) as $entry) {
        $entry = trim($entry);
        if ($entry === '') {
            continue;
        }
        $allowed[mb_strtolower($entry, 'UTF-8')] ??= $entry;
    }

    return array_values($allowed);
}

/**
 * Tell whether a group of the provider answer is one of the allowed groups.
 *
 * A group matches through its name or its id, ignoring case: Entra group names are not unique
 * and may be created by the members themselves, so the object id lets an administrator name
 * one group without ambiguity. The wildcard is not a group and never matches here.
 *
 * @param array<int, string> $allowedGroups oauth2ParseAllowedGroups() result
 * @param mixed              $group         Entry of the provider answer: {id, displayName}
 *
 * @return bool
 */
function oauth2GroupIsAllowed(array $allowedGroups, mixed $group): bool
{
    if (is_array($group) === false) {
        return false;
    }

    $candidates = [];
    foreach (['displayName', 'id'] as $field) {
        $value = $group[$field] ?? null;
        if ((is_string($value) || is_int($value)) && trim((string) $value) !== '') {
            $candidates[] = mb_strtolower(trim((string) $value), 'UTF-8');
        }
    }

    foreach ($allowedGroups as $allowed) {
        if ($allowed !== OAUTH2_SELF_REGISTER_ANYONE
            && in_array(mb_strtolower($allowed, 'UTF-8'), $candidates, true) === true
        ) {
            return true;
        }
    }

    return false;
}

/**
 * Decide whether a user who has no TeamPass account yet may have one created at sign-in.
 *
 * - no allowed group: self-registration is disabled, nobody;
 * - '*' among them: every user the provider authenticated;
 * - otherwise: members of at least one allowed group.
 *
 * @param array<int, string> $allowedGroups oauth2ParseAllowedGroups() result
 * @param array<int, mixed>  $userGroups    Groups of the provider answer: {id, displayName}
 *
 * @return bool
 */
function oauth2UserMayRegister(array $allowedGroups, array $userGroups): bool
{
    if (in_array(OAUTH2_SELF_REGISTER_ANYONE, $allowedGroups, true) === true) {
        return true;
    }

    foreach ($userGroups as $group) {
        if (oauth2GroupIsAllowed($allowedGroups, $group) === true) {
            return true;
        }
    }

    return false;
}

/**
 * Groups of a user that are matched against the TeamPass role titles.
 *
 * The allowed groups only open the self-registration: they are left out, so that a role named
 * after them is not granted to every user they let in. Entries that are not groups are dropped.
 *
 * @param array<int, string> $allowedGroups oauth2ParseAllowedGroups() result
 * @param array<int, mixed>  $userGroups    Groups of the provider answer: {id, displayName}
 *
 * @return array<int, array<string, mixed>> The remaining groups, reindexed
 */
function oauth2GroupsForRoleMatching(array $allowedGroups, array $userGroups): array
{
    $groups = [];
    foreach ($userGroups as $group) {
        if (is_array($group) === true && oauth2GroupIsAllowed($allowedGroups, $group) === false) {
            $groups[] = $group;
        }
    }

    return $groups;
}

/**
 * Titles a TeamPass role named after a provider group may be stored as.
 *
 * Every write of a role title goes through FILTER_SANITIZE_FULL_SPECIAL_CHARS (Roles page, role
 * created from the OAuth2 or LDAP synchronization, domain role): "Équipe R&D" is stored as
 * "&Eacute;quipe R&amp;D", which a lookup on the raw group name never finds. The raw name is
 * kept too, so the lookup still finds every title it found before.
 *
 * @param mixed $groupName displayName of a group of the provider answer
 *
 * @return array<int, string> Values to look up in roles_title.title; [] for a group without a name
 */
function oauth2RoleTitleCandidates(mixed $groupName): array
{
    if (is_string($groupName) === false || trim($groupName) === '') {
        return [];
    }

    return array_values(array_unique([
        $groupName,
        (string) filter_var($groupName, FILTER_SANITIZE_FULL_SPECIAL_CHARS),
    ]));
}

/**
 * Group name a stored role title stands for, to compare it with the names the provider sends.
 *
 * @param string $storedTitle roles_title.title, stored entity-encoded (see oauth2RoleTitleCandidates())
 *
 * @return string
 */
function oauth2RoleTitleAsGroupName(string $storedTitle): string
{
    return html_entity_decode($storedTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Detail of a refused self-registration for the Logs page: the login and the groups received.
 *
 * Groups without a name are counted: Microsoft Graph returns them that way when the app
 * registration lacks the delegated Group.Read.All permission, which is then the cause.
 *
 * @param string            $login      Login of the user
 * @param array<int, mixed> $userGroups Groups of the provider answer: {id, displayName}
 * @param int               $maxLength  Size of the log column (log_system.field_1)
 *
 * @return string
 */
function oauth2SelfRegisterLogDetail(string $login, array $userGroups, int $maxLength = 250): string
{
    $names = [];
    $unnamed = 0;
    foreach ($userGroups as $group) {
        $name = is_array($group) === true ? ($group['displayName'] ?? null) : null;
        if ((is_string($name) || is_int($name)) && trim((string) $name) !== '') {
            $names[] = trim((string) $name);
        } else {
            $unnamed++;
        }
    }

    $received = $names === [] ? 'none' : implode(', ', $names);
    if ($unnamed > 0) {
        $received .= ' (+' . $unnamed . ' without name)';
    }

    return mb_substr($login . ' | groups: ' . $received, 0, $maxLength);
}
