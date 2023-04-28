<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 * @version   3.0.7
 * @file      ldap.openldap.php
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

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\OpenLdap\User;

/*require_once 'SecureHandler.php';
session_name('teampass_session');
session_start();*/
if (isset($_SESSION['CPM']) === false || (int) $_SESSION['CPM'] !== 1) {
    //die('Hacking attempt...');
}

/**
 * Get the user's AD groups.
 *
 * @param string $userDN
 * @param LdapRecord\Connection $connection
 * @param array $SETTINGS
 *
 * @return array
 */
function getUserADGroups(string $userDN, LdapRecord\Connection $connection, array $SETTINGS): array
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
        require_once '../includes/libraries/LdapRecord/Models/OpenLDAP/User.php';
        $user = User::find($userDN);
        $groups = $user->groups()->get();
        foreach ($groups as $group) {
            /*array_push(
                $groupsArr,
                [
                    'title' => $group->getName(),
                    'dn' => $group->getDn(),
                    'id' => $group[$idAttribute][0],
                ]
            );*/
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