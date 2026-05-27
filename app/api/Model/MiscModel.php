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
    // Get extension settings
    public function getBrowserExtensionSettings(): array
    {
        // Load config
        $configManager = new ConfigManager();
        $SETTINGS = $configManager->getAllSettings();

        return [
            'extension_fqdn' => $SETTINGS['browser_extension_fqdn'] ?? '',
            'extension_key' => $SETTINGS['browser_extension_key'] ?? '',
            'extension_url' => $SETTINGS['cpassman_url'] ?? '',
        ];
    }
}