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

    public static function manageSessionArray($key, $values = [], $action = 'add') {
        // Récupérer le tableau de la session
        $sessionArray = self::getSession()->get($key, []);

        foreach ($values as $value) {
            if ($action === 'add') {
                // Ajouter la valeur au tableau
                $sessionArray[] = $value;
            } elseif ($action === 'remove') {
                // Trouver l'index de la valeur dans le tableau
                $index = array_search($value, $sessionArray);
    
                // Si la valeur est trouvée dans le tableau, la supprimer
                if ($index !== false) {
                    unset($sessionArray[$index]);
                }
            }
        }

        // Réaffecter le tableau à la session
        self::getSession()->set($key, $sessionArray);
    }
}