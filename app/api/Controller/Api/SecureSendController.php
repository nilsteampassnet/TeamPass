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
 * @file      SecureSendController.php
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

require_once API_ROOT_PATH . '/../sources/secure_send_api_logic.php';
require_once API_ROOT_PATH . '/../sources/secure_send_create.php';
require_once API_ROOT_PATH . '/../sources/secure_send_storage.php';
require_once API_ROOT_PATH . '/../sources/secure_send_active.php';

/**
 * Secure Send links created by API clients (browser extension, paired devices, scripts).
 *
 * Creation goes through secureSendCreateLink(), the inventory through secureSendActiveLinks()
 * and revocation through secureSendRevokeLink(), the functions the web handlers use, so policy,
 * access, encryption, audit and email delivery are identical.
 */
class SecureSendController extends BaseController
{
    /**
     * Publish the instance policy the client needs to build its form (GET).
     * Always answers 200 so a client can hide the action when it is disabled.
     *
     * @param array $userData User data from JWT token
     * @return void
     */
    public function policyAction(array $userData): void
    {
        if ($this->requireMethod('GET') === false) {
            return;
        }

        $policy = secureSendApiPolicy($this->settings(), $userData);
        $this->sendOutput(json_encode($policy), ['Content-Type: application/json', $this->statusLine(200)]);
    }

