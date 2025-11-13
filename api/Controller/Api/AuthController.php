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
 * @copyright 2009-2025 Teampass.net
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
            require API_ROOT_PATH . "/Model/AuthModel.php";
            try {
                $authModel = new AuthModel();

                // Leer el JSON del body del POST
                $body = $request->getContent();
                $data = json_decode($body, true);

                $login = $data['login'] ?? null;
                $password = $data['password'] ?? null;
                $apikey = $data['apikey'] ?? null;

                // Validación mínima para evitar null
                if (!$login || !$password || !$apikey) {
                    throw new Exception('Missing parameter');
                }

                $arrUser = $authModel->getUserAuth($login, $password, $apikey);

                if (array_key_exists("token", $arrUser)) {
                    $responseData = json_encode($arrUser);
                } else {
                    $strErrorDesc = $arrUser['error'] . " (" . $arrUser['info'] . ")";
                    $strErrorHeader = 'HTTP/1.1 401 Unauthorized';
                }
            } catch (Error|Exception $e) {
                $strErrorDesc = $e->getMessage().' Something went wrong! Please contact support.2';
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
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
