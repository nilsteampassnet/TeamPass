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
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use LdapRecord\Models\ActiveDirectory\Group as BaseGroup ;
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

        // Always select CN and the chosen GUID attribute for the LDAP query
        $selectAttrs = ['cn', $guidAttr];
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
                            $adGroupId = strtolower(vsprintf(
                                '%02x%02x%02x%02x-%02x%02x-%02x%02x-%02x%02x-%02x%02x%02x%02x%02x%02x',
                                array_values(unpack('C16', $bin))
                            ));
                        } catch (\Throwable $e) {
                            // If conversion fails, assign a unique fallback
                            $adGroupId = 'invalid_guid_' . uniqid();
                        }
                    } else {
                        // Otherwise treat attribute as plain string (e.g. gidNumber)
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


    function getUserADGroups(string $userDN, Connection $connection, array $SETTINGS): array
    {
        // init
        $groupsArr = [];

        try {
            Container::addConnection($connection);
            // get id attribute
            if (isset($SETTINGS['ldap_guid_attibute']) ===true && empty($SETTINGS['ldap_guid_attibute']) === false) {
                $idAttribute = $SETTINGS['ldap_guid_attibute'];
            } else {
                $idAttribute = 'objectguid';
            }

            // Get user groups from AD
            $user = User::find($userDN);
            $groups = $user->groups()->paginate();
            foreach ($groups as $group) {
                array_push(
                    $groupsArr,
                    $group[$idAttribute][0]
                );
            }
        } catch (\LdapRecord\Auth\BindException $e) {
            // Do nothing
        }

        return [
            'error' => false,
            'message' => '',
            'userGroups' => $groupsArr,
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
}