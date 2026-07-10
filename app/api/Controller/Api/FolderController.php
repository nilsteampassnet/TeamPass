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
 * @copyright 2009-2026 Teampass.net
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

        $arrErrorHeaders = [];
        $arrSuccessHeaders = [];

        if (strtoupper($requestMethod) === 'GET') {
            if (empty($userData['folders_list'])) {
                // Empty collection → 200 + [] (a 204 must not carry a body — RFC 9110)
                $responseData = json_encode([]);
                $arrSuccessHeaders[] = 'X-Total-Count: 0';
            } else {
                try {
                    $folderModel = new FolderModel();
                    $arrFolders = $folderModel->getFoldersInfo(explode(",", $userData['folders_list']));

                    // Pagination over the root-level entries (the tree is hierarchical)
                    $arrQueryStringParams = $this->getQueryStringParams();
                    $intTotal = count($arrFolders);
                    $intOffset = max(0, (int) ($arrQueryStringParams['offset'] ?? 0));
                    $intLimit = max(0, (int) ($arrQueryStringParams['limit'] ?? 0));
                    if ($intOffset > 0 || $intLimit > 0) {
                        $arrFolders = array_slice($arrFolders, $intOffset, $intLimit > 0 ? $intLimit : null);
                    }

                    $responseData = json_encode($arrFolders);
                    $arrSuccessHeaders[] = 'X-Total-Count: ' . $intTotal;
                } catch (Error $e) {
                    error_log('[API] FolderController::listFoldersAction error: ' . $e->getMessage());
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
                array_merge(['Content-Type: application/json', 'HTTP/1.1 200 OK'], $arrSuccessHeaders)
            );
        } else {
            $this->sendProblemFromHeader($strErrorHeader, $strErrorDesc, $arrErrorHeaders);
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

        $arrErrorHeaders = [];
        $intSuccessStatus = 200;

        if (strtoupper($requestMethod) === 'POST') {
            if (empty($userData['folders_list'])) {
                // No accessible folder → nothing can host the new folder
                $this->sendProblem(403, 'Access denied: no accessible folders');
                return;
            } else {
                // Is user allowed to create a folder
                // We check if allowed_to_create
                if ((int) $userData['allowed_to_create'] !== 1) {
                    $strErrorDesc = 'Access denied: insufficient permissions to create a folder';
                    $strErrorHeader = 'HTTP/1.1 403 Forbidden';
                } else {
                    // get parameters
                    $arrQueryStringParams = $this->getQueryStringParams();

                    // Optional convenience flag: create a private (personal) folder
                    // without knowing the personal root id. Never trusts a client
                    // personal_folder flag — the model derives it server-side.
                    $private = filter_var($arrQueryStringParams['private'] ?? false, FILTER_VALIDATE_BOOLEAN);

                    // Validate required parameters — avoids PHP warnings and gives a clear 400.
                    // For a private folder, parent_id and complexity are optional (§3.2).
                    $requiredParams = $private === true ? ['title'] : ['title', 'parent_id', 'complexity'];
                    $missingParams = [];
                    foreach ($requiredParams as $requiredParam) {
                        if (isset($arrQueryStringParams[$requiredParam]) === false
                            || $arrQueryStringParams[$requiredParam] === ''
                        ) {
                            $missingParams[] = $requiredParam;
                        }
                    }
                    if (count($missingParams) > 0) {
                        $this->sendProblem(400, 'Missing required parameters: ' . implode(', ', $missingParams));
                        return;
                    }

                    try {
                        $folderModel = new FolderModel();
                        $arrFolder = $folderModel->createFolder(
                            (string) $arrQueryStringParams['title'],
                            (int) ($arrQueryStringParams['parent_id'] ?? 0),
                            (int) ($arrQueryStringParams['complexity'] ?? 0),
                            (int) ($arrQueryStringParams['duration'] ?? 0),
                            (int) ($arrQueryStringParams['create_auth_without'] ?? 0),
                            (int) ($arrQueryStringParams['edit_auth_without'] ?? 0),
                            (string) ($arrQueryStringParams['icon'] ?? ''),
                            (string) ($arrQueryStringParams['icon_selected'] ?? ''),
                            (string) ($arrQueryStringParams['access_rights'] ?? ''),
                            (int) $userData['is_admin'],
                            (array) explode(',', $userData['folders_list']),
                            (int) $userData['is_manager'],
                            (int) $userData['user_can_create_root_folder'],
                            (int) $userData['user_can_manage_all_users'],
                            (int) $userData['id'],
                            (string) $userData['roles'],
                            $private,
                            (int) ($userData['pf_enabled'] ?? 0),
                            (string) $userData['username'],
                        );

                        if (($arrFolder['error'] ?? false) === true) {
                            $strErrorDesc = (string) ($arrFolder['error_message'] ?? 'Folder creation failed');
                            $strErrorHeader = (string) ($arrFolder['error_header'] ?? 'HTTP/1.1 422 Unprocessable Entity');
                        } else {
                            // 201 Created — no Location header: the API has no folder
                            // get-by-id endpoint yet (the body carries newId)
                            $responseData = json_encode($arrFolder);
                            $intSuccessStatus = 201;
                            $this->markApiFunctionalActivity($userData);
                        }
                    } catch (Error $e) {
                        error_log('[API] FolderController::createAction error: ' . $e->getMessage());
                        $strErrorDesc = 'An internal error occurred. Please contact support.';
                        $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
                    }
                }
            }
        } else {
            $strErrorDesc = 'Method not supported';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
            $arrErrorHeaders[] = 'Allow: POST';
        }

        // send output
        if (empty($strErrorDesc) === true) {
            $this->sendOutput(
                $responseData,
                ['Content-Type: application/json', $this->statusLine($intSuccessStatus)]
            );
        } else {
            $this->sendProblemFromHeader($strErrorHeader, $strErrorDesc, $arrErrorHeaders);
        }
    }
    //end createFolderAction()

    /**
     * Get list of writable folders
     *
     * @return void
     */
    public function writableFoldersAction(array $userData)
    {
        $request = symfonyRequest::createFromGlobals();
        $requestMethod = $request->getMethod();
        $strErrorDesc = $responseData = $strErrorHeader = '';

        if (strtoupper($requestMethod) === 'GET') {
            try {
                $folderAccessModel = new FolderAccessModel();
                // folders_list is already filtered by index.php — normalize to int[] only
                $userFolders = $folderAccessModel->normalizeFolderIds($userData['folders_list'] ?? []);

                $userId = (string) $userData['id'];
                $username = $userData['username'];
                $writableFolders = [];

                if (empty($userFolders) === false) {
                    $rows = DB::query(
                        'SELECT nt.id AS folder_id, nt.title, nt.nlevel, nt.parent_id
                        FROM ' . prefixTable('nested_tree') . ' AS nt
                        WHERE nt.id IN %li
                        GROUP BY nt.id, nt.title, nt.nlevel, nt.parent_id
                        ORDER BY nt.nlevel ASC, nt.title ASC',
                        $userFolders
                    );

                    foreach ($rows as $row) {
                        $folderId = (int) $row['folder_id'];
                        $writableFolders[] = [
                            'id' => $folderId,
                            'label' => $row['title'] === $userId ? $username : $row['title'],
                            'level' => (int) $row['nlevel'],
                            'parent_id' => (int) $row['parent_id'],
                            'first_position' => $row['title'] === $userId ? 1 : 0,
                            'is_readonly' => $folderAccessModel->isFolderReadOnlyForUser($folderId, (int) $userData['id']) ? 1 : 0,
                        ];
                    }
                }

                $responseData = json_encode($writableFolders);

            } catch (Error $e) {
                error_log('[API] FolderController::writableFoldersAction error: ' . $e->getMessage());
                $strErrorDesc = 'An internal error occurred. Please contact support.';
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
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
    //end writableFoldersAction()

    /**
     * Update an existing folder (partial update).
     *
     * PUT only. All access/business validation lives in FolderModel::updateFolder().
     *
     * @param array $userData User data from JWT token
     * @return void
     */
    public function updateAction(array $userData): void
    {
        $request = symfonyRequest::createFromGlobals();
        $requestMethod = $request->getMethod();
        $strErrorDesc = $strErrorHeader = $responseData = '';
        $arrErrorHeaders = [];

        if (strtoupper($requestMethod) === 'PUT') {
            if ((int) $userData['allowed_to_update'] !== 1) {
                $strErrorDesc = 'Access denied: insufficient permissions to update a folder';
                $strErrorHeader = 'HTTP/1.1 403 Forbidden';
            } else {
                $arrQueryStringParams = $this->getQueryStringParams();

                if (is_array($arrQueryStringParams) === false) {
                    $strErrorDesc = 'Data not consistent';
                    $strErrorHeader = 'HTTP/1.1 400 Bad Request';
                } elseif (isset($arrQueryStringParams['id']) === false || (int) $arrQueryStringParams['id'] <= 0) {
                    $strErrorDesc = 'Folder id is mandatory';
                    $strErrorHeader = 'HTTP/1.1 400 Bad Request';
                } else {
                    // At least one updatable field must be present
                    $updatableFields = ['title', 'parent_id', 'complexity', 'duration', 'create_auth_without', 'edit_auth_without', 'icon', 'icon_selected'];
                    $hasUpdateField = false;
                    foreach ($updatableFields as $field) {
                        if (array_key_exists($field, $arrQueryStringParams) === true) {
                            $hasUpdateField = true;
                            break;
                        }
                    }

                    if ($hasUpdateField === false) {
                        $strErrorDesc = 'Nothing to update (provide at least one of: ' . implode(', ', $updatableFields) . ')';
                        $strErrorHeader = 'HTTP/1.1 400 Bad Request';
                    } else {
                        try {
                            $folderModel = new FolderModel();
                            $ret = $folderModel->updateFolder($arrQueryStringParams, $userData);

                            if (($ret['error'] ?? false) === true) {
                                $strErrorDesc = (string) ($ret['error_message'] ?? 'Folder update failed');
                                $strErrorHeader = (string) ($ret['error_header'] ?? 'HTTP/1.1 422 Unprocessable Entity');
                            } else {
                                $responseData = json_encode($ret);
                                $this->markApiFunctionalActivity($userData);
                            }
                        } catch (Error $e) {
                            error_log('[API] FolderController::updateAction error: ' . $e->getMessage());
                            $strErrorDesc = 'An internal error occurred. Please contact support.';
                            $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
                        }
                    }
                }
            }
        } else {
            $strErrorDesc = 'Method not supported';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
            $arrErrorHeaders[] = 'Allow: PUT';
        }

        // send output
        if (empty($strErrorDesc) === true) {
            $this->sendOutput(
                $responseData,
                ['Content-Type: application/json', 'HTTP/1.1 200 OK']
            );
        } else {
            $this->sendProblemFromHeader($strErrorHeader, $strErrorDesc, $arrErrorHeaders);
        }
    }
    //end updateAction()

    /**
     * Soft-delete a folder and its descendants.
     *
     * DELETE only. All access/business validation lives in FolderModel::deleteFolder().
     *
     * @param array $userData User data from JWT token
     * @return void
     */
    public function deleteAction(array $userData): void
    {
        $request = symfonyRequest::createFromGlobals();
        $requestMethod = $request->getMethod();
        $strErrorDesc = $strErrorHeader = $responseData = '';
        $arrErrorHeaders = [];

        if (strtoupper($requestMethod) === 'DELETE') {
            if ((int) $userData['allowed_to_delete'] !== 1) {
                $strErrorDesc = 'Access denied: insufficient permissions to delete a folder';
                $strErrorHeader = 'HTTP/1.1 403 Forbidden';
            } else {
                $arrQueryStringParams = $this->getQueryStringParams();

                if (is_array($arrQueryStringParams) === false) {
                    $strErrorDesc = 'Data not consistent';
                    $strErrorHeader = 'HTTP/1.1 400 Bad Request';
                } elseif (isset($arrQueryStringParams['id']) === false || (int) $arrQueryStringParams['id'] <= 0) {
                    $strErrorDesc = 'Folder id is mandatory and must be greater than 0';
                    $strErrorHeader = 'HTTP/1.1 400 Bad Request';
                } else {
                    try {
                        $folderModel = new FolderModel();
                        $ret = $folderModel->deleteFolder((int) $arrQueryStringParams['id'], $userData);

                        if (($ret['error'] ?? false) === true) {
                            $strErrorDesc = (string) ($ret['error_message'] ?? 'Folder deletion failed');
                            $strErrorHeader = (string) ($ret['error_header'] ?? 'HTTP/1.1 500 Internal Server Error');
                        } else {
                            $responseData = json_encode($ret);
                            $this->markApiFunctionalActivity($userData);
                        }
                    } catch (Error $e) {
                        error_log('[API] FolderController::deleteAction error: ' . $e->getMessage());
                        $strErrorDesc = 'An internal error occurred. Please contact support.';
                        $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
                    }
                }
            }
        } else {
            $strErrorDesc = 'Method not supported';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
            $arrErrorHeaders[] = 'Allow: DELETE';
        }

        // send output
        if (empty($strErrorDesc) === true) {
            $this->sendOutput(
                $responseData,
                ['Content-Type: application/json', 'HTTP/1.1 200 OK']
            );
        } else {
            $this->sendProblemFromHeader($strErrorHeader, $strErrorDesc, $arrErrorHeaders);
        }
    }
    //end deleteAction()
}
