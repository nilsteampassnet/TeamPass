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
 * @copyright 2009-2026 Teampass.net
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
     * Cache key used for APCu settings storage.
     * Bump this constant to invalidate all cached settings across workers.
     */
    private const APCU_CACHE_KEY = 'teampass_settings_v1';

    /**
     * APCu cache TTL in seconds (60 s — short enough to reflect manual DB edits).
     */
    private const APCU_TTL = 60;

    /**
     * Load settings from the database.
     * When APCu is available, settings are cached for APCU_TTL seconds to
     * avoid a full DB query on every request.
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

        // Serve from APCu cache when available
        if (function_exists('apcu_fetch') === true) {
            $success = false;
            /** @var array<string,string>|false $cached */
            $cached = apcu_fetch(self::APCU_CACHE_KEY, $success);
            if ($success === true && is_array($cached) === true) {
                return $cached;
            }
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

        // Store in APCu for subsequent requests within this worker
        if (function_exists('apcu_store') === true) {
            apcu_store(self::APCU_CACHE_KEY, $ret, self::APCU_TTL);
        }

        return $ret;
    }

    /**
     * Invalidate the APCu settings cache.
     * Must be called after any write to teampass_misc.
     */
    public static function invalidateCache(): void
    {
        if (function_exists('apcu_delete') === true) {
            apcu_delete(self::APCU_CACHE_KEY);
        }
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