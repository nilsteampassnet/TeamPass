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
 * @file      ItemControler.php
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
class ItemController extends BaseController
{


    /**
     * Manage case inFolder
     *
     * @param array $userData
     * @return string
     */
    public function inFoldersAction(array $userData): string
    {
        $superGlobal = new protect\SuperGlobal\SuperGlobal();
        $strErrorDesc = '';
        $requestMethod = $superGlobal->get('REQUEST_METHOD', 'SERVER');

        // get parameters
        $arrQueryStringParams = $this->getQueryStringParams();

        if (strtoupper($requestMethod) === 'GET') {
            // define WHERE clause
            $sqlExtra = '';
            if (empty($userData['folders_list']) === false) {
                $userData['folders_list'] = explode(',', $userData['folders_list']);
            } else {
                $userData['folders_list'] = [];
            }

            $foldersList = '';
            if (isset($arrQueryStringParams['folders']) && $arrQueryStringParams['folders']) {
                // convert the folders to an array
                $arrQueryStringParams['folders'] = explode(',', str_replace( array('[',']') , ''  , $arrQueryStringParams['folders']));
                // ensure to only use the intersection
                $foldersList = implode(',', array_intersect($arrQueryStringParams['folders'], $userData['folders_list']));
                // build sql where clause
                $sqlExtra = ' WHERE id_tree IN ('.$foldersList.')';
            }
            if (empty($foldersList) === true) {
                $strErrorDesc = 'Folders are mandatory';
                $strErrorHeader = 'HTTP/1.1 401 Expected parameters not provided';
            }

            // send query
            try {
                $itemModel = new ItemModel();

                // SQL LIMIT
                $intLimit = 0;
                if (isset($arrQueryStringParams['limit']) && $arrQueryStringParams['limit']) {
                    $intLimit = $arrQueryStringParams['limit'];
                }

                $arrItems = $itemModel->getItems($sqlExtra, $intLimit, $userData['private_key'], $userData['id']);
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
    //end InFoldersAction() 
}
