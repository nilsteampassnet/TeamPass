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
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

 class ConfigManager
 {
    private $settings;
 
    public function __construct( $rootPath = null, $rootUrl = null)
    {
        $this->loadConfiguration($rootPath, $rootUrl);
    }
 
    private function loadConfiguration($rootPath = null, $rootUrl = null)
    {
        $configPath = __DIR__ . '/../../../../includes/config/tp.config.php';
        global $SETTINGS;
         
         // Vérifier si le répertoire de configuration est défini et non vide, et que le fichier de configuration existe.
         if (file_exists($configPath) === false) {
            if ($rootPath === null && $rootUrl === null) {
                $this->settings = [];
            } else {
                $this->settings = [
                    'cpassman_dir' => $rootPath,
                    'cpassman_url' => $rootUrl,
                ];
            }
         } else {
            include_once $configPath;
            $this->settings = $SETTINGS;

             // Decrypt values of keys that start with "def"
            foreach ($this->settings as $key => $value) {
                if (strpos($value, 'def') === 0) {
                    $this->settings[$key] = $this->getDecryptedValue($value, 1);
                }
            }
         }
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
 }