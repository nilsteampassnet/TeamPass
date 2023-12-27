<?php

namespace TeampassClasses\LdapExtra;

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 * @file      OpenLdapExtra.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
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

        // get all parameters to search
        foreach (static::$objectClasses as $objectClass) {
            $query->where('objectclass', '=', $objectClass);
        }
        try {
            // perform query and get data
            $groups = $query->get();

            $groupsArr = [];
            foreach($groups as $key => $group) {
                $adGroupId = (int) $group[(isset($settings['ldap_guid_attibute']) === true && empty($settings['ldap_guid_attibute']) === false ? $settings['ldap_guid_attibute'] : 'gidnumber')][0];
                $groupsArr[$adGroupId] = [
                    'ad_group_id' => $adGroupId,
                    'ad_group_title' => $group['cn'][0],
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
            $groups = $user->groups()->get();
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
}
