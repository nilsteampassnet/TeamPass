<?php

namespace TeampassClasses\SuperGlobal;

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
 * @file      SuperGlobal.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Handle global variables
 */
class SuperGlobal
{
    /**
     * Call to dataSanitizer function
     *
     * @param string $key
     * @return array
     */
    protected function dataSanitizerCall($key)
    {
        return  dataSanitizer(
            ['key' => $key],
            ['key' => 'trim|escape'],
            __DIR__ . '/../../../../'
        );
    }

    /**
     * Sets a variable
     *
     * @param  string                $key       Key to use
     * @param  string|array|bool|int $value     Value to put
     * @param  string                $type      Type of super global
     * @param  string                $special   Is this a special superglobal definition
     * @return void
     */
    public static function put($key, $value, $type, $special = false)
    {
        if ($type === 'SESSION') {
            if ($special === 'lang') {
                $_SESSION['teampass']['lang'][$key] = $value;
            } else if ($special === 'user') {
                $_SESSION['user'][$key] = $value;
                return (isset($_SESSION['user'][$key]) === true ? $_SESSION['user'][$key] : null);
            } else if ($special === 'arr_roles') {
                $_SESSION['arr_roles'][$key] = $value;
            } else if ($special === 'arr_roles_full') {
                $_SESSION['arr_roles_full'][$key] = $value;
            } else if ($special === 'latest_items_tab') {
                $_SESSION['latest_items_tab'][$key] = $value;
            }
            $_SESSION[$key] = $value;
        } elseif ($type === 'SERVER') {
            $_SERVER[$key] = $value;
        } elseif ($type === 'GET') {
            $_GET[$key] = $value;
        } elseif ($type === 'COOKIE') {
            $_COOKIE[$key] = $value;
        }
    }

    /**
     * Returns a variable
     *
     * @param  string $key     Key to use
     * @param  string $type    Type of super global
     * @param  string $special Is this a special superglobal definition
     * @return mixed
     */
    public function get($key, $type, $special = false)
    {
        if ($type === 'SESSION') {
            if ($special === 'lang') {
                return (isset($_SESSION['teampass']['lang'][$key]) === true ? $_SESSION['teampass']['lang'][$key] : null);
            } else if ($special === 'user') {
                return (isset($_SESSION['user'][$key]) === true ? $_SESSION['user'][$key] : null);
            } else if ($special === 'arr_roles') {
                return (isset($_SESSION['arr_roles'][$key]) === true ? $_SESSION['arr_roles'][$key] : null);
            } else if ($special === 'arr_roles_full') {
                return (isset($_SESSION['arr_roles_full'][$key]) === true ? $_SESSION['arr_roles_full'][$key] : null);
            } else if ($special === 'latest_items_tab') {
                return (isset($_SESSION['latest_items_tab'][$key]) === true ? $_SESSION['latest_items_tab'][$key] : null);
            }
            return (isset($_SESSION[$key]) === true ? $_SESSION[$key] : null);
        } elseif ($type === 'SERVER') {
            return (isset($_SERVER[$key]) === true ? $this->dataSanitizerCall($_SERVER[$key])['key'] : null);
        } elseif ($type === 'GET') {
            return (isset($_GET[$key]) === true ? $this->dataSanitizerCall($_GET[$key])['key']  : null);
        } elseif ($type === 'COOKIE') {
            return (isset($_COOKIE[$key]) === true ? $this->dataSanitizerCall($_COOKIE[$key])['key'] : null);
        } elseif ($type === 'POST') {
            return (isset($_POST[$key]) === true ? $this->dataSanitizerCall($_POST[$key])['key']  : null);
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
        } elseif ($type === 'COOKIE') {
            setcookie($_COOKIE[$key], "", time() - 3600, "/", "", true, true);
        }
    }
}
