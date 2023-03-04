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
 * @file      FolderControler.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */


class FolderController extends BaseController
{

    /**
     * Get list of Folders
     *
     * @return void
     */
    public function listFoldersAction(array $userData)
    {
        $superGlobal = new protect\SuperGlobal\SuperGlobal();
        $strErrorDesc = '';
        $requestMethod = $superGlobal->get('REQUEST_METHOD', 'SERVER');

        // get parameters
        //$arrQueryStringParams = $this->getQueryStringParams();

        if (strtoupper($requestMethod) === 'GET') {
            if (empty($userData['folders_list'])) {
                $this->sendOutput("", ['HTTP/1.1 204 No Content']);
            } else {
                try {
                    $folderModel = new FolderModel();
                    $arrFolders = $folderModel->getFoldersInfo(explode(",", $userData['folders_list']));
                    $responseData = json_encode($arrFolders);
                } catch (Error $e) {
                    $strErrorDesc = $e->getMessage() . ' Something went wrong! Please contact support.';
                    $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
                }
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
