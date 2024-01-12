<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 * @version    API
 *
 * @file      Operation.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2024 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Operation
{
    public function verifyJwt($jwt)
    {
        $JWT = new JWT();

        try {
            $JWT->decode($jwt, new Key(DB_PASSWD, 'HS256'));
    
            // Access is granted.    
            return array(
                "message" => "Access granted:",
                "error" => '',
            );
    
        }catch (Exception $e) {    
            return false;
        }
    }
}