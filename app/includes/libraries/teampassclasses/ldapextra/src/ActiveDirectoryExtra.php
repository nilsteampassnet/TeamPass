<?php

namespace TeampassClasses\LdapExtra;

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
 * @file      ActiveDirectoryExtra.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use LdapRecord\Models\ActiveDirectory\Group as BaseGroup;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\ActiveDirectory\User;

class ActiveDirectoryExtra extends BaseGroup
{
    public function getADGroups(Connection $connection, array $settings): array
    {
        if (isset($settings['ldap_group_objectclasses_attibute'])) {
            static::$objectClasses = explode(",", $settings['ldap_group_objectclasses_attibute']);
        }

        if (!$connection || !$connection->isConnected()) {
            return [
                'error' => true,
                'message' => 'No valid LDAP connection is available for the query.',
                'userGroups' => [],
            ];
        }

        $query = $connection->query();

        // Determine which GUID attribute is used
        $guidAttr = strtolower(
            (isset($settings['ldap_guid_attibute']) && !empty($settings['ldap_guid_attibute']))
                ? $settings['ldap_guid_attibute']
                : 'gidnumber'
        );

        // Always select CN, the chosen GUID attribute, and member attributes for group membership mapping
        $selectAttrs = ['cn', $guidAttr, 'member', 'uniquemember', 'memberuid'];
        $query->select($selectAttrs);

        foreach (static::$objectClasses as $objectClass) {
            $query->where('objectclass', '=', $objectClass);
        }

        try {
            $groups = $query->paginate();

            $groupsArr = [];
            foreach ($groups as $group) {
                if (isset($group[$guidAttr][0])) {
                    // Convert binary AD objectGUID to standard GUID string format
                    if ($guidAttr === 'objectguid') {
                        try {
                            $bin = $group[$guidAttr][0];

                            $adGroupId = strtolower(
                                (new \LdapRecord\Models\Attributes\Guid($bin))->getValue()
                            );
                        } catch (\Throwable $e) {
                            error_log('TEAMPASS LDAP: invalid group objectGUID for group ' . ($group['cn'][0] ?? 'Unknown') . ': ' . $e->getMessage());

                            // Fallback to old behavior to avoid breaking GUI
                            try {
                                $bin = $group[$guidAttr][0];
                                $adGroupId = strtolower(vsprintf(
                                    '%02x%02x%02x%02x-%02x%02x-%02x%02x-%02x%02x-%02x%02x%02x%02x%02x%02x',
                                    array_values(unpack('C16', $bin))
                                ));
                            } catch (\Throwable $fallbackException) {
                                $adGroupId = 'invalid_guid_' . md5($group['cn'][0] ?? uniqid('', true));
                            }
                        }
                    } else {
                        // Otherwise treat attribute as plain string, for example gidNumber
                        $adGroupId = strtolower((string) $group[$guidAttr][0]);
                    }
                } else {
                    // Handle groups missing the expected GUID attribute
                    $adGroupId = 'missing_' . uniqid();
                }

                $groupsArr[$adGroupId] = [
                    'ad_group_id' => $adGroupId,
                    'ad_group_title' => $group['cn'][0] ?? 'Unknown',
                    'role_id' => -1,
                    'id' => -1,
                    'role_title' => '',
                    'members' => $this->extractGroupMembers($group),
                ];
            }

            return [
                'error' => false,
                'message' => 'Groups fetched successfully.',
                'userGroups' => $groupsArr,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => 'LDAP Error: ' . $e->getMessage(),
                'userGroups' => [],
            ];
        }
    }

