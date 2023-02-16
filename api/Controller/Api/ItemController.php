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
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */
class ItemController extends BaseController
{


    /**
     * Manage case inFolder - get items inside an array of folders
     *
     * @param array $userData
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

                // build sql where clause
                $sqlExtra = ' WHERE id_tree IN ('.$foldersList.')';
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
                $responseData = json_encode($arrItems);
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
     * Manage case get - get an item
     *
     * @param array $userData
     */
    public function getAction(array $userData): void
    {
        $superGlobal = new protect\SuperGlobal\SuperGlobal();
        $strErrorDesc = '';
        $sqlExtra = '';
        $responseData = '';
        $strErrorHeader = '';
        $requestMethod = $superGlobal->get('REQUEST_METHOD', 'SERVER');
        $sql_constraint = ' AND (i.id_tree IN ('.$userData['folders_list'].') OR i.id IN ('.$userData['restricted_items_list'].'))';

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
    //end getAction() 
}