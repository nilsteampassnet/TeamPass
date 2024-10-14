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
 * @copyright 2009-2024 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

use Symfony\Component\HttpFoundation\Request AS symfonyRequest;

class ItemController extends BaseController
{


    /**
     * Manage case inFolder - get items inside an array of folders
     *
     * @param array $userData
     */
    public function inFoldersAction(array $userData): void
    {
        $request = symfonyRequest::createFromGlobals();
        $requestMethod = $request->getMethod();
        $strErrorDesc = $responseData = $strErrorHeader = '';

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

                // build sql where clause
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
                $strErrorDesc = $e->getMessage().'. Something went wrong! Please contact support.4';
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

    private function checkNewItemData(array $arrQueryStringParams, array $userData): array
    {
        if (isset($arrQueryStringParams['label']) === true
            && isset($arrQueryStringParams['folder_id']) === true
            && isset($arrQueryStringParams['password']) === true
            && isset($arrQueryStringParams['login']) === true
            && isset($arrQueryStringParams['email']) === true
            && isset($arrQueryStringParams['url']) === true
            && isset($arrQueryStringParams['tags']) === true
            && isset($arrQueryStringParams['anyone_can_modify']) === true
        ) {
            //
            if (in_array($arrQueryStringParams['folder_id'], $userData['folders_list']) === false) {
                return [
                    'error' => true,
                    'strErrorDesc' => 'User is not allowed in this folder',
                    'strErrorHeader' => 'HTTP/1.1 401 Unauthorized',
                ];
            } else if (empty($arrQueryStringParams['label']) === true) {
                return [
                    'error' => true,
                    'strErrorDesc' => 'Label is mandatory',
                    'strErrorHeader' => 'HTTP/1.1 401 Expected parameters not provided',
                ];
            } else {
                return [
                    'error' => false,
                ];
            }
        }

        return [
            'error' => true,
            'strErrorDesc' => 'All fields have to be provided even if empty (refer to documentation).',
            'strErrorHeader' => 'HTTP/1.1 401 Expected parameters not provided',
        ];
    }

    /**
     * Manage case Add
     *
     * @param array $userData
     */
    public function createAction(array $userData)
    {
        $request = symfonyRequest::createFromGlobals();
        $requestMethod = $request->getMethod();
        $strErrorDesc = $strErrorHeader = $responseData = '';

        if (strtoupper($requestMethod) === 'POST') {
            // Is user allowed to create a folder
            // We check if allowed_to_create
            if ((int) $userData['allowed_to_create'] !== 1) {
                $strErrorDesc = 'User is not allowed to create an item';
                $strErrorHeader = 'HTTP/1.1 401 Unauthorized';
            } else {
                if (empty($userData['folders_list']) === false) {
                    $userData['folders_list'] = explode(',', $userData['folders_list']);
                } else {
                    $userData['folders_list'] = [];
                }

                // get parameters
                $arrQueryStringParams = $this->getQueryStringParams();

                // Check that the parameters are indeed an array before using them
                if (is_array($arrQueryStringParams)) {
                    // check parameters
                    $arrCheck = $this->checkNewItemData($arrQueryStringParams, $userData);

                    if ($arrCheck['error'] === true) {
                        $strErrorDesc = $arrCheck['strErrorDesc'];
                        $strErrorHeader = $arrCheck['strErrorHeader'];
                    } else {
                        // launch
                        $itemModel = new ItemModel();
                        $ret = $itemModel->addItem(
                            (int) $arrQueryStringParams['folder_id'],
                            (string) $arrQueryStringParams['label'],
                            (string) $arrQueryStringParams['password'],
                            (string) $arrQueryStringParams['description'],
                            (string) $arrQueryStringParams['login'],
                            (string) $arrQueryStringParams['email'],
                            (string) $arrQueryStringParams['url'],
                            (string) $arrQueryStringParams['tags'],
                            (string) $arrQueryStringParams['anyone_can_modify'],
                            (string) $arrQueryStringParams['icon'],
                            (int) $userData['id'],
                            (string) $userData['username'],
                        );
                        $responseData = json_encode($ret);
                    }
                
                } else {
                    // Gérer le cas où les paramètres ne sont pas un tableau
                    $strErrorDesc = 'Data not consistent';
                    $strErrorHeader = 'Expected array, received ' . gettype($arrQueryStringParams);
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
    //end addAction()


    /**
     * Manage case get - get an item
     *
     * @param array $userData
     */
    public function getAction(array $userData): void
    {
        
        $request = symfonyRequest::createFromGlobals();
        $requestMethod = $request->getMethod();
        $strErrorDesc = '';
        $sqlExtra = '';
        $responseData = '';
        $strErrorHeader = '';
        $sql_constraint = ' AND (i.id_tree IN ('.$userData['folders_list'].')';
        if (!empty($userData['restricted_items_list'])) {
            $sql_constraint .= 'OR i.id IN ('.$userData['restricted_items_list'].')';
        }
        $sql_constraint .= ')';

        // get parameters
        $arrQueryStringParams = $this->getQueryStringParams();

        if (strtoupper($requestMethod) === 'GET') {
            // SQL where clause with item id
            if (isset($arrQueryStringParams['id']) === true) {
                // build sql where clause by ID
                $sqlExtra = ' WHERE i.id = '.$arrQueryStringParams['id'] . $sql_constraint;
            } else if (isset($arrQueryStringParams['label']) === true) {
                // build sql where clause by LABEL
                $sqlExtra = ' WHERE i.label '.(isset($arrQueryStringParams['like']) === true && (int) $arrQueryStringParams['like'] === 1 ? ' LIKE '.$arrQueryStringParams['label'] : ' = '.$arrQueryStringParams['label']) . $sql_constraint;
            } else if (isset($arrQueryStringParams['description']) === true) {
                // build sql where clause by LABEL
                $sqlExtra = ' WHERE i.description '.(isset($arrQueryStringParams['like']) === true && (int) $arrQueryStringParams['like'] === 1 ? ' LIKE '.$arrQueryStringParams['description'] : ' = '.$arrQueryStringParams['description']).$sql_constraint;
            } else {
                // Send error
                $this->sendOutput(
                    json_encode(['error' => 'Item id, label or description is mandatory']),
                    ['Content-Type: application/json', 'HTTP/1.1 401 Expected parameters not provided']
                );
            }

            // send query
            try {
                $itemModel = new ItemModel();

                $arrItems = $itemModel->getItems($sqlExtra, 0, $userData['private_key'], $userData['id']);
                $responseData = json_encode($arrItems);
            } catch (Error $e) {
                $strErrorDesc = $e->getMessage().'. Something went wrong! Please contact support.6';
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
    //end getAction() 
}