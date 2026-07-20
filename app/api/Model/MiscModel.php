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
 * @file      MiscModel.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2026 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

use TeampassClasses\ConfigManager\ConfigManager;

class MiscModel
{
    /**
     * Get the browser extension connection settings.
     *
     * The server version is included so a client can refresh it without waiting for
     * the next authentication (an instance upgraded between two logins reports the
     * new value here).
     *
     * @return array<string,string>
     */
    public function getBrowserExtensionSettings(): array
    {
        // Load config
        $configManager = new ConfigManager();
        $SETTINGS = $configManager->getAllSettings();

        return [
            'extension_fqdn' => $SETTINGS['browser_extension_fqdn'] ?? '',
            'extension_key' => $SETTINGS['browser_extension_key'] ?? '',
            'extension_url' => $SETTINGS['cpassman_url'] ?? '',
            'teampass_version' => TP_VERSION . '.' . TP_VERSION_MINOR,
            'teampass_version_major' => TP_VERSION,
            'teampass_version_minor' => TP_VERSION_MINOR,
        ];
    }
}