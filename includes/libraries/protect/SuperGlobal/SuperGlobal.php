<?php
/**
 * Manage global variables
 *
 * @package       SuperGlobal.php
 * @author        Nils Laumaillé <nils@teampass.net>
 * @version       2.1.27
 * @copyright     2009-2019 Nils Laumaillé
 * @license       GNU GPL-3.0
 * @link          https://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

namespace protect\SuperGlobal;

// Start session in case its not
if (session_id() === '') {
    include_once __DIR__.'/../../../../sources/SecureHandler.php';
    session_start();
}

/**
 * Handle global variables
 */
class SuperGlobal
{
    /**
     * Sets a variable
     *
     * @param  string $key   Key to use
     * @param  string $value Value to put
     * @param  string $type  Type of super global
     * @return void
     */
    public static function put($key, $value, $type)
    {
        if ($type === 'SESSION') {
            $_SESSION[$key] = $value;
        } elseif ($type === 'SERVER') {
            $_SERVER[$key] = $value;
        } elseif ($type === 'GET') {
            $_GET[$key] = $value;
        }
    }

    /**
     * Returns a variable
     *
     * @param  string $key  Key to use
     * @param  string $type Type of super global
     * @return mixed
     */
    public static function get($key, $type)
    {
        if ($type === 'SESSION') {
            return (isset($_SESSION[$key]) === true ? $_SESSION[$key] : null);
        } elseif ($type === 'SERVER') {
            return (isset($_SERVER[$key]) === true ? filter_var($_SERVER[$key], FILTER_SANITIZE_STRING) : null);
        } elseif ($type === 'GET') {
            return (isset($_GET[$key]) === true ? filter_var($_GET[$key], FILTER_SANITIZE_STRING) : null);
        }
    }

    /**
     * Delete a variable
     *
     * @param  string $key  Key to use
     * @param  string $type Type of super global
     * @return void
     */
    public static function forget($key, $type)
    {
        if ($type === 'SESSION') {
            unset($_SESSION[$key]);
        } elseif ($type === 'SERVER') {
            unset($_SERVER[$key]);
        } elseif ($type === 'SERVER') {
            unset($_GET[$key]);
        }
    }
}
