<?php
namespace TeampassClasses\SessionManager;

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      sessionManager.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2023 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */

use Symfony\Component\HttpFoundation\Session\Session;

class SessionManager
{
    private static $session;

    public static function getSession()
    {
        if (null === self::$session) {
            self::$session = new Session();
            if (session_status() == PHP_SESSION_NONE) {
                self::$session->start();
            }
        }

        return self::$session;
    }
}