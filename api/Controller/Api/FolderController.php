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
 * @copyright 2009-2024 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

use Symfony\Component\HttpFoundation\Request AS symfonyRequest;

class FolderController extends BaseController
{

    /**
     * Get list of Folders
     *
     * @return void
     */
    public function listFoldersAction(array $userData)
    {
        $request = symfonyRequest::createFromGlobals();
        $requestMethod = $request->getMethod();
        $strErrorDesc = $responseData = $strErrorHeader = '';

        if (strtoupper($requestMethod) === 'GET') {
            if (empty($userData['folders_list'])) {
                $this->sendOutput("", ['HTTP/1.1 204 No Content']);
            } else {
                try {
                    $folderModel = new FolderModel();
                    $arrFolders = $folderModel->getFoldersInfo(explode(",", $userData['folders_list']));
                    $responseData = json_encode($arrFolders);
                } catch (Error $e) {
                    $strErrorDesc = $e->getMessage() . ' Something went wrong! Please contact support.3';
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

    /**
     * create new folder
     *
     * @return void
     */
    public function createAction(array $userData)
    {
        $request = symfonyRequest::createFromGlobals();
        $requestMethod = $request->getMethod();
        $strErrorDesc = $responseData = $strErrorHeader = '';

        if (strtoupper($requestMethod) === 'POST') {
            if (empty($userData['folders_list'])) {
                $this->sendOutput("", ['HTTP/1.1 204 No Content']);
            } else {
                // Is user allowed to create a folder
                // We check if allowed_to_create
                if ((int) $userData['allowed_to_create'] !== 1) {
                    $strErrorDesc = 'User is not allowed to create a folder';
                    $strErrorHeader = 'HTTP/1.1 401 Unauthorized';
                } else {
                    // get parameters
                    $arrQueryStringParams = $this->getQueryStringParams();
                    try {
                        $folderModel = new FolderModel();
                        $arrFolder = $folderModel->createFolder(
                            (string) $arrQueryStringParams['title'],
                            (int) $arrQueryStringParams['parent_id'],
                            (int) $arrQueryStringParams['complexity'],
                            (int) $arrQueryStringParams['duration'],
                            (int) $arrQueryStringParams['create_auth_without'],
                            (int) $arrQueryStringParams['edit_auth_without'],
                            (string) $arrQueryStringParams['icon'],
                            (string) $arrQueryStringParams['icon_selected'],
                            (string) $arrQueryStringParams['access_rights'],
                            (int) $userData['is_admin'],
                            (array) explode(',', $userData['folders_list']),
                            (int) $userData['is_manager'],
                            (int) $userData['user_can_create_root_folder'],
                            (int) $userData['user_can_manage_all_users'],
                            (int) $userData['id'],
                            (string) $userData['roles'],
                        );
                        
                        $responseData = json_encode($arrFolder);
                    } catch (Error $e) {
                        $strErrorDesc = $e->getMessage() . ' Something went wrong! Please contact support.1';
                        $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
                    }
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
    //end createFolderAction() 
}
