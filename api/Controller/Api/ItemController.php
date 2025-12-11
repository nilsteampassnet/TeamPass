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
 * @copyright 2009-2025 Teampass.net
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
     * The private key is stored ENCRYPTED in the database and is decrypted
     * using the session_key from the JWT token.
     *
     * @param array $userData User data from JWT token (must include id, key_tempo, and session_key)
     * @return string|null Decrypted private key or null if not found/invalid session
     */
    private function getUserPrivateKey(array $userData): ?string
    {
        include_once API_ROOT_PATH . '/inc/jwt_utils.php';

        // Verify session_key exists in JWT payload
        if (!isset($userData['session_key']) || empty($userData['session_key'])) {
            error_log('getUserPrivateKey: Missing session_key in JWT token for user ID ' . $userData['id']);
            return null;
        }

        $userKeys = get_user_keys(
            (int) $userData['id'],
            (string) $userData['key_tempo'],
            (string) $userData['session_key'] // Session key from JWT for decryption
        );

        if ($userKeys === null) {
            return null;
        }

        return $userKeys['private_key'];
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

                // Get user's private key from database
                $userPrivateKey = $this->getUserPrivateKey($userData);
                if ($userPrivateKey === null) {
                    $strErrorDesc = 'Invalid session or user keys not found';
                    $strErrorHeader = 'HTTP/1.1 401 Unauthorized';
                } else {
                    $arrItems = $itemModel->getItems($sqlExtra, $intLimit, $userPrivateKey, $userData['id']);
                    if (!empty($arrItems)) {
                        $responseData = json_encode($arrItems);
                    } else {
                        $strErrorDesc = 'No content for this label';
                        $strErrorHeader = 'HTTP/1.1 204 No Content';
                    }
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
            //&& isset($arrQueryStringParams['email']) === true
            //&& isset($arrQueryStringParams['url']) === true
            //&& isset($arrQueryStringParams['tags']) === true
            //&& isset($arrQueryStringParams['anyone_can_modify']) === true
        ) {
            //
            if (in_array($arrQueryStringParams['folder_id'], $userData['folders_list']) === false && $userData['user_can_create_root_folder'] === 0) {
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
                            (string) $arrQueryStringParams['description'] ?? '',
                            (string) $arrQueryStringParams['login'],
                            (string) $arrQueryStringParams['email'] ?? '',
                            (string) $arrQueryStringParams['url'] ?? '' ,
                            (string) $arrQueryStringParams['tags'] ?? '',
                            (int) $arrQueryStringParams['anyone_can_modify'] ?? 0,
                            (string) $arrQueryStringParams['icon'] ?? '',
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

                // Get user's private key from database
                $userPrivateKey = $this->getUserPrivateKey($userData);
                if ($userPrivateKey === null) {
                    $strErrorDesc = 'Invalid session or user keys not found';
                    $strErrorHeader = 'HTTP/1.1 401 Unauthorized';
                } else {
                    $arrItems = $itemModel->getItems($sqlExtra, 0, $userPrivateKey, $userData['id']);
                    $responseData = json_encode($arrItems);
                }
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
            $this->sendOutput(
                json_encode(['error' => 'URL parameter is mandatory']),
                ['Content-Type: application/json', 'HTTP/1.1 400 Bad Request']
            );
            return;
        }

        // Prepare user's accessible folders
        /*if (empty($userData['folders_list']) === false) {
            $userData['folders_list'] = explode(',', $userData['folders_list']);
        } else {
            $userData['folders_list'] = [];
        }*/

        // Build SQL constraint for accessible folders
        $sql_constraint = ' AND (i.id_tree IN (' . $userData['folders_list'] . ')';
        if (!empty($userData['restricted_items_list'])) {
            $sql_constraint .= ' OR i.id IN (' . $userData['restricted_items_list'] . ')';
        }
        $sql_constraint .= ')';

        // Decode URL if needed
        $searchUrl = urldecode($arrQueryStringParams['url']);

        try {
            // Get user's private key from database
            $userPrivateKey = $this->getUserPrivateKey($userData);
            if ($userPrivateKey === null) {
                $strErrorDesc = 'Invalid session or user keys not found';
                $strErrorHeader = 'HTTP/1.1 401 Unauthorized';
            } else {
                // Query items with the specific URL
                $rows = DB::query(
                    "SELECT i.id, i.label, i.login, i.url, i.id_tree, 
                            CASE WHEN o.enabled = 1 THEN 1 ELSE 0 END AS has_otp
                    FROM " . prefixTable('items') . " AS i
                    LEFT JOIN " . prefixTable('items_otp') . " AS o ON (o.item_id = i.id)
                    WHERE i.url LIKE %s" . $sql_constraint . "
                    ORDER BY i.label ASC",
                    "%".$searchUrl."%"
                );

                $ret = [];
                foreach ($rows as $row) {
                    // Get user's sharekey for this item
                    $shareKey = DB::queryfirstrow(
                        'SELECT share_key
                        FROM ' . prefixTable('sharekeys_items') . '
                        WHERE user_id = %i AND object_id = %i',
                        $userData['id'],
                        $row['id']
                    );

                    // Skip if no sharekey found (user doesn't have access)
                    if (DB::count() === 0) {
                        continue;
                    }

                    // Build response
                    array_push(
                        $ret,
                        [
                            'id' => (int) $row['id'],
                            'label' => $row['label'],
                            'login' => $row['login'],
                            'url' => $row['url'],
                            'folder_id' => (int) $row['id_tree'],
                            'has_otp' => (int) $row['has_otp'],
                        ]
                    );
                }

                if (!empty($ret)) {
                    $responseData = json_encode($ret);
                } else {
                    $strErrorDesc = 'No items found with this URL';
                    $strErrorHeader = 'HTTP/1.1 204 No Content';
                }
            }
        } catch (Error $e) {
            $strErrorDesc = $e->getMessage() . '. Something went wrong! Please contact support.';
            $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
        }
    } else {
        $strErrorDesc = 'Method not supported';
        $strErrorHeader = 'HTTP/1.1 422 Unprocessable Entity';
    }

    // Send output
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
                $this->sendOutput(
                    json_encode(['error' => 'Item id is mandatory']),
                    ['Content-Type: application/json', 'HTTP/1.1 400 Bad Request']
                );
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
                    // Check if user has access to the folder
                    $userFolders = !empty($userData['folders_list']) ? explode(',', $userData['folders_list']) : [];
                    $hasAccess = in_array((string) $itemInfo['id_tree'], $userFolders, true);

                    // Also check restricted items if applicable
                    if (!$hasAccess && !empty($userData['restricted_items_list'])) {
                        $restrictedItems = explode(',', $userData['restricted_items_list']);
                        $hasAccess = in_array((string) $itemId, $restrictedItems, true);
                    }

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
                $strErrorDesc = $e->getMessage() . '. Something went wrong! Please contact support.';
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
    //end getOtpAction()


}
