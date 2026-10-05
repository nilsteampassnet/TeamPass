<?php

declare(strict_types=1);

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
 * @file      WebauthnController.php
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

require_once API_ROOT_PATH . '/Model/WebauthnModel.php';

/**
 * Vault passkeys for third-party sites, driven by the browser extension.
 *
 * The extension intercepts navigator.credentials on the site, builds the client data from the
 * tab's real origin and relays the ceremony here. TeamPass generates, stores and signs.
 */
class WebauthnController extends BaseController
{
    /**
     * Create a passkey on an existing item (POST).
     *
     * @param array $userData User data from JWT token
     * @return void
     */
    public function createAction(array $userData): void
    {
        if ($this->requireMethod('POST') === false) {
            return;
        }

        $input = $this->getQueryStringParams();
        try {
            $request = webauthnNormalizeCreateRequest(is_array($input) ? $input : []);
        } catch (InvalidArgumentException $e) {
            $this->sendProblem($e->getCode() === 422 ? 422 : 400, $e->getMessage());
            return;
        }

        $ret = (new WebauthnModel())->createCredential($userData, $request);
        if ($ret['error'] === true) {
            $this->sendProblemFromHeader($ret['error_header'], $ret['error_message']);
            return;
        }

        $this->markApiFunctionalActivity($userData);
        unset($ret['error']);
        $this->sendOutput(json_encode($ret), ['Content-Type: application/json', $this->statusLine(201)]);
    }

    /**
     * List the passkeys the caller can use, filtered by relying party or item (GET).
     *
     * @param array $userData User data from JWT token
     * @return void
     */
    public function listAction(array $userData): void
    {
        if ($this->requireMethod('GET') === false) {
            return;
        }

        $input = $this->getQueryStringParams();
        $input = is_array($input) ? $input : [];

        $rpId = null;
        $itemId = null;
        try {
            if (isset($input['rp_id']) === true && $input['rp_id'] !== '') {
                $rpId = webauthnReadRpId($input);
            }
            if (isset($input['item_id']) === true && $input['item_id'] !== '') {
                $itemId = webauthnReadPositiveInt($input, 'item_id');
            }
        } catch (InvalidArgumentException $e) {
            $this->sendProblem($e->getCode() === 422 ? 422 : 400, $e->getMessage());
            return;
        }
        if ($rpId === null && $itemId === null) {
            $this->sendProblem(400, 'rp_id or item_id is mandatory');
            return;
        }

        try {
            $credentials = (new WebauthnModel())->listCredentials($userData, $rpId, $itemId);
        } catch (Throwable $e) {
            error_log('[API] WebauthnController::listAction failed: ' . $e->getMessage());
            $this->sendProblem(500, 'An internal error occurred. Please contact support.');
            return;
        }

        if ($credentials !== []) {
            $this->markApiFunctionalActivity($userData);
        }
        $this->sendOutput(json_encode($credentials), ['Content-Type: application/json', $this->statusLine(200)]);
    }

    /**
     * Sign an assertion with a passkey (POST).
     *
     * @param array $userData User data from JWT token
     * @return void
     */
    public function assertAction(array $userData): void
    {
        if ($this->requireMethod('POST') === false) {
            return;
        }

        $input = $this->getQueryStringParams();
        try {
            $request = webauthnNormalizeAssertRequest(is_array($input) ? $input : []);
        } catch (InvalidArgumentException $e) {
            $this->sendProblem($e->getCode() === 422 ? 422 : 400, $e->getMessage());
            return;
        }

        include_once API_ROOT_PATH . '/inc/jwt_utils.php';
        $userKeys = get_user_keys(
            (int) $userData['id'],
            (string) $userData['key_tempo'],
            isset($userData['jti']) ? (string) $userData['jti'] : null
        );
        if ($userKeys === null) {
            $this->sendProblem(401, 'Invalid session or user keys not found');
            return;
        }

        $ret = (new WebauthnModel())->assertCredential(
            $userData,
            $request,
            (string) $userKeys['private_key'],
            (string) $userKeys['public_key']
        );
        if ($ret['error'] === true) {
            $this->sendProblemFromHeader($ret['error_header'], $ret['error_message']);
            return;
        }

        $this->markApiFunctionalActivity($userData);
        unset($ret['error']);
        $this->sendOutput(json_encode($ret), ['Content-Type: application/json', $this->statusLine(200)]);
    }

    /**
     * Delete a passkey (DELETE).
     *
     * @param array $userData User data from JWT token
     * @return void
     */
    public function deleteAction(array $userData): void
    {
        if ($this->requireMethod('DELETE') === false) {
            return;
        }

        $input = $this->getQueryStringParams();
        try {
            $credentialRowId = webauthnReadPositiveInt(is_array($input) ? $input : [], 'id');
        } catch (InvalidArgumentException $e) {
            $this->sendProblem(400, $e->getMessage());
            return;
        }

        $ret = (new WebauthnModel())->deleteCredential($userData, $credentialRowId);
        if ($ret['error'] === true) {
            $this->sendProblemFromHeader($ret['error_header'], $ret['error_message']);
            return;
        }

        $this->markApiFunctionalActivity($userData);
        $this->sendOutput(json_encode($ret), ['Content-Type: application/json', $this->statusLine(200)]);
    }

    /**
     * Answer 405 with an Allow header when the request uses another method.
     *
     * @param string $method Expected HTTP method
     * @return bool True when the method matches
     */
    private function requireMethod(string $method): bool
    {
        if (strtoupper(symfonyRequest::createFromGlobals()->getMethod()) === $method) {
            return true;
        }

        $this->sendProblem(405, 'Method not supported', ['Allow: ' . $method]);

        return false;
    }
}