    /**
     * Create a Secure Send link (POST, JSON body): a snapshot of an item the caller can read,
     * or a note whose content the caller supplies.
     *
     * The response URL is a bearer secret: no-store, never logged, and no idempotent replay
     * (a replay would have to store it).
     *
     * @param array $userData User data from JWT token
     * @return void
     */
    public function createAction(array $userData): void
    {
        header('Cache-Control: no-store');
        header('Pragma: no-cache');
        if ($this->requireMethod('POST') === false) {
            return;
        }

        $settings = $this->settings();
        if (secureSendApiEnabled($settings) === false) {
            $this->sendRefusal('secure_send_disabled');
            return;
        }

        $request = $this->currentRequest();
        if ($request->headers->has('Idempotency-Key')) {
            $this->sendRefusal('invalid_payload', 'Idempotency-Key is not supported on this endpoint');
            return;
        }
        if ((string) $request->getQueryString() !== '') {
            $this->sendRefusal('invalid_payload', 'Parameters must be sent in a JSON body, not in the URL');
            return;
        }
        if ($request->getContentTypeFormat() !== 'json') {
            $this->sendRefusal('invalid_payload', 'A JSON body is required');
            return;
        }
        try {
            $body = $request->toArray();
            $input = secureSendApiCreateInput($body);
        } catch (InvalidArgumentException $e) {
            $this->sendRefusal('invalid_payload', $e->getMessage());
            return;
        } catch (Throwable $e) {
            $this->sendRefusal('invalid_payload', 'Invalid JSON body');
            return;
        }

        try {
            // A note discloses nothing from the vault: the private key is never unwrapped for it.
            $userKeys = ['private_key' => '', 'public_key' => ''];
            if ($input['send_type'] === 'item') {
                // Same visibility pre-check as item/getOtp; secureSendReadItem() stays the authority.
                loadClasses('DB');
                $itemId = (int) $input['id'];
                $item = DB::queryFirstRow(
                    'SELECT id_tree FROM ' . prefixTable('items') . '
                    WHERE id = %i AND inactif = 0 AND (deleted_at IS NULL OR deleted_at = 0)',
                    $itemId
                );
                if ($item === null) {
                    $this->sendRefusal('not_found');
                    return;
                }
                $folderAccessModel = new FolderAccessModel();
                if ($folderAccessModel->canAccessItemInFolder($userData, (int) $item['id_tree'], $itemId) === false
                    || $folderAccessModel->satisfiesItemRestriction($itemId, (int) $userData['id']) === false
                ) {
                    $this->sendRefusal('not_allowed');
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
            }

            $result = secureSendCreateLink($input, [
                'id' => (int) $userData['id'],
                'private_key' => (string) $userKeys['private_key'],
                'public_key' => (string) $userKeys['public_key'],
                'read_only' => (int) ($userData['is_read_only'] ?? 1) === 1,
            ], $settings);
        } catch (SecureSendCreateRefusal $e) {
            $this->sendRefusal($e->getMessage());
            return;
        } catch (Throwable $e) {
            // Messages can carry SQL or request data; only the class is logged.
            error_log('[API] Secure Send creation failed (' . get_class($e) . ')');
            $this->sendRefusal('server_error');
            return;
        }

        $this->markApiFunctionalActivity($userData);
        $this->sendOutput(
            json_encode(secureSendApiCreatedBody($result)),
            ['Content-Type: application/json', $this->statusLine(201)]
        );
    }

    /**
     * List the caller's links that can still be opened or are suspended by a policy (GET),
     * newest first, paginated with ?limit=&offset= and X-Total-Count.
     *
     * Like revocation, it does not depend on secure_send_api_enabled: it lets a client find
     * and revoke links. It is empty while one-time view links are off, as on the web.
     *
     * @param array $userData User data from JWT token
     * @return void
     */
    public function listAction(array $userData): void
    {
        // Item labels and link metadata are personal: keep them out of shared caches.
        header('Cache-Control: no-store');
        header('Pragma: no-cache');
        if ($this->requireMethod('GET') === false) {
            return;
        }
        try {
            $window = secureSendApiListWindow($this->currentRequest()->query->all());
        } catch (InvalidArgumentException $e) {
            $this->sendRefusal('invalid_payload', $e->getMessage());
            return;
        }

        $settings = $this->settings();
        $ownerId = (int) $userData['id'];
        // Same sequence as the web list: a disabled feature neither reads nor cleans up links.
        if ((int) ($settings['otv_is_enabled'] ?? 0) === 1) {
            try {
                // Opportunistic: expired links leave with their audit event, failures do not block the list.
                secureSendPurgeExpiredLinks($settings);
            } catch (Throwable $e) {
                error_log('[API] Secure Send cleanup failed (' . get_class($e) . ')');
            }
        }
        try {
            $rows = secureSendActiveLinks($settings, $ownerId, null, null, true);
            $statuses = $rows === [] ? [] : secureSendEmailStatuses($ownerId);
        } catch (Throwable $e) {
            error_log('[API] Secure Send inventory failed (' . get_class($e) . ')');
            $this->sendRefusal('server_error');
            return;
        }

        $links = secureSendApiListBody(array_slice($rows, $window['offset'], $window['limit']), $statuses);
        // Like item reads: an empty answer is not user activity.
        if ($links !== []) {
            $this->markApiFunctionalActivity($userData);
        }
        $this->sendOutput(json_encode($links), [
            'Content-Type: application/json',
            'X-Total-Count: ' . count($rows),
            $this->statusLine(200),
        ]);
    }

    /**
     * Revoke one of the caller's Secure Send links (DELETE, ?id=N).
     *
     * Available whatever the Secure Send settings, like the web: removing a link only narrows
     * exposure. secureSendRevokeLink() checks ownership under the row lock and records the
     * revoked event; the owner is the token's user, never a request member.
     *
     * @param array $userData User data from JWT token
     * @return void
     */
    public function revokeAction(array $userData): void
    {
        if ($this->requireMethod('DELETE') === false) {
            return;
        }

        $request = $this->currentRequest();
        if ($request->headers->has('Idempotency-Key')) {
            $this->sendRefusal('invalid_payload', 'Idempotency-Key is not supported on this endpoint');
            return;
        }
        try {
            $sendId = secureSendApiRevokeId($request->query->all());
        } catch (InvalidArgumentException $e) {
            $this->sendRefusal('invalid_payload', $e->getMessage());
            return;
        }

        try {
            $revoked = secureSendRevokeLink($sendId, (int) $userData['id'], $this->settings());
        } catch (Throwable $e) {
            // Messages can carry SQL; only the class is logged.
            error_log('[API] Secure Send revocation failed (' . get_class($e) . ')');
            $this->sendRefusal('server_error');
            return;
        }
        if ($revoked === false) {
            // Unknown and foreign links answer alike, so link ids cannot be probed.
            $this->sendRefusal('not_found', 'Secure Send link not found');
            return;
        }

        $this->markApiFunctionalActivity($userData);
        $this->sendOutput(json_encode(['id' => $sendId]), ['Content-Type: application/json', $this->statusLine(200)]);
    }

    /**
     * Answer a problem response carrying the stable machine-readable reason.
     *
     * @param string $reason Refusal reason (secureSendApiRefusalStatuses() key)
     * @param string|null $detail Explanation overriding the default one
     * @return void
     */
    private function sendRefusal(string $reason, ?string $detail = null): void
    {
        $status = secureSendApiRefusalStatuses()[$reason] ?? 500;
        $this->sendProblem($status, $detail ?? secureSendApiRefusalDetail($reason), [], ['reason' => $reason]);
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
     * Answer 405 with an Allow header when the request uses another method.
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
