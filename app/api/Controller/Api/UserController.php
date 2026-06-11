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
 * @file      UserControler.php
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

class UserController extends BaseController
{
    /**
     * "/user/list" Endpoint - Get list of users (admin only)
     *
     * @param array $userData Decoded JWT payload.
     */
    public function listAction(array $userData): void
    {
        $request = symfonyRequest::createFromGlobals();
        $requestMethod = $request->getMethod();
        $strErrorDesc = $responseData = $strErrorHeader = '';
        $arrQueryStringParams = $this->getQueryStringParams();

        if (strtoupper($requestMethod) === 'GET') {
            // Restricted to administrators only — the user list exposes account metadata
            if ((int) ($userData['is_admin'] ?? 0) !== 1) {
                $strErrorDesc = 'Access denied: administrator rights required';
                $strErrorHeader = 'HTTP/1.1 403 Forbidden';
            } else {
                try {
                    $userModel = new UserModel();

                    $intLimit = 10;
                    if (isset($arrQueryStringParams['limit']) === true && empty($arrQueryStringParams['limit']) === false) {
                        $intLimit = (int) $arrQueryStringParams['limit'];
                    }

                    $arrUsers = $userModel->getUsers($intLimit);
                    $responseData = json_encode($arrUsers);
                } catch (Error $e) {
                    error_log('[API] UserController::listAction error: ' . $e->getMessage());
                    $strErrorDesc = 'An internal error occurred. Please contact support.';
                    $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
                }
            }
        } else {
            $strErrorDesc = 'Method not supported';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
            $arrErrorHeaders[] = 'Allow: GET';
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
}
