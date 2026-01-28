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
                        $strErrorDesc = $e->getMessage().' Something went wrong! Please contact support.2';
                        $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
                    }
                }
            }

        } else {
            $strErrorDesc = 'Method '.$requestMethod.' not supported';
            $strErrorHeader = 'HTTP/1.1 422 Unprocessable Entity';
        }

        // send output
        if (empty($strErrorDesc) === true) {
            $this->sendOutput(
                $responseData,
                ['Content-Type: application/json', 'HTTP/1.1 200 OK']
            );
        } else {
            $this->sendOutput(
                json_encode(['error' => $strErrorDesc]), 
                ['Content-Type: application/json', $strErrorHeader]
            );
        }
    }
}