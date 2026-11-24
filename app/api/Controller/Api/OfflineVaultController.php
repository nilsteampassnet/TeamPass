<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 *
 * @file      OfflineVaultController.php
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

use Symfony\Component\HttpFoundation\Request AS symfonyRequest;
use TeampassClasses\ConfigManager\ConfigManager;

require_once API_ROOT_PATH . '/../sources/offline_vault_api_logic.php';

/**
 * Offline vault of the browser extension: the policy it applies to its local copy.
 *
 * The copy itself is read through item/inFolders and item/changes, like the mobile vault.
 */
class OfflineVaultController extends BaseController
{
    /**
     * Publish the offline vault policy for the caller (GET).
     * Always answers 200: the extension erases its copy on allowed=false.
     *
     * @param array $userData User data from JWT token
     * @return void
     */
    public function policyAction(array $userData): void
    {
        if ($this->requireMethod('GET') === false) {
            return;
        }

        $policy = offlineVaultApiPolicy($this->settings(), $userData);
        $this->sendOutput(json_encode($policy), [
            'Content-Type: application/json',
            'Cache-Control: no-store',
            $this->statusLine(200),
        ]);
    }

    /**
     * Current TeamPass settings.
     *
     * @return array
     */
    protected function settings(): array
    {
        return (new ConfigManager())->getAllSettings();
    }

    /**
     * Current HTTP request.
     *
     * @return symfonyRequest
     */
    protected function currentRequest(): symfonyRequest
    {
        return symfonyRequest::createFromGlobals();
    }

    /**
     * Answer 405 unless the request uses the given method.
     *
     * @param string $method Expected HTTP method
     * @return bool True when the method matches
     */
    private function requireMethod(string $method): bool
    {
        if (strtoupper($this->currentRequest()->getMethod()) === $method) {
            return true;
        }

        $this->sendProblem(405, 'Method not supported', ['Allow: ' . $method]);

        return false;
    }
}
