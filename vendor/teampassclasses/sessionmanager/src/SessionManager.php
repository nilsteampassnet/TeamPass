<?php
namespace TeampassClasses\SessionManager;

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
 * @file      sessionManager.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;
use Defuse\Crypto\Key;
use TeampassClasses\SessionManager\EncryptedSessionProxy;
use TeampassClasses\ConfigManager\ConfigManager;

class SessionManager
{
    private static $session = null;

    public static function getSession()
    {
        if (null === self::$session) {
            // Load the encryption key
            $key = Key::loadFromAsciiSafeString(file_get_contents(SECUREPATH . "/" . SECUREFILE));

            // Create an instance of EncryptedSessionProxy
            $handler = new EncryptedSessionProxy(new \SessionHandler(), $key);

            // Create a new session with the encrypted session handler
            self::$session = new Session(new \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage([], $handler));

            if (session_status() === PHP_SESSION_NONE) {
                $request = Request::createFromGlobals();
                $isSecure = $request->isSecure();

                // Configure gc_maxlifetime dynamically based on maximum_session_expiration_time
                // This prevents PHP garbage collection from destroying session files before
                // the TeamPass application session expires
                $gcMaxLifetime = self::calculateGcMaxLifetime();
                ini_set('session.gc_maxlifetime', (string) $gcMaxLifetime);

                // Cookie lifetime is set to 24 hours to maintain PHP session data
                // This is longer than the application session duration (default 60 min)
                // to prevent PHP garbage collection from destroying session files prematurely.
                // The actual session expiration is controlled by user-session_duration
                // which is validated on every request in PerformChecks::checkSession()
                session_set_cookie_params([
                    'lifetime' => 86400, // 24 hours - keeps PHP session alive while app session is controlled separately
                    'path' => '/',
                    'secure' => $isSecure,
                    'httponly' => true,
                    'samesite' => 'Lax' // None || Lax  || Strict
                ]);
                self::$session->start();
            }
        }

        return self::$session;
    }

    /**
     * Calculate the gc_maxlifetime value based on maximum_session_expiration_time setting
     * Adds a 30-minute buffer to ensure PHP doesn't garbage collect sessions before they expire
     *
     * @return int gc_maxlifetime in seconds (minimum 7200s/2h, default 86400s/24h)
     */
    private static function calculateGcMaxLifetime(): int
    {
        $defaultGcMaxLifetime = 86400; // 24 hours default
        $minimumGcMaxLifetime = 7200;  // 2 hours minimum
        $bufferTime = 1800;            // 30 minutes buffer

        try {
            // Try to load the maximum_session_expiration_time setting
            $configManager = new ConfigManager();
            $maxSessionTime = $configManager->getSetting('maximum_session_expiration_time');

            if ($maxSessionTime !== null && is_numeric($maxSessionTime) && (int) $maxSessionTime > 0) {
                // Convert minutes to seconds and add buffer
                $calculatedLifetime = ((int) $maxSessionTime * 60) + $bufferTime;
                return max($calculatedLifetime, $minimumGcMaxLifetime);
            }
        } catch (\Exception $e) {
            // If ConfigManager is not available (e.g., during installation),
            // fall back to default value
        }

        return $defaultGcMaxLifetime;
    }

    public static function addRemoveFromSessionArray($key, $values = [], $action = 'add') {
        // Retrieve the array from the session
        $sessionArray = self::getSession()->get($key, []);

        foreach ($values as $value) {
            if ($action === 'add') {
                // Add the value to the array
                $sessionArray[] = $value;
            } elseif ($action === 'remove') {
                // Find the index of the value in the array
                $index = array_search($value, $sessionArray);
    
                // If the value is found in the array, remove it
                if ($index !== false) {
                    unset($sessionArray[$index]);
                }
            }
        }

        // Reassign the array to the session
        self::getSession()->set($key, $sessionArray);
    }

    public static function specificOpsOnSessionArray($key, $action = 'pop', $value = null) {
        // Retrieve the array from the session
        $sessionArray = self::getSession()->get($key, []);

        if ($action === 'pop') {
            // Remove the last value from the array
            array_pop($sessionArray);
        } elseif ($action === 'shift') {
            // Remove the first value from the array
            array_shift($sessionArray);
        } elseif ($action === 'reset') {
            // Reset the array
            $sessionArray = [];
        } elseif ($action === 'unshift' && is_null($value) === false) {
            // Add a value to the beginning of the array
            array_unshift($sessionArray, $value);
        }

        // Reassign the array to the session
        self::getSession()->set($key, $sessionArray);
    }

    public static function addRemoveFromSessionAssociativeArray($key, $values = [], $action = 'add') {
        // Retrieve the array from the session
        $sessionArray = self::getSession()->get($key, []);

        if ($action === 'add') {
            // Add the value to the array
            array_push($sessionArray, $values);
        } elseif ($action === 'remove') {
            // If the value exists in the array, remove it
            if (($key = array_search($values, $sessionArray)) !== false) {
                unset($sessionArray[$key]);
            }
        }

        // Reassign the array to the session
        self::getSession()->set($key, $sessionArray);
    }

    public static function getCookieValue($cookieName)
    {
        $request = Request::createFromGlobals();

        // Check if the cookie exists
        if ($request->cookies->has($cookieName)) {
            return $request->cookies->get($cookieName);
        }

        return null;
    }
}