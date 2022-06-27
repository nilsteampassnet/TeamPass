<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass API
 *
 * @file      FolderControler.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2022 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */


class FolderController extends BaseController
{

    /**
     * Get list of users
     *
     * @return void
     */
    public function listInFoldersAction()
    {
        $superGlobal = new protect\SuperGlobal\SuperGlobal();
        $strErrorDesc = '';
        $requestMethod = $superGlobal->get('REQUEST_METHOD', 'SERVER');

        // get parameters
        $arrQueryStringParams = $this->getQueryStringParams();

        if (strtoupper($requestMethod) === 'GET') {
            try {
                $itemModel = new ItemModel();

                $intLimit = 0;
                if (isset($arrQueryStringParams['limit']) === true && empty($arrQueryStringParams['limit']) === false ) {
                    $intLimit = $arrQueryStringParams['limit'];
                }

                $arrItems = $itemModel->getItems($intLimit);
                $responseData = json_encode($arrItems);
            } catch (Error $e) {
                $strErrorDesc = $e->getMessage().'Something went wrong! Please contact support.';
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
            }
        } else {
            $strErrorDesc = 'Method not supported';
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
    //end listInFoldersAction() 
}
