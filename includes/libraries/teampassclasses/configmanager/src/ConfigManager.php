<?php
namespace TeampassClasses\ConfigManager;

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
 * @file      ConfigManager.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use DB;

class ConfigManager
{
    private $settings;
 
    public function __construct()
    {
        $this->loadConfiguration();
    }
 
    private function loadConfiguration()
    {
        $this->settings = $this->loadSettingsFromDB('DB');
     }
 
     public function getSetting($key)
     {
         return isset($this->settings[$key]) ? $this->settings[$key] : null;
     }
 
     public function getAllSettings()
     {
        return $this->settings;
     }
     
    /**
     * Returns the decrypted value if needed.
     *
     * @param string $value       Value to decrypt.
     * @param int   $isEncrypted Is the value encrypted?
     *
     * @return string
     */
    public function getDecryptedValue(string $value, int $isEncrypted): string
    {
        return $isEncrypted ? cryption($value, '', 'decrypt')['string'] : $value;
    }

    /**
     * Load settings from the database.
     *
     * @return array
     */
    public function loadSettingsFromDB(): array
    {
        // Do we have a settings file?
        $settingsFile = __DIR__ . '/../../../../includes/config/settings.php';
        if (!file_exists($settingsFile) || empty(DB_HOST) === true) {
            return [];
        }

        // Load the DB library
        require_once __DIR__.'/../../../sergeytsalkov/meekrodb/db.class.php';
        $ret = [];

        $result = DB::query(
            'SELECT intitule, valeur
            FROM ' . prefixTable('misc') . '
            WHERE type = %s',
            'admin'
        );
        foreach ($result as $row) {
            $ret[$row['intitule']] = $row['valeur'];
        }

        return $ret;
    }

    /**
     * Get the last modification timestamp of the settings.
     *
     * @return string|null
     */
    public function getLastModificationTimestamp(): string|null
    {
        // Do we have a settings file?
        $settingsFile = __DIR__ . '/../../../../includes/config/settings.php';
        if (!file_exists($settingsFile) || empty(DB_HOST) === true) {
            return "";
        }

        // Load the DB library
        require_once __DIR__.'/../../../sergeytsalkov/meekrodb/db.class.php';

        $maxTimestamp = DB::queryFirstField(
            'SELECT GREATEST(MAX(created_at), MAX(updated_at)) AS timestamp
            FROM ' . prefixTable('misc') . '
            WHERE type = %s',
            'admin'
        );

        // NULL is returned if no settings are found or if the settings have no created_at value
        return $maxTimestamp;
    }
}