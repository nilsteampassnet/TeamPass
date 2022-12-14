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
    public function inFoldersAction(array $userData): void
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

            // SQL where clause with folders list
            if (isset($arrQueryStringParams['folders']) === true) {
                // convert the folders to an array
                $arrQueryStringParams['folders'] = explode(',', str_replace( array('[',']') , ''  , $arrQueryStringParams['folders']));

                // ensure to only use the intersection
                $foldersList = implode(',', array_intersect($arrQueryStringParams['folders'], $userData['folders_list']));

                if (!empty($foldersList)) {
                    // build sql where clause
                    $sqlExtra = ' WHERE id_tree IN ('.$foldersList.')';
                } else {
                    // Send error
                    $this->sendOutput(
                        json_encode(['error' => 'Folders are mandatory']),
                        ['Content-Type: application/json', 'HTTP/1.1 401 Expected parameters not provided']
                    );
                }
            } else {
                // Send error
                $this->sendOutput(
                    json_encode(['error' => 'Folders are mandatory']),
                    ['Content-Type: application/json', 'HTTP/1.1 401 Expected parameters not provided']
                );
            }

            // SQL LIMIT
            $intLimit = 0;
            if (isset($arrQueryStringParams['limit']) === true) {
                $intLimit = $arrQueryStringParams['limit'];
            }

            // send query
            try {
                $itemModel = new ItemModel();

                $arrItems = $itemModel->getItems($sqlExtra, $intLimit, $userData['private_key'], $userData['id']);

                if (!empty($arrItems)) {
                    $responseData = json_encode($arrItems);
                } else {
                    $strErrorDesc = 'No content for this label';
                    $strErrorHeader = 'HTTP/1.1 204 No Content';
                }
            } catch (Error $e) {
                $strErrorDesc = $e->getMessage().'. Something went wrong! Please contact support.';
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

    /**
     * Manage case Add
     *
     * @param array $userData
     */
    public function addAction(array $userData)
    {
        $superGlobal = new protect\SuperGlobal\SuperGlobal();
        $strErrorDesc = '';
        $requestMethod = $superGlobal->get('REQUEST_METHOD', 'SERVER');

        if (strtoupper($requestMethod) === 'POST') {
            if (empty($userData['folders_list']) === false) {
                $userData['folders_list'] = explode(',', $userData['folders_list']);
            } else {
                $userData['folders_list'] = [];
            }

            $data = json_decode(file_get_contents("php://input"));

            if (in_array($data->folderId, $userData['folders_list'])) {
                // send query
                try {
                    $itemModel = new ItemModel();

                    $itemModel->addItem($data->folderId, $data->userName, $data->hostname, $data->password);
                } catch (Error $e) {
                    $strErrorDesc = $e->getMessage().'. Something went wrong! Please contact support.';
                    $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
                }
            } else {
                $strErrorDesc = 'Folders are mandatory';
                $strErrorHeader = 'HTTP/1.1 401 Expected parameters not provided';
            }
        } else {
            $strErrorDesc = 'Method not supported';
            $strErrorHeader = 'HTTP/1.1 422 Unprocessable Entity';
        }

        // send output
        if (empty($strErrorDesc) === true) {
            $this->sendOutput(
                "",
                ['Content-Type: application/json', 'HTTP/1.1 201 Created']
            );

            //$this->sendOutput(['HTTP/1.1 201 Created']);
        } else {
            $this->sendOutput(
                json_encode(['error' => $strErrorDesc]),
                ['Content-Type: application/json', $strErrorHeader]
            );
        }
    }
    //end addAction()

    /**
     * Manage case get item by label
     *
     * @param array $userData
     */
    public function getAction(array $userData)
    {
        $superGlobal = new protect\SuperGlobal\SuperGlobal();
        $strErrorDesc = '';
        $requestMethod = $superGlobal->get('REQUEST_METHOD', 'SERVER');

        // get parameters
        $arrQueryStringParams = $this->getQueryStringParams();

        if (strtoupper($requestMethod) === 'GET') {
            if (empty($userData['folders_list']) === false) {
                $userData['folders_list'] = explode(',', $userData['folders_list']);
            } else {
                $userData['folders_list'] = [];
            }

            if (isset($arrQueryStringParams['id']) === true && empty($arrQueryStringParams['id']) === false ) {
                try {
                    $itemModel = new ItemModel();

                    $item = $itemModel->getItem($arrQueryStringParams['id'], $userData['private_key'], $userData['id'], $userData['folders_list']);

                    if (!empty($item)) {
                        $responseData = json_encode($item);
                    } else {
                        $strErrorDesc = 'No content for this label';
                        $strErrorHeader = 'HTTP/1.1 204 No Content';
                    }
                } catch (Error $e) {
                    $strErrorDesc = $e->getMessage().'. Something went wrong! Please contact support.';
                    $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
                }
            } else if (isset($arrQueryStringParams['label']) === true && empty($arrQueryStringParams['label']) === false ) {
                try {
                    $itemModel = new ItemModel();

                    $item = $itemModel->getItemByLabel($arrQueryStringParams['label'], $userData['private_key'], $userData['id'], $userData['folders_list']);

                    if (!empty($item)) {
                        $responseData = json_encode($item);
                    } else {
                        $strErrorDesc = 'No content for this label';
                        $strErrorHeader = 'HTTP/1.1 204 No Content';
                    }
                } catch (Error $e) {
                    $strErrorDesc = $e->getMessage().'. Something went wrong! Please contact support.';
                    $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
                }
            } else {
                $strErrorDesc = 'Id are mandatory';
                $strErrorHeader = 'HTTP/1.1 204 No Content';
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
    //end addAction()
}
