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
 * @file      OpenLdapExtra.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use LdapRecord\Models\OpenLDAP\Group as BaseGroup ;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\OpenLDAP\User;

class OpenLdapExtra extends BaseGroup 
{
    public function getADGroups(Connection $connection, array $settings): array
    {
        // Configure objectClasses based on settings
        if (isset($settings['ldap_group_objectclasses_attibute']) === true) {
            static::$objectClasses = explode(",",$settings['ldap_group_objectclasses_attibute']);
        }
        if (!$connection || !$connection->isConnected()) {
            return [
                'error' => true,
                'message' => 'No valid LDAP connection is available for the query.',
                'userGroups' => [],
            ];
        }

        // prepare query
        $query = $connection->query();

        // Select attributes including member attributes for group membership mapping
        $guidAttr = (!empty($settings['ldap_guid_attibute']) ? $settings['ldap_guid_attibute'] : 'gidnumber');
        $query->select(['cn', $guidAttr, 'member', 'uniquemember', 'memberuid']);

        // get all parameters to search
        foreach (static::$objectClasses as $objectClass) {
            $query->where('objectclass', '=', $objectClass);
        }
        try {
            // perform query and get data
            $groups = $query->get();

            $groupsArr = [];
            foreach($groups as $key => $group) {
                // Check if the attribute exists and contains at least one value
                $adGroupId = isset($group[$guidAttr][0]) ? (int) $group[$guidAttr][0] : -1;

                // Check the "cn" attribute
                $adGroupTitle = isset($group['cn'][0]) ? $group['cn'][0] : '';

                // Build the entry only if we have a minimum of info
                $groupsArr[$adGroupId] = [
                    'ad_group_id'    => $adGroupId,
                    'ad_group_title' => $adGroupTitle,
                    'role_id'        => -1,
                    'id'             => -1,
                    'role_title'     => '',
                    'members'        => $this->extractGroupMembers($group),
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
     * Handles posixGroup (memberUid), groupOfNames (member), and groupOfUniqueNames (uniqueMember)
     *
     * @param array $group The LDAP group entry
     * @return array Array of members with type (uid or dn) and value
     */
    private function extractGroupMembers(array $group): array
    {
        $members = [];

        // posixGroup: memberUid contains uid/login of members
        if (isset($group['memberuid']) === true) {
            foreach ($group['memberuid'] as $key => $member) {
                if ($key !== 'count' && !empty($member)) {
                    $members[] = ['type' => 'uid', 'value' => $member];
                }
            }
        }

        // groupOfNames: member contains DN of members
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

        return $members;
    }

    /**
     * Get user's AD groups by searching groups that contain this user
     * This method works for OpenLDAP which doesn't have memberof attribute on users
     *
     * @param string $userDN User's DN
     * @param Connection $connection LDAP connection
     * @param array $SETTINGS Teampass settings
     * @param string $userLogin User's login/uid (used for posixGroup memberuid matching)
     * @return array Array with error status and userGroups
     */
    function getUserADGroups(string $userDN, Connection $connection, array $SETTINGS, string $userLogin = ''): array
    {
        $groupsArr = [];

        try {
            // Get the GUID attribute to use for group identification
            $guidAttr = strtolower(!empty($SETTINGS['ldap_guid_attibute']) ? $SETTINGS['ldap_guid_attibute'] : 'gidnumber');

            // Use provided userLogin for posixGroup matching, fallback to extracting from DN
            $userUid = !empty($userLogin) ? $userLogin : $this->extractUidFromDN($userDN);

            // Configure objectClasses for groups
            if (isset($SETTINGS['ldap_group_objectclasses_attibute']) === true && !empty($SETTINGS['ldap_group_objectclasses_attibute'])) {
                static::$objectClasses = explode(",", $SETTINGS['ldap_group_objectclasses_attibute']);
            }

            // Query all groups with their members
            $query = $connection->query();
            $query->select(['cn', $guidAttr, 'member', 'uniquemember', 'memberuid']);

            foreach (static::$objectClasses as $objectClass) {
                $query->where('objectclass', '=', $objectClass);
            }

            $groups = $query->get();

            // Check each group for this user's membership
            foreach ($groups as $group) {
                $groupCn = $group['cn'][0] ?? 'unknown';
                $isMember = $this->isUserMemberOfGroup($group, $userDN, $userUid);

                if ($isMember) {
                    // Get the group ID
                    $groupId = isset($group[$guidAttr][0]) ? $group[$guidAttr][0] : null;
                    if ($groupId !== null) {
                        $groupsArr[] = $groupId;
                    }
                }
            }

        } catch (\Throwable $e) {
            error_log('TEAMPASS Error - OpenLDAP getUserADGroups: ' . $e->getMessage());
        }

        return [
            'error' => false,
            'message' => '',
            'userGroups' => $groupsArr,
        ];
    }

    /**
     * Extract UID from a DN string
     * Examples:
     *   "uid=john,ou=users,dc=example,dc=com" -> "john"
     *   "cn=John Doe,ou=users,dc=example,dc=com" -> "John Doe"
     *
     * @param string $dn The distinguished name
     * @return string The extracted UID or CN value
     */
    private function extractUidFromDN(string $dn): string
    {
        // Try to extract uid= first
        if (preg_match('/uid=([^,]+)/i', $dn, $matches)) {
            return $matches[1];
        }
        // Fall back to cn=
        if (preg_match('/cn=([^,]+)/i', $dn, $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * Check if a user is a member of a group
     * Checks memberuid (posixGroup), member (groupOfNames), and uniquemember (groupOfUniqueNames)
     *
     * @param array $group The group entry from LDAP
     * @param string $userDN The user's DN
     * @param string $userUid The user's UID
     * @return bool True if user is a member
     */
    private function isUserMemberOfGroup(array $group, string $userDN, string $userUid): bool
    {
        $groupCn = $group['cn'][0] ?? 'unknown';

        // Check memberuid (posixGroup) - matches by UID
        if (isset($group['memberuid']) && !empty($userUid)) {
            $memberUids = [];
            foreach ($group['memberuid'] as $key => $member) {
                if ($key !== 'count') {
                    $memberUids[] = $member;
                    if (strcasecmp($member, $userUid) === 0) {
                        return true;
                    }
                }
            }
        }

        // Check member (groupOfNames) - matches by DN
        if (isset($group['member'])) {
            foreach ($group['member'] as $key => $member) {
                if ($key !== 'count' && strcasecmp($member, $userDN) === 0) {
                    return true;
                }
            }
        }

        // Check uniquemember (groupOfUniqueNames) - matches by DN
        if (isset($group['uniquemember'])) {
            foreach ($group['uniquemember'] as $key => $member) {
                if ($key !== 'count' && strcasecmp($member, $userDN) === 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