    /**
     * Extract group members from LDAP group entry
     * Handles Active Directory member attribute and also posixGroup/groupOfNames for compatibility
     *
     * @param array $group The LDAP group entry
     * @return array Array of members with type (uid or dn) and value
     */
    private function extractGroupMembers(array $group): array
    {
        $members = [];

        // Active Directory and groupOfNames: member contains DN of members
        if (isset($group['member']) === true) {
            foreach ($group['member'] as $key => $member) {
                if ($key !== 'count' && !empty($member)) {
                    $members[] = ['type' => 'dn', 'value' => $member];
                }
            }
        }

        // groupOfUniqueNames: uniqueMember contains DN of members
        if (isset($group['uniquemember']) === true) {
            foreach ($group['uniquemember'] as $key => $member) {
                if ($key !== 'count' && !empty($member)) {
                    $members[] = ['type' => 'dn', 'value' => $member];
                }
            }
        }

        // posixGroup: memberUid contains uid/login of members
        if (isset($group['memberuid']) === true) {
            foreach ($group['memberuid'] as $key => $member) {
                if ($key !== 'count' && !empty($member)) {
                    $members[] = ['type' => 'uid', 'value' => $member];
                }
            }
        }

        return $members;
    }

    function getUserADGroups(?string $userDN, Connection $connection, array $SETTINGS): array
    {
        // init
        $groupsArr = [];

        if (empty($userDN)) {
            error_log('TEAMPASS LDAP: getUserADGroups called with empty userDN');

            return [
                'error' => false,
                'message' => 'Empty user DN.',
                'userGroups' => [],
            ];
        }

        try {
            Container::addConnection($connection);

            // get id attribute
            if (isset($SETTINGS['ldap_guid_attibute']) === true && empty($SETTINGS['ldap_guid_attibute']) === false) {
                $idAttribute = strtolower($SETTINGS['ldap_guid_attibute']);
            } else {
                $idAttribute = 'objectguid';
            }

            /*
             * Active Directory nested groups support.
             *
             * LDAP_MATCHING_RULE_IN_CHAIN:
             * 1.2.840.113556.1.4.1941
             *
             * This returns all groups where the user is a member,
             * including indirect membership through nested groups.
             */
            $escapedUserDN = ldap_escape($userDN, '', LDAP_ESCAPE_FILTER);

            $query = $connection->query();

            $query->select(['cn', $idAttribute]);

            $query->rawFilter(
                '(&(objectClass=group)(member:1.2.840.113556.1.4.1941:=' . $escapedUserDN . '))'
            );

            $groups = $query->paginate();

            foreach ($groups as $group) {
                if (!isset($group[$idAttribute][0])) {
                    continue;
                }

                if ($idAttribute === 'objectguid') {
                    try {
                        $bin = $group[$idAttribute][0];

                        $adGroupId = strtolower(
                            (new \LdapRecord\Models\Attributes\Guid($bin))->getValue()
                        );
                    } catch (\Throwable $e) {
                        error_log('TEAMPASS LDAP: invalid nested group objectGUID for group ' . ($group['cn'][0] ?? 'Unknown') . ': ' . $e->getMessage());
                        continue;
                    }
                } else {
                    $adGroupId = strtolower((string) $group[$idAttribute][0]);
                }

                $groupsArr[] = $adGroupId;
            }
        } catch (\Throwable $e) {
            error_log('TEAMPASS LDAP: getUserADGroups nested groups error: ' . $e->getMessage());
        }

        return [
            'error' => false,
            'message' => '',
            'userGroups' => array_values(array_unique($groupsArr)),
        ];
    }

    /**
     * Check is user is enabled
     *
     * @param string $userDN
     * @param Connection $connection
     * @return bool
     */
    function userIsEnabled(string $userDN, Connection $connection): bool
    {
        $isEnabled = false;
        try {
            Container::addConnection($connection);
            $user = User::find($userDN);
            $isEnabled = $user->isEnabled();
        } catch (\LdapRecord\Auth\BindException $e) {
            // Do nothing
        }
        return $isEnabled;
    }

