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
 * @file      AuthControler.php
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

class AuthController extends BaseController
{
    /**
     *
     */
    public function authorizeAction()
    {
        $request = symfonyRequest::createFromGlobals();
        $requestMethod = $request->getMethod();
        $strErrorDesc = $responseData = $strErrorHeader = '';

        if (strtoupper($requestMethod) === 'POST') {
            // Security: Prevent credentials from being passed via query string
            // Only allow credentials in POST body (form-data or JSON)
            $queryString = $request->getQueryString();
            if (!empty($queryString)) {
                parse_str(html_entity_decode($queryString), $queryParams);
                $sensitiveParams = ['login', 'password', 'apikey'];
                foreach ($sensitiveParams as $param) {
                    if (isset($queryParams[$param])) {
                        $strErrorDesc = 'Credentials must be sent in request body (application/x-www-form-urlencoded or application/json), not in URL';
                        $strErrorHeader = 'HTTP/1.1 400 Bad Request';
                        break;
                    }
                }
            }

            // Proceed only if no security violation detected
            if (empty($strErrorDesc)) {
                $arrQueryStringParams = $this->getQueryStringParams();

                // Validate required parameters are present
                if (empty($arrQueryStringParams['login']) || empty($arrQueryStringParams['password']) || empty($arrQueryStringParams['apikey'])) {
                    $strErrorDesc = 'Missing required parameters: login, password, and apikey must be provided in request body';
                    $strErrorHeader = 'HTTP/1.1 400 Bad Request';
                } else {
                    require API_ROOT_PATH . "/Model/AuthModel.php";
                    try {
                        $authModel = new AuthModel();
                        $arrUser = $authModel->getUserAuth(
                            $arrQueryStringParams['login'],
                            $arrQueryStringParams['password'],
                            $arrQueryStringParams['apikey']
                        );
                        if (array_key_exists("token", $arrUser)) {
                            $responseData = json_encode($arrUser);
                        } else {
                            $strErrorDesc = $arrUser['error'] . " (" . $arrUser['info'] . ")";
                            $strErrorHeader = 'HTTP/1.1 401 Unauthorized';
                        }
                    } catch (Error $e) {
                        error_log('[API] AuthController::authorizeAction error: ' . $e->getMessage());
                        $strErrorDesc = 'An internal error occurred. Please contact support.';
                        $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
                    }
                }
            }

        } else {
            $strErrorDesc = 'Method '.$requestMethod.' not supported';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
            $arrErrorHeaders[] = 'Allow: POST';
        }

        // send output
        if (empty($strErrorDesc) === true) {
            $this->sendOutput(
                $responseData,
                ['Content-Type: application/json', 'HTTP/1.1 200 OK']
            );
        } else {
            $this->sendProblemFromHeader($strErrorHeader, $strErrorDesc, $arrErrorHeaders ?? []);
        }
    }

    /**
     * Token-based authorization for OAuth2/SSO users (Personal Access Token).
     *
     * POST only. Credentials must be provided in the request body, never the query
     * string. Body params: login + token. Returns the same JWT as the password path.
     */
    public function authorizeTokenAction()
    {
        $request = symfonyRequest::createFromGlobals();
        $requestMethod = $request->getMethod();
        $strErrorDesc = $responseData = $strErrorHeader = '';

        if (strtoupper($requestMethod) === 'POST') {
            // Security: prevent credentials from being passed via query string.
            $queryString = $request->getQueryString();
            if (!empty($queryString)) {
                parse_str(html_entity_decode($queryString), $queryParams);
                $sensitiveParams = ['login', 'token'];
                foreach ($sensitiveParams as $param) {
                    if (isset($queryParams[$param])) {
                        $strErrorDesc = 'Credentials must be sent in request body (application/x-www-form-urlencoded or application/json), not in URL';
                        $strErrorHeader = 'HTTP/1.1 400 Bad Request';
                        break;
                    }
                }
            }

            // Proceed only if no security violation detected
            if (empty($strErrorDesc)) {
                $arrQueryStringParams = $this->getQueryStringParams();

                // Validate required parameters are present
                if (empty($arrQueryStringParams['login']) || empty($arrQueryStringParams['token'])) {
                    $strErrorDesc = 'Missing required parameters: login and token must be provided in request body';
                    $strErrorHeader = 'HTTP/1.1 400 Bad Request';
                } else {
                    require API_ROOT_PATH . "/Model/AuthModel.php";
                    try {
                        $authModel = new AuthModel();
                        $arrUser = $authModel->getUserAuthByToken(
                            $arrQueryStringParams['login'],
                            $arrQueryStringParams['token']
                        );
                        if (array_key_exists("token", $arrUser)) {
                            $responseData = json_encode($arrUser);
                        } else {
                            $strErrorDesc = $arrUser['error'] . " (" . $arrUser['info'] . ")";
                            $strErrorHeader = 'HTTP/1.1 401 Unauthorized';
                        }
                    } catch (Error $e) {
                        error_log('[API] AuthController::authorizeTokenAction error: ' . $e->getMessage());
                        $strErrorDesc = 'An internal error occurred. Please contact support.';
                        $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
                    }
                }
            }

        } else {
            $strErrorDesc = 'Method '.$requestMethod.' not supported';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
            $arrErrorHeaders[] = 'Allow: POST';
        }

        // send output
        if (empty($strErrorDesc) === true) {
            $this->sendOutput(
                $responseData,
                ['Content-Type: application/json', 'HTTP/1.1 200 OK']
            );
        } else {
            $this->sendProblemFromHeader($strErrorHeader, $strErrorDesc, $arrErrorHeaders ?? []);
        }
    }

    /**
     * Revoke the current API session (POST /auth/logout).
     *
     * Requires a valid JWT (the router dispatches here after verification). The
     * api_sessions row matching the token's jti is flagged revoked — the token is
     * then rejected on every endpoint until it expires. Legacy tokens without a
     * session row fall back to wiping the single-row teampass_api session, which
     * invalidates the key_tempo for that user's legacy tokens.
     *
     * @param array $userData Verified JWT payload
     */
    public function logoutAction(array $userData): void
    {
        $request = symfonyRequest::createFromGlobals();
        $requestMethod = $request->getMethod();

        if (strtoupper($requestMethod) !== 'POST') {
            $this->sendProblem(405, 'Method not supported', ['Allow: POST']);
            return;
        }

        $userId = (int) ($userData['id'] ?? 0);
        $jti = (string) ($userData['jti'] ?? '');

        try {
            $revokedSession = false;
            if ($jti !== '') {
                DB::update(
                    prefixTable('api_sessions'),
                    ['revoked_at' => time()],
                    'jti = %s AND user_id = %i AND revoked_at IS NULL',
                    $jti,
                    $userId
                );
                $revokedSession = DB::affectedRows() > 0;
            }

            if ($revokedSession === false) {
                // Legacy token (no api_sessions row): invalidate the single-row session
                DB::update(
                    prefixTable('api'),
                    [
                        'session_key' => '',
                        'session_aes_key' => '',
                        'encrypted_private_key' => '',
                        'timestamp' => time(),
                    ],
                    'user_id = %i',
                    $userId
                );
            }

            $this->sendOutput(
                json_encode(['error' => false, 'message' => 'Session revoked']),
                ['Content-Type: application/json', 'HTTP/1.1 200 OK']
            );
        } catch (Error $e) {
            error_log('[API] AuthController::logoutAction error: ' . $e->getMessage());
            $this->sendProblem(500, 'An internal error occurred. Please contact support.');
        }
    }
}