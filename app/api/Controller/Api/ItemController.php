<?php
declare(strict_types=1);

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
 * @copyright 2009-2026 Teampass.net
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
     * Get user's private key from database
     *
     * Retrieves the user's private key by decrypting it from the database.
     * The AES key used for decryption (session_aes_key) is stored server-side in
     * teampass_api and is never transmitted in the JWT.
     *
     * @param array $userData User data from JWT token (must include id and key_tempo)
     * @return string|null Decrypted private key or null if not found/invalid session
     */
    private function getUserPrivateKey(array $userData): ?string
    {
        include_once API_ROOT_PATH . '/inc/jwt_utils.php';

        $userKeys = get_user_keys(
            (int) $userData['id'],
            (string) $userData['key_tempo'],
            isset($userData['jti']) ? (string) $userData['jti'] : null
        );

        if ($userKeys === null) {
            return null;
        }

        return $userKeys['private_key'];
    }


    /**
     * Normalize the optional custom-fields payload.
     *
     * Accepts an array (JSON body) or a JSON-encoded string (form-data / query string)
     * and returns a clean list of [ 'id' => int, 'value' => string ] entries. Entries
     * without an 'id' are dropped.
     *
     * @param mixed $raw Raw fields input
     * @return array<int, array{id:int, value:string}>
     */
    private function normalizeFields($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $field) {
            if (is_array($field) && isset($field['id'])) {
                $out[] = [
                    'id' => (int) $field['id'],
                    'value' => (string) ($field['value'] ?? ''),
                ];
            }
        }

        return $out;
    }


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
        $arrErrorHeaders = [];
        $arrSuccessHeaders = [];

        // get parameters
        $arrQueryStringParams = $this->getQueryStringParams();

        if (strtoupper($requestMethod) === 'GET') {
            // define WHERE clause
            $sqlExtra = 'WHERE i.deleted_at IS NULL';
            if (empty($userData['folders_list']) === false) {
                $userData['folders_list'] = explode(',', $userData['folders_list']);
            } else {
                $userData['folders_list'] = [];
            }
            $folderAccessModel = new FolderAccessModel();
            // folders_list is already filtered by index.php — normalize to int[] only
            $userData['folders_list'] = $folderAccessModel->normalizeFolderIds($userData['folders_list']);

            // SQL where clause with folders list
            if (isset($arrQueryStringParams['folders']) === true) {
                // convert the folders to an array
                $arrQueryStringParams['folders'] = explode(',', str_replace( array('[',']') , ''  , $arrQueryStringParams['folders']));

                // ensure to only use the intersection, sanitized as strict integers
                $foldersList = implode(',', array_filter(array_map('intval', array_intersect($arrQueryStringParams['folders'], $userData['folders_list']))));

                // build sql where clause
                if (!empty($foldersList)) {
                    // build sql where clause
                    $sqlExtra .= ' AND i.id_tree IN (' . $foldersList . ')';
                } else {
                    // Send error
                    $this->sendProblem(400, 'Folders are mandatory');
                    return;
                }
            } else {
                // Send error
                $this->sendProblem(400, 'Folders are mandatory');
                return;
            }

            // SQL LIMIT (hard cap at 500 to prevent resource exhaustion)
            $intLimit = isset($arrQueryStringParams['limit'])
                ? min(max(0, (int) $arrQueryStringParams['limit']), 500)
                : 0;
            // SQL OFFSET — pagination requires a LIMIT; default it when only offset is given
            $intOffset = isset($arrQueryStringParams['offset'])
                ? max(0, (int) $arrQueryStringParams['offset'])
                : 0;
            if ($intOffset > 0 && $intLimit === 0) {
                $intLimit = 50;
            }

            // send query
            try {
                $itemModel = new ItemModel();

                // Get user's private key from database
                $userPrivateKey = $this->getUserPrivateKey($userData);
                if ($userPrivateKey === null) {
                    $strErrorDesc = 'Invalid session or user keys not found';
                    $strErrorHeader = 'HTTP/1.1 401 Unauthorized';
                } else {
                    $arrItems = $itemModel->getItems($sqlExtra, $intLimit, $userPrivateKey, $userData['id'], false, $intOffset);
                    // Empty collection → 200 + [] (a 204 must not carry a body — RFC 9110)
                    $responseData = json_encode($arrItems);
                    $arrSuccessHeaders[] = 'X-Total-Count: ' . $itemModel->countItems($sqlExtra);
                }
            } catch (Error $e) {
                error_log('ItemController::inFoldersAction error: ' . $e->getMessage());
                $strErrorDesc = 'Something went wrong. Please contact support.';
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
                array_merge(['Content-Type: application/json', 'HTTP/1.1 200 OK'], $arrSuccessHeaders)
            );
        } else {
            $this->sendProblemFromHeader($strErrorHeader, $strErrorDesc, $arrErrorHeaders);
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
            $folderAccessModel = new FolderAccessModel();
            $folderId = (int) $arrQueryStringParams['folder_id'];

            if ($folderAccessModel->canUseFolder($userData, $folderId, true) === false) {
                return [
                    'error' => true,
                    'strErrorDesc' => 'User is not allowed in this folder',
                    'strErrorHeader' => 'HTTP/1.1 403 Forbidden',
                ];
            } elseif ($folderAccessModel->isFolderReadOnlyForUser($folderId, (int) ($userData['id'] ?? 0))) {
                return [
                    'error' => true,
                    'strErrorDesc' => 'Access denied: folder is read-only',
                    'strErrorHeader' => 'HTTP/1.1 403 Forbidden',
                ];
            } else if (empty($arrQueryStringParams['label']) === true) {
                return [
                    'error' => true,
                    'strErrorDesc' => 'Label is mandatory',
                    'strErrorHeader' => 'HTTP/1.1 400 Bad Request',
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
            'strErrorHeader' => 'HTTP/1.1 400 Bad Request',
        ];
    }

    /**
     * Manage case Add
     *
     * @param array $userData User data from JWT token
     * @return void
     */
    public function createAction(array $userData)
    {
        $request = symfonyRequest::createFromGlobals();
        $requestMethod = $request->getMethod();
        $strErrorDesc = $strErrorHeader = $responseData = '';
        $arrErrorHeaders = [];
        $arrSuccessHeaders = [];
        $intSuccessStatus = 200;

        if (strtoupper($requestMethod) === 'POST') {
            // Is user allowed to create a folder
            // We check if allowed_to_create
            if ((int) $userData['allowed_to_create'] !== 1) {
                $strErrorDesc = 'User is not allowed to create an item';
                $strErrorHeader = 'HTTP/1.1 403 Forbidden';
            } else {
                if (empty($userData['folders_list']) === false) {
                    $userData['folders_list'] = explode(',', $userData['folders_list']);
                } else {
                    $userData['folders_list'] = [];
                }
                $folderAccessModel = new FolderAccessModel();
                // folders_list is already filtered by index.php — normalize to int[] only
                $userData['folders_list'] = $folderAccessModel->normalizeFolderIds($userData['folders_list']);

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
                        // Prepare array of item parameters
                        $arrItemParams = [
                            'folder_id' => (int) $arrQueryStringParams['folder_id'],
                            'label' => (string) $arrQueryStringParams['label'],
                            'password' => (string) $arrQueryStringParams['password'],
                            'description' => (string) ($arrQueryStringParams['description'] ?? ''),
                            'login' => (string) $arrQueryStringParams['login'],
                            'email' => (string) ($arrQueryStringParams['email'] ?? ''),
                            'url' => (string) ($arrQueryStringParams['url'] ?? ''),
                            'tags' => (string) ($arrQueryStringParams['tags'] ?? ''),
                            'anyone_can_modify' => (int) ($arrQueryStringParams['anyone_can_modify'] ?? 0),
                            'icon' => (string) ($arrQueryStringParams['icon'] ?? ''),
                            'id' => (int) $userData['id'],
                            'username' => (string) $userData['username'],
                            'totp' => (string) ($arrQueryStringParams['totp'] ?? ''),
                            'fields' => $this->normalizeFields($arrQueryStringParams['fields'] ?? []),
                        ];

                        // launch
                        $itemModel = new ItemModel();
                        $ret = $itemModel->addItem(
                            $arrItemParams
                        );
                        if (($ret['error'] ?? false) === true) {
                            $strErrorDesc = (string) ($ret['error_message'] ?? 'Item creation failed');
                            $strErrorHeader = (string) ($ret['error_header'] ?? 'HTTP/1.1 422 Unprocessable Entity');
                        } else {
                            // 201 Created + Location of the new resource (path-absolute reference)
                            $responseData = json_encode($ret);
                            $intSuccessStatus = 201;
                            $requestPath = (string) parse_url($request->getRequestUri(), PHP_URL_PATH);
                            $arrSuccessHeaders[] = 'Location: '
                                . preg_replace('/create$/', 'get', $requestPath)
                                . '?id=' . (int) $ret['newId'];
                        }
                    }

                } else {
                    $strErrorDesc = 'Data not consistent';
                    $strErrorHeader = 'HTTP/1.1 400 Bad Request';
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
                array_merge(['Content-Type: application/json', $this->statusLine($intSuccessStatus)], $arrSuccessHeaders)
            );
        } else {
            $this->sendProblemFromHeader($strErrorHeader, $strErrorDesc, $arrErrorHeaders);
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
        $showItem = false;
        $sqlExtra = 'WHERE i.deleted_at IS NULL';
        $sqlLimit = 0;
        $sqlOffset = 0;
        $responseData = '';
        $strErrorHeader = '';
        $arrErrorHeaders = [];
        $arrSuccessHeaders = [];
        // Sanitize folder/item IDs — already filtered by index.php, normalize to strict integers
        $folderAccessModel = new FolderAccessModel();
        $safeFolders = implode(
            ',',
            $folderAccessModel->normalizeFolderIds($userData['folders_list'] ?? '')
        ) ?: '0';
        $sql_constraint = ' AND (i.id_tree IN (' . $safeFolders . ')';
        if (!empty($userData['restricted_items_list'])) {
            $safeRestricted = implode(',', array_filter(array_map('intval', explode(',', (string) $userData['restricted_items_list'])))) ?: '0';
            $sql_constraint .= ' OR i.id IN (' . $safeRestricted . ')';
        }
        $sql_constraint .= ')' . $folderAccessModel->getItemFolderSqlConstraint('i.id_tree', (int) $userData['id']);

        // get parameters
        $arrQueryStringParams = $this->getQueryStringParams();

        if (strtoupper($requestMethod) === 'GET') {
            // Values bound to the MeekroDB placeholders embedded in $sqlExtra
            $sqlParams = [];
            // SQL where clause with item id
            if (isset($arrQueryStringParams['id']) === true) {
                // build sql where clause by ID
                $sqlExtra .= ' AND i.id = ' . (int) $arrQueryStringParams['id'] . $sql_constraint;
                $showItem = true;
            } else if (isset($arrQueryStringParams['label']) === true) {
                // build sql where clause by LABEL (value bound via placeholder)
                $isLikeLabel = isset($arrQueryStringParams['like']) && (int) $arrQueryStringParams['like'] === 1;
                if ($isLikeLabel) {
                    $likeVal = str_replace(['%', '_'], ['\\%', '\\_'], $arrQueryStringParams['label']);
                    $sqlParams[] = '%' . $likeVal . '%';
                } else {
                    $sqlParams[] = (string) $arrQueryStringParams['label'];
                }
                $sqlExtra .= ' AND i.label ' . ($isLikeLabel ? 'LIKE' : '=') . ' %s' . $sql_constraint;
                $sqlLimit = isset($arrQueryStringParams['limit']) && (int) $arrQueryStringParams['limit'] > 0
                    ? min((int) $arrQueryStringParams['limit'], 500)
                    : 50;
                $sqlOffset = max(0, (int) ($arrQueryStringParams['offset'] ?? 0));
            } else if (isset($arrQueryStringParams['description']) === true) {
                // build sql where clause by DESCRIPTION (value bound via placeholder)
                $isLikeDesc = isset($arrQueryStringParams['like']) && (int) $arrQueryStringParams['like'] === 1;
                if ($isLikeDesc) {
                    $likeVal = str_replace(['%', '_'], ['\\%', '\\_'], $arrQueryStringParams['description']);
                    $sqlParams[] = '%' . $likeVal . '%';
                } else {
                    $sqlParams[] = (string) $arrQueryStringParams['description'];
                }
                $sqlExtra .= ' AND i.description ' . ($isLikeDesc ? 'LIKE' : '=') . ' %s' . $sql_constraint;
                $sqlLimit = isset($arrQueryStringParams['limit']) && (int) $arrQueryStringParams['limit'] > 0
                    ? min((int) $arrQueryStringParams['limit'], 500)
                    : 50;
                $sqlOffset = max(0, (int) ($arrQueryStringParams['offset'] ?? 0));
            } else {
                // Send error
                $this->sendProblem(400, 'Item id, label or description is mandatory');
                return;
            }

            // send query
            try {
                $itemModel = new ItemModel();

                // Get user's private key from database
                $userPrivateKey = $this->getUserPrivateKey($userData);
                if ($userPrivateKey === null) {
                    $strErrorDesc = 'Invalid session or user keys not found';
                    $strErrorHeader = 'HTTP/1.1 401 Unauthorized';
                } else {
                    $arrItems = $itemModel->getItems($sqlExtra, $sqlLimit, $userPrivateKey, $userData['id'], $showItem, $sqlOffset, $sqlParams);
                    $responseData = json_encode($arrItems);
                    // Pagination contract only applies to searches (a get-by-id is a single resource)
                    if ($showItem === false) {
                        $arrSuccessHeaders[] = 'X-Total-Count: ' . $itemModel->countItems($sqlExtra, $sqlParams);
                    }
                }
            } catch (Error $e) {
                error_log('ItemController::getAction error: ' . $e->getMessage());
                $strErrorDesc = 'Something went wrong. Please contact support.';
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
                array_merge(['Content-Type: application/json', 'HTTP/1.1 200 OK'], $arrSuccessHeaders)
            );
        } else {
            $this->sendProblemFromHeader($strErrorHeader, $strErrorDesc, $arrErrorHeaders);
        }
    }
    //end getAction()

    /**
     * Find items by URL
     * Searches for items matching a specific URL
     *
     * @param array $userData User data from JWT token
     * @return void
     */
    public function findByUrlAction(array $userData): void
    {
        $request = symfonyRequest::createFromGlobals();
        $requestMethod = $request->getMethod();
        $strErrorDesc = $responseData = $strErrorHeader = '';

        // Get parameters
        $arrQueryStringParams = $this->getQueryStringParams();

        if (strtoupper($requestMethod) === 'GET') {
            // Check if URL parameter is provided
            if (isset($arrQueryStringParams['url']) === false || empty($arrQueryStringParams['url']) === true) {
                $this->sendProblem(400, 'URL parameter is mandatory');
                return;
            }

            // Prepare user's accessible folders
            /*if (empty($userData['folders_list']) === false) {
                $userData['folders_list'] = explode(',', $userData['folders_list']);
            } else {
                $userData['folders_list'] = [];
            }*/

            // Build SQL constraint for accessible folders — already filtered by index.php, normalize to strict integers
            $folderAccessModel = new FolderAccessModel();
            $safeFoldersFbu = implode(
                ',',
                $folderAccessModel->normalizeFolderIds($userData['folders_list'] ?? '')
            ) ?: '0';
            $sql_constraint = ' AND (i.id_tree IN (' . $safeFoldersFbu . ')';
            if (!empty($userData['restricted_items_list'])) {
                $safeRestrictedFbu = implode(',', array_filter(array_map('intval', explode(',', (string) $userData['restricted_items_list'])))) ?: '0';
                $sql_constraint .= ' OR i.id IN (' . $safeRestrictedFbu . ')';
            }
            $sql_constraint .= ')' . $folderAccessModel->getItemFolderSqlConstraint('i.id_tree', (int) $userData['id']);

            // Decode URL and escape LIKE metacharacters to prevent wildcard injection
            $searchUrl = urldecode($arrQueryStringParams['url']);
            $likeUrl = str_replace(['%', '_'], ['\\%', '\\_'], $searchUrl);

            try {
                // Get user's private key from database
                $userPrivateKey = $this->getUserPrivateKey($userData);
                if ($userPrivateKey === null) {
                    $strErrorDesc = 'Invalid session or user keys not found';
                    $strErrorHeader = 'HTTP/1.1 401 Unauthorized';
                } else {
                    // Query items with the specific URL
                    $rows = DB::query(
                        "SELECT i.id, i.label, i.login, i.url, i.id_tree, i.favicon_url,
                                CASE WHEN o.enabled = 1 THEN 1 ELSE 0 END AS has_otp
                        FROM " . prefixTable('items') . " AS i
                        LEFT JOIN " . prefixTable('items_otp') . " AS o ON (o.item_id = i.id)
                        WHERE i.url LIKE %s" . $sql_constraint . "
                            AND i.deleted_at IS NULL
                        ORDER BY i.label ASC",
                        "%" . $likeUrl . "%"
                    );

                    // Fetch all sharekeys for matched items in a single query (avoids N+1)
                    $itemIds = array_column($rows, 'id');
                    $accessibleItemIds = [];
                    if (!empty($itemIds)) {
                        $shareKeyRows = DB::query(
                            'SELECT object_id FROM ' . prefixTable('sharekeys_items') . '
                            WHERE user_id = %i AND object_id IN %li',
                            $userData['id'],
                            $itemIds
                        );
                        foreach ($shareKeyRows as $sk) {
                            $accessibleItemIds[(int) $sk['object_id']] = true;
                        }
                    }

                    $ret = [];
                    foreach ($rows as $row) {
                        // Skip items for which the user has no sharekey
                        if (!isset($accessibleItemIds[(int) $row['id']])) {
                            continue;
                        }

                        $ret[] = [
                            'id' => (int) $row['id'],
                            'label' => $row['label'],
                            'login' => $row['login'],
                            'url' => $row['url'],
                            'folder_id' => (int) $row['id_tree'],
                            'has_otp' => (int) $row['has_otp'],
                            'favicon_url' => (string) $row['favicon_url'],
                        ];
                    }

                    // Empty collection → 200 + [] (a 204 must not carry a body — RFC 9110)
                    $responseData = json_encode($ret);
                    if (count($ret) > 0) {
                        $this->markApiFunctionalActivity($userData);
                    }
                }
            } catch (Error $e) {
                error_log('ItemController error: ' . $e->getMessage());
                $strErrorDesc = 'Something went wrong. Please contact support.';
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
            }
        } else {
            $strErrorDesc = 'Method not supported';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
            $arrErrorHeaders[] = 'Allow: GET';
        }

        // Send output
        if (empty($strErrorDesc) === true) {
            $this->sendOutput(
                $responseData,
                ['Content-Type: application/json', 'HTTP/1.1 200 OK']
            );
        } else {
            $this->sendProblemFromHeader($strErrorHeader, $strErrorDesc, $arrErrorHeaders ?? []);
        }
    }
    //end findByUrlAction()


    /**
     * Get OTP/TOTP code for an item
     * Retrieves the current 6-digit TOTP code for an item with OTP enabled
     *
     * @param array $userData User data from JWT token
     * @return void
     */
    public function getOtpAction(array $userData): void
    {
        $request = symfonyRequest::createFromGlobals();
        $requestMethod = $request->getMethod();
        $strErrorDesc = '';
        $responseData = '';
        $strErrorHeader = '';

        // get parameters
        $arrQueryStringParams = $this->getQueryStringParams();

        if (strtoupper($requestMethod) === 'GET') {
            // Check if item ID is provided
            if (!isset($arrQueryStringParams['id']) || empty($arrQueryStringParams['id'])) {
                $this->sendProblem(400, 'Item id is mandatory');
                return;
            }

            $itemId = (int) $arrQueryStringParams['id'];

            try {
                // Load config
                loadClasses('DB');

                // Load item basic info to check folder access
                $itemInfo = DB::queryFirstRow(
                    'SELECT id_tree FROM ' . prefixTable('items') . ' WHERE id = %i',
                    $itemId
                );

                if (DB::count() === 0) {
                    $strErrorDesc = 'Item not found';
                    $strErrorHeader = 'HTTP/1.1 404 Not Found';
                } else {
                    $folderAccessModel = new FolderAccessModel();
                    $hasAccess = $folderAccessModel->canAccessItemInFolder(
                        $userData,
                        (int) $itemInfo['id_tree'],
                        $itemId
                    );

                    if (!$hasAccess) {
                        $strErrorDesc = 'Access denied to this item';
                        $strErrorHeader = 'HTTP/1.1 403 Forbidden';
                    } else {
                        // Load OTP data
                        $otpData = DB::queryFirstRow(
                            'SELECT secret, enabled FROM ' . prefixTable('items_otp') . ' WHERE item_id = %i',
                            $itemId
                        );

                        if (DB::count() === 0) {
                            $strErrorDesc = 'OTP not configured for this item';
                            $strErrorHeader = 'HTTP/1.1 404 Not Found';
                        } elseif ((int) $otpData['enabled'] !== 1) {
                            $strErrorDesc = 'OTP is not enabled for this item';
                            $strErrorHeader = 'HTTP/1.1 403 Forbidden';
                        } else {
                            // Decrypt the secret
                            $decryptedSecret = cryption(
                                $otpData['secret'],
                                '',
                                'decrypt'
                            );

                            if (isset($decryptedSecret['string']) && !empty($decryptedSecret['string'])) {
                                // Generate OTP code using OTPHP library
                                try {
                                    $otp = \OTPHP\TOTP::createFromSecret($decryptedSecret['string']);
                                    $otpCode = $otp->now();
                                    $otpExpiresIn = $otp->expiresIn();

                                    $responseData = json_encode([
                                        'otp_code' => $otpCode,
                                        'expires_in' => $otpExpiresIn,
                                        'item_id' => $itemId
                                    ]);
                                    $this->markApiFunctionalActivity($userData);
                                } catch (\RuntimeException $e) {
                                    $strErrorDesc = 'Failed to generate OTP code: ' . $e->getMessage();
                                    $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
                                }
                            } else {
                                $strErrorDesc = 'Failed to decrypt OTP secret';
                                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
                            }
                        }
                    }
                }
            } catch (\Error $e) {
                error_log('ItemController error: ' . $e->getMessage());
                $strErrorDesc = 'Something went wrong. Please contact support.';
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
    //end getOtpAction()


    /**
     * Get all unique tags from the database
     *
     * @param array $userData User data from JWT token
     * @return void
     */
    public function allTagsAction(array $userData): void
    {
        $request = symfonyRequest::createFromGlobals();
        $requestMethod = $request->getMethod();
        $strErrorDesc = '';
        $responseData = '';
        $strErrorHeader = '';

        if (strtoupper($requestMethod) === 'GET') {
            try {
                $rows = DB::query(
                    'SELECT DISTINCT tag FROM ' . prefixTable('tags') . ' ORDER BY tag ASC'
                );

                $tags = array_column($rows, 'tag');
                $responseData = json_encode($tags);
            } catch (\Error $e) {
                error_log('ItemController error: ' . $e->getMessage());
                $strErrorDesc = 'Something went wrong. Please contact support.';
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
    //end allTagsAction()


    /**
     * Update an existing item
     * Updates an item based upon provided parameters and item ID
     *
     * @param array $userData User data from JWT token
     * @return void
     */
    public function updateAction(array $userData): void
    {
        $request = symfonyRequest::createFromGlobals();
        $requestMethod = $request->getMethod();
        $strErrorDesc = $strErrorHeader = $responseData = '';

        if (strtoupper($requestMethod) === 'PUT') {
            // Check if user is allowed to update items
            if ((int) $userData['allowed_to_update'] !== 1) {
                $strErrorDesc = 'User is not allowed to update items';
                $strErrorHeader = 'HTTP/1.1 403 Forbidden';
            } else {
                // Prepare user's accessible folders
                if (empty($userData['folders_list']) === false) {
                    $userData['folders_list'] = explode(',', $userData['folders_list']);
                } else {
                    $userData['folders_list'] = [];
                }
                $folderAccessModel = new FolderAccessModel();
                // folders_list is already filtered by index.php — normalize to int[] only
                $userData['folders_list'] = $folderAccessModel->normalizeFolderIds($userData['folders_list']);

                // Get parameters
                $arrQueryStringParams = $this->getQueryStringParams();

                // Check that the parameters are indeed an array before using them
                if (is_array($arrQueryStringParams)) {
                    // Check if item ID is provided
                    if (!isset($arrQueryStringParams['id']) || empty($arrQueryStringParams['id'])) {
                        $strErrorDesc = 'Item ID is mandatory';
                        $strErrorHeader = 'HTTP/1.1 400 Bad Request';
                    } else {
                        $itemId = (int) $arrQueryStringParams['id'];

                        try {
                            // Load item info to check access rights
                            $itemInfo = DB::queryFirstRow(
                                'SELECT id, id_tree, label FROM ' . prefixTable('items') . ' WHERE id = %i',
                                $itemId
                            );

                            if (DB::count() === 0) {
                                $strErrorDesc = 'Item not found';
                                $strErrorHeader = 'HTTP/1.1 404 Not Found';
                            } else {
                                $hasAccess = $folderAccessModel->canAccessItemInFolder(
                                    $userData,
                                    (int) $itemInfo['id_tree'],
                                    $itemId
                                );

                                if (!$hasAccess) {
                                    $strErrorDesc = 'Access denied to this item';
                                    $strErrorHeader = 'HTTP/1.1 403 Forbidden';
                                } elseif ($folderAccessModel->isFolderReadOnlyForUser((int) $itemInfo['id_tree'], (int) $userData['id'])) {
                                    $strErrorDesc = 'Access denied: folder is read-only';
                                    $strErrorHeader = 'HTTP/1.1 403 Forbidden';
                                } else {
                                    // Validate at least one field to update is provided
                                    $updateableFields = ['label', 'password', 'description', 'login', 'email', 'url', 'tags', 'anyone_can_modify', 'icon', 'folder_id', 'totp', 'fields'];
                                    $hasUpdateField = false;
                                    foreach ($updateableFields as $field) {
                                        if (isset($arrQueryStringParams[$field])) {
                                            $hasUpdateField = true;
                                            break;
                                        }
                                    }

                                    if (!$hasUpdateField) {
                                        $strErrorDesc = 'At least one field to update must be provided (label, password, description, login, email, url, tags, anyone_can_modify, icon, folder_id, totp, fields)';
                                        $strErrorHeader = 'HTTP/1.1 400 Bad Request';
                                    } else {
                                        // Normalize custom fields payload (accepts JSON array or JSON-encoded string)
                                        if (isset($arrQueryStringParams['fields'])) {
                                            $arrQueryStringParams['fields'] = $this->normalizeFields($arrQueryStringParams['fields']);
                                        }
                                        // Get user's private key for password encryption/decryption
                                        $userPrivateKey = $this->getUserPrivateKey($userData);
                                        if ($userPrivateKey === null) {
                                            $strErrorDesc = 'Invalid session or user keys not found';
                                            $strErrorHeader = 'HTTP/1.1 401 Unauthorized';
                                        } else {
                                            // Update the item
                                            $itemModel = new ItemModel();
                                            $ret = $itemModel->updateItem(
                                                $itemId,
                                                $arrQueryStringParams,
                                                $userData,
                                                $userPrivateKey
                                            );

                                            if ($ret['error'] === true) {
                                                $strErrorDesc = $ret['error_message'];
                                                $strErrorHeader = $ret['error_header'];
                                            } else {
                                                $responseData = json_encode($ret);
                                            }
                                        }
                                    }
                                }
                            }
                        } catch (Error $e) {
                            error_log('[API] ItemController error: ' . $e->getMessage());
                            $strErrorDesc = 'An internal error occurred. Please contact support.';
                            $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
                        }
                    }
                } else {
                    $strErrorDesc = 'Data not consistent';
                    $strErrorHeader = 'HTTP/1.1 400 Bad Request';
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
            $this->sendProblemFromHeader($strErrorHeader, $strErrorDesc, $arrErrorHeaders ?? []);
        }
    }
    //end updateAction()


    /**
     * Delete an existing item
     * Updates an item based upon provided parameters and item ID
     *
     * @param array $userData User data from JWT token
     * @return void
     */
    public function deleteAction(array $userData): void
    {
        $request = symfonyRequest::createFromGlobals();
        $requestMethod = $request->getMethod();
        $strErrorDesc = $strErrorHeader = $responseData = '';

        if (strtoupper($requestMethod) === 'DELETE') {
            // Check if user is allowed to delete items
            if ((int) $userData['allowed_to_delete'] !== 1) {
                $strErrorDesc = 'User is not allowed to delete items';
                $strErrorHeader = 'HTTP/1.1 403 Forbidden';
            } else {
                // Get parameters
                $arrQueryStringParams = $this->getQueryStringParams();
                
                // Check that the parameters are indeed an array before using them
                if (is_array($arrQueryStringParams)) {
                    // Check if item ID is provided
                    if (!isset($arrQueryStringParams['id']) || empty($arrQueryStringParams['id'])) {
                        $strErrorDesc = 'Item ID is mandatory';
                        $strErrorHeader = 'HTTP/1.1 400 Bad Request';
                    } else {
                        $itemId = (int) $arrQueryStringParams['id'];
                        
                        try {
                            // Load item info to check access rights
                            $itemInfo = DB::queryFirstRow(
                                'SELECT id, id_tree, label FROM ' . prefixTable('items') . ' WHERE id = %i',
                                $itemId
                            );

                            if (DB::count() === 0) {
                                $strErrorDesc = 'Item not found';
                                $strErrorHeader = 'HTTP/1.1 404 Not Found';
                            } else {
                                $folderAccessModel = new FolderAccessModel();
                                $hasAccess = $folderAccessModel->canAccessItemInFolder(
                                    $userData,
                                    (int) $itemInfo['id_tree'],
                                    $itemId
                                );

                                if (!$hasAccess) {
                                    $strErrorDesc = 'Access denied to this item';
                                    $strErrorHeader = 'HTTP/1.1 403 Forbidden';
                                } elseif ($folderAccessModel->isFolderReadOnlyForUser((int) $itemInfo['id_tree'], (int) $userData['id'])) {
                                    $strErrorDesc = 'Access denied: folder is read-only';
                                    $strErrorHeader = 'HTTP/1.1 403 Forbidden';
                                } else {
                                    // delete the item
                                    $itemModel = new ItemModel();
                                    $ret = $itemModel->deleteItem(
                                        $itemId,
                                        $userData
                                    );

                                    if ($ret['error'] === true) {
                                        $strErrorDesc = $ret['error_message'];
                                        $strErrorHeader = $ret['error_header'];
                                    } else {
                                        $responseData = json_encode($ret);
                                    }
                                }
                            }
                        } catch (Error $e) {
                            error_log('[API] ItemController error: ' . $e->getMessage());
                            $strErrorDesc = 'An internal error occurred. Please contact support.';
                            $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
                        }
                    }
                } else {
                    $strErrorDesc = 'Data not consistent';
                    $strErrorHeader = 'HTTP/1.1 400 Bad Request';
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
            $this->sendProblemFromHeader($strErrorHeader, $strErrorDesc, $arrErrorHeaders ?? []);
        }
    }
    //end deleteAction()


}