    /**
     * Check whether a user is a member of a specific LDAP group using the user's memberOf attribute.
     * This is the user-centric alternative to isUserInAllowedGroup() and works best with
     * Active Directory where users carry a memberOf attribute populated by the server.
     *
     * @param string $groupDn Full distinguished name of the required group
     * @param array $userEntry LDAP user entry array that must contain the 'memberof' key
     * @return bool True if the user's memberof list contains $groupDn, or if $groupDn is empty
     */
    public function isUserInAllowedGroupByMemberOf(string $groupDn, array $userEntry): bool
    {
        if (trim($groupDn) === '') {
            return true;
        }

        $memberOf = $userEntry['memberof'] ?? [];
        if (empty($memberOf)) {
            error_log('TEAMPASS LDAP: isUserInAllowedGroupByMemberOf — user has no memberof attribute; consider using group-centric mode instead.');
            return false;
        }

        foreach ($memberOf as $key => $dn) {
            if ($key !== 'count' && strcasecmp((string) $dn, $groupDn) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a user is a member of a specific LDAP group identified by its full DN.
     * Uses a scope=base LDAP read so the group can reside outside the configured users base DN.
     *
     * @param string $groupDn Full distinguished name of the required group
     * @param string $userDn Distinguished name of the authenticating user
     * @param string $userUid Login name of the user (for posixGroup memberUid checks)
     * @param Connection $connection Active LdapRecord connection
     * @return bool True if the user is a member or if $groupDn is empty (no restriction)
     */
    public function isUserInAllowedGroup(
        string $groupDn,
        string $userDn,
        string $userUid,
        Connection $connection
    ): bool {
        if (trim($groupDn) === '') {
            return true;
        }

        try {
            /*
             * Active Directory nested group support.
             *
             * LDAP_MATCHING_RULE_IN_CHAIN:
             * 1.2.840.113556.1.4.1941
             *
             * This checks whether $userDn is a direct or indirect member
             * of the group identified by $groupDn.
             */
            if (trim($userDn) !== '') {
                $escapedUserDn = ldap_escape($userDn, '', LDAP_ESCAPE_FILTER);

                $nestedGroupEntry = $connection->query()
                    ->select(['cn'])
                    ->setDn($groupDn)
                    ->read()
                    ->rawFilter('(member:1.2.840.113556.1.4.1941:=' . $escapedUserDn . ')')
                    ->first();

                if ($nestedGroupEntry !== null) {
                    return true;
                }
            }

            /*
             * Fallback for direct membership and non-AD LDAP directories.
             */
            $groupEntry = $connection->query()
                ->select(['member', 'uniquemember', 'memberuid'])
                ->setDn($groupDn)
                ->read()
                ->whereHas('objectclass')
                ->first();

            if ($groupEntry === null) {
                error_log('TEAMPASS LDAP: Allowed login group not found: ' . $groupDn);
                return false;
            }

            // Check member, AD standard — DN comparison, case-insensitive
            if (isset($groupEntry['member'])) {
                foreach ($groupEntry['member'] as $key => $member) {
                    if ($key !== 'count' && strcasecmp((string) $member, $userDn) === 0) {
                        return true;
                    }
                }
            }

            // Check uniquemember — DN comparison
            if (isset($groupEntry['uniquemember'])) {
                foreach ($groupEntry['uniquemember'] as $key => $member) {
                    if ($key !== 'count' && strcasecmp((string) $member, $userDn) === 0) {
                        return true;
                    }
                }
            }

            // Check memberuid — UID comparison
            if (isset($groupEntry['memberuid']) && $userUid !== '') {
                foreach ($groupEntry['memberuid'] as $key => $member) {
                    if ($key !== 'count' && strcasecmp((string) $member, $userUid) === 0) {
                        return true;
                    }
                }
            }

            return false;
        } catch (\Throwable $e) {
            error_log('TEAMPASS LDAP: isUserInAllowedGroup error: ' . $e->getMessage());
            return false;
        }
    }
}
