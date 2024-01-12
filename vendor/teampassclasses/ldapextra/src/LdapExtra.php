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
 * @file      LdapExtra.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use LdapRecord\Connection;
use LdapRecord\Container;


class LdapExtra
{
    protected $connection;
    protected $settings;

    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    public function establishLdapConnection()
    {
        $config = [
            'hosts' => explode(',', $this->settings['ldap_hosts']),
            'base_dn' => $this->settings['ldap_bdn'],
            'username' => $this->settings['ldap_username'],
            'password' => $this->settings['ldap_password'],
            'port' => $this->settings['ldap_port'],
            'use_ssl' => (int) $this->settings['ldap_ssl'] === 1 ? true : false,
            'use_tls' => (int) $this->settings['ldap_tls'] === 1 ? true : false,
            'version' => 3,
            'timeout' => 5,
            'follow_referrals' => false,
            'options' => [
                LDAP_OPT_X_TLS_REQUIRE_CERT => isset($this->settings['ldap_tls_certiface_check']) ? $this->settings['ldap_tls_certiface_check'] : LDAP_OPT_X_TLS_HARD,
            ],
        ];

        try {
            $this->connection = new Connection($config);
            $this->connection->connect();            
            Container::addConnection($this->connection);
        } catch (\LdapRecord\ConnectionException $e) {
            throw new \Exception("Error - LDAP connection : " . $e->getMessage());
        } catch (\LdapRecord\Auth\BindException $e) {
            throw new \Exception("Error - LDAP bind : " . $e->getMessage());
        }

        return $this->connection;
    }
}