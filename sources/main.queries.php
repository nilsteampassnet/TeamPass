<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      main.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\PasswordManager\PasswordManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;
use Hackzilla\PasswordGenerator\RandomGenerator\Php7RandomGenerator;
use RobThree\Auth\TwoFactorAuth;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\EmailService\EmailService;
use TeampassClasses\EmailService\EmailSettings;

// Load functions
require_once 'main.functions.php';

loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// TODO : ajouter un check sue l'envoi de la key

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if (
    ($checkUserAccess->userAccessPage('home') === false ||
    $checkUserAccess->checkSession() === false)
    && in_array(filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS), ['get_teampass_settings', 'ga_generate_qr']) === false
) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');
set_time_limit(600);

// DO CHECKS
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (
    isset($post_type) === true
    && ($post_type === 'ga_generate_qr'
        || $post_type === 'get_teampass_settings')
) {
    // continue
    mainQuery($SETTINGS);
} elseif (
    $session->has('user-id') && null !== $session->get('user-id')
    && $checkUserAccess->userAccessPage('home') === false
) {
    $session->set('system-error_code', ERR_NOT_ALLOWED); //not allowed page
    include __DIR__.'/../error.php';
    exit();
} elseif (($session->has('user-id') && null !== $session->get('user-id')
        && $session->get('key') !== null)
    || (isset($post_type) === true
        && null !== filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES))
) {
    // continue
    mainQuery($SETTINGS);
} else {
    $session->set('system-error_code', ERR_NOT_ALLOWED); //not allowed page
    include __DIR__.'/../error.php';
    exit();
}

// Includes
include_once __DIR__.'/../sources/main.functions.php';

/**
 * Undocumented function.
 */
function mainQuery(array $SETTINGS)
{
    header('Content-type: text/html; charset=utf-8');
    header('Cache-Control: no-cache');
    error_reporting(E_ERROR);

    // Load libraries
    loadClasses('DB');

    // User's language loading
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');
    $request = SymfonyRequest::createFromGlobals();

    // Prepare POST variables
    $inputData = dataSanitizer(
        [
            'type' => $request->request->filter('type', '', FILTER_SANITIZE_SPECIAL_CHARS),
            'data' => $request->request->filter('data', '', FILTER_SANITIZE_SPECIAL_CHARS),
            'key' => $request->request->filter('key', '', FILTER_SANITIZE_SPECIAL_CHARS),
            'type_category' => $request->request->filter('type_category', '', FILTER_SANITIZE_SPECIAL_CHARS),
        ],
        [
            'type' => 'trim|escape',
            'data' => 'trim|escape',
            'key' => 'trim|escape',
            'type_category' => 'trim|escape',
        ]
    );
    
    // Check KEY
    if (isValueSetNullEmpty($inputData['key']) === true) {
        echo prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('key_is_not_correct'),
            ),
            'encode',
            $inputData['key']
        );
        return false;
    }
    // decrypt and retreive data in JSON format
    $dataReceived = empty($inputData['data']) === false ? prepareExchangedData(
        $inputData['data'],
        'decode'
    ) : '';
    
    switch ($inputData['type_category']) {
        case 'action_password':
            echo passwordHandler($inputData['type'], $dataReceived, $SETTINGS);
            break;

        case 'action_user':
            echo userHandler($inputData['type'], $dataReceived, $SETTINGS, $inputData['key']);
            break;

        case 'action_mail':
            echo mailHandler($inputData['type'], $dataReceived, $SETTINGS);
            break;

        case 'action_key':
            // deepcode ignore ServerLeak: All cases handled by keyHandler return an encrypted string that is sent back to the client
            echo keyHandler($inputData['type'], $dataReceived, $SETTINGS);
            break;

        case 'action_system':
            echo systemHandler($inputData['type'], $dataReceived, $SETTINGS);
            break;

        case 'action_utils':
            echo utilsHandler($inputData['type'], $dataReceived, $SETTINGS);
            break;
    }
    
}

/**
 * Handler for all password tasks
 *
 * @param string $post_type
 * @param array|null|string $dataReceived
 * @param array $SETTINGS
 * @return string
 */
function passwordHandler(string $post_type, /*php8 array|null|string*/ $dataReceived, array $SETTINGS): string
{
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');

    switch ($post_type) {
        case 'change_pw'://action_password
            return changePassword(
                (string) filter_var($dataReceived['new_pw'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                isset($dataReceived['current_pw']) === true ? (string) filter_var($dataReceived['current_pw'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '',
                (int) filter_var($dataReceived['complexity'], FILTER_SANITIZE_NUMBER_INT),
                (string) filter_var($dataReceived['change_request'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                (int) $session->get('user-id'),
                $SETTINGS
            );

        /*
         * Change user's authentication password
         */
        case 'change_user_auth_password'://action_password

            // Check new password and confirm match server side
            if ($dataReceived['new_password'] !== $dataReceived['new_password_confirm']) {
                return prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_bad_credentials'),
                    ),
                    'encode'
                );
            }

            // Check if new password is strong
            if (!isPasswordStrong($dataReceived['new_password'])) {
                return prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('complexity_level_not_reached'),
                    ),
                    'encode'
                );
            }

            return changeUserAuthenticationPassword(
                (int) $session->get('user-id'),
                (string) filter_var($dataReceived['old_password'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                (string) filter_var($dataReceived['new_password'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                $SETTINGS
            );

        /*
         * User's authentication password in LDAP has changed
         */
        case 'change_user_ldap_auth_password'://action_password

            // Users passwords are html escaped
            $userPassword = filter_var($dataReceived['current_password'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            // Get current user hash
            $userHash = DB::queryFirstRow(
                "SELECT pw FROM " . prefixtable('users') . " WHERE id = %d;",
                $session->get('user-id')
            )['pw'];

            $passwordManager = new PasswordManager();

            // Verify provided user password
            if (!$passwordManager->verifyPassword($userHash, $userPassword)) {
                return prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_bad_credentials'),
                    ),
                    'encode'
                );
            }

            return /** @scrutinizer ignore-call */ changeUserLDAPAuthenticationPassword(
                (int) $session->get('user-id'),
                filter_var($dataReceived['previous_password'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                filter_var($userPassword)
            );

        /*
         * test_current_user_password_is_correct
         */
        case 'test_current_user_password_is_correct'://action_password
            return isUserPasswordCorrect(
                (int) $session->get('user-id'),
                (string) $dataReceived['password'],
                $SETTINGS
            );

        /*
         * Default case
         */
        default :
            return prepareExchangedData(
                array(
                    'error' => true,
                ),
                'encode'
            );
    }
}

/**
 * Handler for all user tasks
 *
 * @param string $post_type
 * @param array|null|string $dataReceived
 * @param array $SETTINGS
 * @param string $post_key
 * @return string
 */
function userHandler(string $post_type, array|null|string $dataReceived, array $SETTINGS, string $post_key): string
{
    $session = SessionManager::getSession();

    // List of post types allowed to all users
    $all_users_can_access = [
        'get_user_info',
        'increase_session_time',
        'generate_password',
        'refresh_list_items_seen',
        'ga_generate_qr',
        'user_get_session_time',
        'save_user_location'
    ];

    // Default values
    $filtered_user_id = $session->get('user-id');

    // User can't manage users and requested type is administrative.
    if ((int) $session->get('user-admin') !== 1 &&
        (int) $session->get('user-manager') !== 1 &&
        (int) $session->get('user-can_manage_all_users') !== 1 &&
        !in_array($post_type, $all_users_can_access)) {

        return prepareExchangedData(
            array(
                'error' => true,
            ),
            'encode'
        );
    }

    if (isset($dataReceived['user_id'])) {
        // Get info about user to modify
        $targetUserInfos = DB::queryFirstRow(
            'SELECT admin, gestionnaire, can_manage_all_users, isAdministratedByRole FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $dataReceived['user_id']
        );

        if (
            // Administrator user
            (int) $session->get('user-admin') === 1
            // Manager of basic/ro users in this role
            || ((int) $session->get('user-manager') === 1
                && in_array($targetUserInfos['isAdministratedByRole'], $session->get('user-roles_array'))
                && (int) $targetUserInfos['admin'] !== 1
                && (int) $targetUserInfos['can_manage_all_users'] !== 1
                && (int) $targetUserInfos['gestionnaire'] !== 1)
            // Manager of all basic/ro users
            || ((int) $session->get('user-can_manage_all_users') === 1
                && (int) $targetUserInfos['admin'] !== 1
                && (int) $targetUserInfos['can_manage_all_users'] !== 1
                && (int) $targetUserInfos['gestionnaire'] !== 1)
        ) {
            // This user is allowed to modify other users.
            $filtered_user_id = $dataReceived['user_id'];
        }
    }

    switch ($post_type) {
        /*
        * Get info 
        */
        case 'get_user_info'://action_user
            return getUserInfo(
                (int) $filtered_user_id,
                $SETTINGS
            );

        /*
        * Increase the session time of User
        */
        case 'increase_session_time'://action_user
            return increaseSessionDuration(
                (int) filter_input(INPUT_POST, 'duration', FILTER_SANITIZE_NUMBER_INT)
            );

        /*
        * Generate a password generic
        */
        case 'generate_password'://action_user
            return generateGenericPassword(
                (int) filter_input(INPUT_POST, 'size', FILTER_SANITIZE_NUMBER_INT),
                (bool) filter_input(INPUT_POST, 'secure_pwd', FILTER_VALIDATE_BOOLEAN),
                (bool) filter_input(INPUT_POST, 'lowercase', FILTER_VALIDATE_BOOLEAN),
                (bool) filter_input(INPUT_POST, 'capitalize', FILTER_VALIDATE_BOOLEAN),
                (bool) filter_input(INPUT_POST, 'numerals', FILTER_VALIDATE_BOOLEAN),
                (bool) filter_input(INPUT_POST, 'symbols', FILTER_VALIDATE_BOOLEAN),
                $SETTINGS
            );

        /*
        * Refresh list of last items seen
        */
        case 'refresh_list_items_seen'://action_user
            if ($session->has('user-id') || (int) $session->get('user-id') && null !== $session->get('user-id') || (int) $session->get('user-id') > 0) {
                return refreshUserItemsSeenList(
                    $SETTINGS
                );

            } else {
                return json_encode(
                    array(
                        'error' => '',
                        'existing_suggestions' => 0,
                        'html_json' => '',
                    ),
                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                );
            }

        /*
        * This will generate the QR Google Authenticator
        */
        case 'ga_generate_qr'://action_user
            return generateQRCode(
                (int) $filtered_user_id,
                (string) filter_var($dataReceived['demand_origin'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                (string) filter_var($dataReceived['send_email'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                (string) filter_var($dataReceived['login'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                (string) filter_var($dataReceived['pwd'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                (string) filter_var($dataReceived['token'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                $SETTINGS
            );

        /*
        * This will set the user ready
        */
        case 'user_is_ready'://action_user
            return userIsReady(
                (int) $filtered_user_id,
                (string) $SETTINGS['cpassman_dir']
            );

        /*
        * This post type is used to check if the user session is still valid
        */
        case 'user_get_session_time'://action_user
            return userGetSessionTime(
                (int) $session->get('user-id'),
                (string) $SETTINGS['cpassman_dir'],
                (int) $SETTINGS['maximum_session_expiration_time'],
            );

        case 'save_user_location'://action_user
            return userSaveIp(
                (int) $session->get('user-id'),
                (string) filter_var($dataReceived['action'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            );

        /*
        * Default case
        */
        default :
            return prepareExchangedData(
                array(
                    'error' => true,
                ),
                'encode'
            );
    }
}

/**
 * Handler for all mail tasks
 *
 * @param string $post_type
 * @param array|null|string $dataReceived
 * @param array $SETTINGS
 * @return string
 */
function mailHandler(string $post_type, /*php8 array|null|string */$dataReceived, array $SETTINGS): string
{
    $session = SessionManager::getSession();

    switch ($post_type) {
        /*
         * CASE
         * Send email
         */
        case 'mail_me'://action_mail
            // Get info about user to send email
            $data_user = DB::queryFirstRow(
                'SELECT admin, gestionnaire, can_manage_all_users, isAdministratedByRole FROM ' . prefixTable('users') . '
                WHERE email = %s',
                filter_var($dataReceived['receipt'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)
            );

            // Unknown email address
            if (!$data_user) {
                return prepareExchangedData(
                    array(
                        'error' => true,
                    ),
                    'encode'
                );
            }

            // Only administrators and managers can send mails
            if (
                // Administrator user
                (int) $session->get('user-admin') === 1
                // Manager of basic/ro users in this role
                || ((int) $session->get('user-manager') === 1
                    && in_array($data_user['isAdministratedByRole'], $session->get('user-roles_array'))
                    && (int) $data_user['admin'] !== 1
                    && (int) $data_user['can_manage_all_users'] !== 1
                    && (int) $data_user['gestionnaire'] !== 1)
                // Manager of all basic/ro users
                || ((int) $session->get('user-can_manage_all_users') === 1
                    && (int) $data_user['admin'] !== 1
                    && (int) $data_user['can_manage_all_users'] !== 1
                    && (int) $data_user['gestionnaire'] !== 1)
            ) {
                return sendMailToUser(
                    filter_var($dataReceived['receipt'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                    $dataReceived['body'],
                    (string) filter_var($dataReceived['subject'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                    (array) filter_var_array(
                        $dataReceived['pre_replace'],
                        FILTER_SANITIZE_FULL_SPECIAL_CHARS
                    ),
                    true
                );
            }

            return prepareExchangedData(
                array(
                    'error' => true,
                ),
                'encode'
            );
        /*
        * Send emails not sent
        */
        case 'send_waiting_emails'://mail
            // Administrative task
            if ((int) $session->get('user-admin') !== 1) {
                return prepareExchangedData(
                    array(
                        'error' => true,
                    ),
                    'encode'
                );
            }

            sendEmailsNotSent(
                $SETTINGS
            );
            return prepareExchangedData(
                array(
                    'error' => false,
                    'message' => 'mail_sent',
                ),
                'encode'
            );

        /*
        * Default case
        */
        default :
            return prepareExchangedData(
                array(
                    'error' => true,
                ),
                'encode'
            );
    }
}

/**
 * Handler for all key related tasks
 *
 * @param string $post_type
 * @param array|null|string $dataReceived
 * @param array $SETTINGS
 * @return string
 */
function keyHandler(string $post_type, /*php8 array|null|string */$dataReceived, array $SETTINGS): string
{
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');

    // List of post types allowed to all users
    $all_users_can_access = [
        'change_private_key_encryption_password',
        'user_new_keys_generation',
        'user_recovery_keys_download',
    ];

    // Default values
    $filtered_user_id = $session->get('user-id');

    if (isset($dataReceived['user_id'])) {
        // Get info about user to modify
        $targetUserInfos = DB::queryFirstRow(
            'SELECT admin, gestionnaire, can_manage_all_users, isAdministratedByRole FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $dataReceived['user_id']
        );
    
        if (
            // Administrator user
            (int) $session->get('user-admin') === 1
            // Manager of basic/ro users in this role
            || ((int) $session->get('user-manager') === 1
                && in_array($targetUserInfos['isAdministratedByRole'], $session->get('user-roles_array'))
                && (int) $targetUserInfos['admin'] !== 1
                && (int) $targetUserInfos['can_manage_all_users'] !== 1
                && (int) $targetUserInfos['gestionnaire'] !== 1)
            // Manager of all basic/ro users
            || ((int) $session->get('user-can_manage_all_users') === 1
                && (int) $targetUserInfos['admin'] !== 1
                && (int) $targetUserInfos['can_manage_all_users'] !== 1
                && (int) $targetUserInfos['gestionnaire'] !== 1)
        ) {
            // This user is allowed to modify other users.
            $filtered_user_id = $dataReceived['user_id'];
    
        } else if (!in_array($post_type, $all_users_can_access)) {
            // User can't manage users and requested type is administrative.
            return prepareExchangedData(
                array(
                    'error' => true,
                ),
                'encode'
            ); 
        }
    }

    switch ($post_type) {
        /*
         * Generate a temporary encryption key for user
         */
        case 'generate_temporary_encryption_key'://action_key
            return generateOneTimeCode(
                (int) filter_var($filtered_user_id, FILTER_SANITIZE_NUMBER_INT)
            );

        /*
         * user_sharekeys_reencryption_next
         */
        case 'user_sharekeys_reencryption_next'://action_key
            return continueReEncryptingUserSharekeys(
                (int) filter_var($filtered_user_id, FILTER_SANITIZE_NUMBER_INT),
                (bool) filter_var($dataReceived['self_change'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                (string) filter_var($dataReceived['action'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                (int) filter_var($dataReceived['start'], FILTER_SANITIZE_NUMBER_INT),
                (int) filter_var($dataReceived['length'], FILTER_SANITIZE_NUMBER_INT),
                $SETTINGS
            );

        /*
         * user_psk_reencryption
         */
        case 'user_psk_reencryption'://action_key
            return migrateTo3_DoUserPersonalItemsEncryption(
                (int) filter_var($filtered_user_id, FILTER_SANITIZE_NUMBER_INT),
                (int) filter_var($dataReceived['start'], FILTER_SANITIZE_NUMBER_INT),
                (int) filter_var($dataReceived['length'], FILTER_SANITIZE_NUMBER_INT),
                (int) filter_var($dataReceived['counterItemsToTreat'], FILTER_SANITIZE_NUMBER_INT),
                (string) filter_var($dataReceived['userPsk'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                $SETTINGS
            );

        /*
         * User's public/private keys change
         */
        case 'change_private_key_encryption_password'://action_key

            // Users passwords are html escaped
            $newPassword = filter_var($dataReceived['new_code'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            // Get current user hash
            $userHash = DB::queryFirstRow(
                "SELECT pw FROM " . prefixtable('users') . " WHERE id = %d;",
                $session->get('user-id')
            )['pw'];

            $passwordManager = new PasswordManager();

            // Verify provided user password
            if (!$passwordManager->verifyPassword($userHash, $newPassword)) {
                return prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_bad_credentials'),
                    ),
                    'encode'
                );
            }

            return changePrivateKeyEncryptionPassword(
                (int) filter_var($filtered_user_id, FILTER_SANITIZE_NUMBER_INT),
                (string) $dataReceived['current_code'],
                (string) $newPassword,
                (string) filter_var($dataReceived['action_type'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                $SETTINGS
            );

        /*
         * Launch user keys change on his demand
         */
        case 'user_new_keys_generation'://action_key

            // Users passwords are html escaped
            $userPassword = filter_var($dataReceived['user_pwd'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            // Don't generate new user password -> verify it
            if ($dataReceived['generate_user_new_password'] !== true) {

                // Get current user hash
                $userHash = DB::queryFirstRow(
                    "SELECT pw FROM " . prefixtable('users') . " WHERE id = %d;",
                    $session->get('user-id')
                )['pw'];

                $passwordManager = new PasswordManager();

                // Verify provided user password
                if (!$passwordManager->verifyPassword($userHash, $userPassword)) {
                    return prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => $lang->get('error_bad_credentials'),
                        ),
                        'encode'
                    );
                }
            }

            return handleUserKeys(
                (int) filter_var($filtered_user_id, FILTER_SANITIZE_NUMBER_INT),
                (string) $userPassword,
                (int) isset($SETTINGS['maximum_number_of_items_to_treat']) === true ? $SETTINGS['maximum_number_of_items_to_treat'] : NUMBER_ITEMS_IN_BATCH,
                (string) filter_var($dataReceived['encryption_key'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                (bool) filter_var($dataReceived['delete_existing_keys'], FILTER_VALIDATE_BOOLEAN),
                (bool) filter_var($dataReceived['send_email_to_user'], FILTER_VALIDATE_BOOLEAN),
                (bool) filter_var($dataReceived['encrypt_with_user_pwd'], FILTER_VALIDATE_BOOLEAN),
                (bool) isset($dataReceived['generate_user_new_password']) === true ? filter_var($dataReceived['generate_user_new_password'], FILTER_VALIDATE_BOOLEAN) : false,
                (string) filter_var($dataReceived['email_body'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                (bool) filter_var($dataReceived['user_self_change'], FILTER_VALIDATE_BOOLEAN),
                (string) filter_var($dataReceived['recovery_public_key'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                (string) filter_var($dataReceived['recovery_private_key'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            );

        /*
         * Launch user recovery download
         */
        case 'user_recovery_keys_download'://action_key
            // Validate user password on local and LDAP accounts before download
            if ($session->get('user-auth_type') !== 'oauth2') {
                // Users passwords are html escaped
                $userPassword = filter_var($dataReceived['password'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                // Get current user hash
                $userHash = DB::queryFirstRow(
                    "SELECT pw FROM " . prefixtable('users') . " WHERE id = %i;",
                    $session->get('user-id')
                )['pw'];

                $passwordManager = new PasswordManager();

                // Verify provided user password
                if (!$passwordManager->verifyPassword($userHash, $userPassword)) {
                    return prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => $lang->get('error_bad_credentials'),
                        ),
                        'encode'
                    );
                }
            }

            return handleUserRecoveryKeysDownload(
                (int) $filtered_user_id,
                (array) $SETTINGS,
            );

        /*
         * Default case
         */
        default :
            return prepareExchangedData(
                array(
                    'error' => true,
                ),
                'encode'
            );
    }
}

/**
 * Handler for all system tasks
 *
 * @param string $post_type Type of action to perform
 * @param mixed $dataReceived Data received for processing
 * @param array $SETTINGS Application settings
 * @return string JSON-encoded response
 */
function systemHandler(string $post_type, $dataReceived, array $SETTINGS): string
{
    try {
        $session = SessionManager::getSession();

        switch ($post_type) {
            case 'get_number_of_items_to_treat':
                return handleGetNumberOfItems($dataReceived, $SETTINGS);
            case 'sending_statistics':
                return handleSendingStatistics($SETTINGS);
            case 'generate_bug_report':
                return handleGenerateBugReport($dataReceived, $SETTINGS, $session);
            case 'get_teampass_settings':
                return handleGetTeampassSettings($SETTINGS);
            case 'save_token':
                return handleSaveToken($session);
            default:
                return prepareErrorResponse('Unknown action type');
        }
    } catch (Exception $e) {
        return prepareErrorResponse($e->getMessage());
    }
}

/**
 * Handles the 'get_number_of_items_to_treat' action
 */
function handleGetNumberOfItems($dataReceived, array $SETTINGS): string
{
    $userId = getValidatedInput($dataReceived, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    if ($userId === false) {
        return prepareErrorResponse('Invalid User ID');
    }

    return prepareSuccessResponse([
        'count' => getNumberOfItemsToTreat((int)$userId, $SETTINGS)
    ]);
}

/**
 * Handles the 'sending_statistics' action
 */
function handleSendingStatistics(array $SETTINGS): string
{
    sendingStatistics($SETTINGS);
    return prepareSuccessResponse();
}

/**
 * Handles the 'generate_bug_report' action
 */
function handleGenerateBugReport($dataReceived, array $SETTINGS, $session): string
{
    if ((int) $session->get('user-admin') !== 1) {
        return prepareSuccessResponse([]); // Return empty success for non-admins
    }

    if (!is_array($dataReceived)) {
        return prepareErrorResponse('Invalid data format');
    }

    $reportContent = generateBugReport($dataReceived, $SETTINGS);
    if ($reportContent['error'] === true) {
        return prepareErrorResponse('Failed to generate bug report');
    }

    return prepareSuccessResponse([
        'report' => $reportContent['report']
    ]);
}

/**
 * Handles the 'get_teampass_settings' action
 */
function handleGetTeampassSettings(array $SETTINGS): string
{
    $allowedSettings = [
        'ldap_user_attribute', 'enable_pf_feature', 'clipboard_life_duration',
        'enable_favourites', 'copy_to_clipboard_small_icons', 'enable_attachment_encryption',
        'google_authentication', 'agses_authentication_enabled', 'yubico_authentication',
        'duo', 'personal_saltkey_security_level', 'enable_tasks_manager',
        'insert_manual_entry_item_history', 'show_item_data'
    ];

    return prepareSuccessResponse([
        'settings' => array_intersect_key($SETTINGS, array_flip($allowedSettings))
    ]);
}

/**
 * Handles the 'save_token' action
 */
function handleSaveToken($session): string
{
    $tokenParams = [
        'size' => FILTER_SANITIZE_NUMBER_INT,
        'secure' => FILTER_VALIDATE_BOOLEAN,
        'numeric' => FILTER_VALIDATE_BOOLEAN,
        'capital' => FILTER_VALIDATE_BOOLEAN,
        'symbols' => FILTER_VALIDATE_BOOLEAN,
        'lowercase' => FILTER_VALIDATE_BOOLEAN,
        'duration' => FILTER_SANITIZE_NUMBER_INT,
        'reason' => FILTER_SANITIZE_FULL_SPECIAL_CHARS
    ];

    $params = [];
    foreach ($tokenParams as $name => $filter) {
        $params[$name] = getPostInput($name, $filter);
    }

    $defaults = [
        'size' => 20,
        'secure' => false,
        'numeric' => false,
        'capital' => false,
        'symbols' => false,
        'lowercase' => false,
        'duration' => 3600,
        'reason' => ''
    ];

    // Apply defaults where needed
    foreach ($defaults as $key => $value) {
        if ($params[$key] === null) {
            $params[$key] = $value;
        }
    }

    $token = GenerateCryptKey(
        (int)$params['size'],
        (bool)$params['secure'],
        (bool)$params['numeric'],
        (bool)$params['capital'],
        (bool)$params['symbols'],
        (bool)$params['lowercase']
    );

    DB::insert(prefixTable('tokens'), [
        'user_id' => (int) $session->get('user-id'),
        'token' => $token,
        'reason' => $params['reason'],
        'creation_timestamp' => time(),
        'end_timestamp' => time() + (int)$params['duration'],
    ]);

    return prepareSuccessResponse(['token' => $token]);
}

/**
 * Helper function to get and validate input
 */
function getValidatedInput(array $data, string $key, int $filter)
{
    if (!isset($data[$key])) {
        return false;
    }
    return filter_var($data[$key], $filter);
}

/**
 * Helper function to get POST input with default value
 */
function getPostInput(string $name, int $filter)
{
    return filter_input(INPUT_POST, $name, $filter);
}

/**
 * Prepares a success response
 */
function prepareSuccessResponse(array $data = []): string
{
    $response = array_merge(['error' => false], $data);
    return prepareExchangedData($response, 'encode');
}

/**
 * Prepares an error response
 */
function prepareErrorResponse(string $message): string
{
    return prepareExchangedData([
        'error' => true,
        'message' => $message
    ], 'encode');
}



function utilsHandler(string $post_type, array|null|string $dataReceived, array $SETTINGS): string
{
    switch ($post_type) {
        /*
         * generate_an_otp
         */
        case 'generate_an_otp'://action_utils
            return generateAnOTP(
                (string) filter_var($dataReceived['label'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                (bool) filter_var($dataReceived['with_qrcode'], FILTER_VALIDATE_BOOLEAN),
                (string) filter_var($dataReceived['secret_key'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            );


        /*
         * Default case
         */
        default :
            return prepareExchangedData(
                array(
                    'error' => true,
                ),
                'encode'
            );
    }
}

/**
 * Permits to set the user ready
 *
 * @param integer $userid
 * @param string $dir
 * @return string
 */
function userIsReady(int $userid, string $dir): string
{
    DB::update(
        prefixTable('users'),
        array(
            'is_ready_for_usage' => 1,
        ),
        'id = %i',
        $userid
    );

    // Send back
    return prepareExchangedData(
        array(
            'error' => false,
        ),
        'encode'
    ); 
}


/**
 * Permits to set the user ready
 *
 * @param integer $userid
 * @return string
 */
function userGetSessionTime(int $userid, string $dir, int $maximum_session_expiration_time): string
{
    $session = SessionManager::getSession();
    // Send back
    return prepareExchangedData(
        array(
            'error' => false,
            'timestamp' => $session->get('user-session_duration'),
            'max_time_to_add' => intdiv((($maximum_session_expiration_time*60) - ((int) $session->get('user-session_duration') - time())), 60),
            'max_session_duration' => $maximum_session_expiration_time,
        ),
        'encode'
    ); 
}

/**
 * Save the user's IP
 *
 * @param integer $userID
 * @param string $action
 * @return string
 */
function userSaveIp(int $userID, string $action): string
{
    if ($action === 'perform') {
        DB::update(
            prefixTable('users'),
            array(
                'user_ip' => getClientIpServer(),
                'user_ip_lastdate' => time(),
            ),
            'id = %i',
            $userID
        );
    }

    return prepareExchangedData(
        array(
            'error' => false,
        ),
        'encode'
    );
}

/**
 * Provides the number of items
 *
 * @param int   $userId     User ID
 * @param array $SETTINGS   TeampassSettings
 *
 * @return string
 */
function getNumberOfItemsToTreat(
    int $userId,
    array $SETTINGS
): string
{
    // get number of items
    DB::queryFirstRow(
        'SELECT increment_id
        FROM ' . prefixTable('sharekeys_items') .
        ' WHERE user_id = %i',
        $userId
    );

    // Send back
    return prepareExchangedData(
        array(
            'error' => false,
            'nbItems' => DB::count(),
        ),
        'encode'
    );
}


/**
 * 
 */
function changePassword(
    string $post_new_password,
    string $post_current_password,
    int $post_password_complexity,
    string $post_change_request,
    int $post_user_id,
    array $SETTINGS
): string
{
    $session = SessionManager::getSession();
    
    // Create password hash
    $passwordManager = new PasswordManager();
    $post_new_password_hashed = $passwordManager->hashPassword($post_new_password);

    // Load user's language
    $lang = new Language($session->get('user-language') ?? 'english');

    // User has decided to change is PW
    if ($post_change_request === 'reset_user_password_expected'
        || $post_change_request === 'user_decides_to_change_password'
    ) {
        // Check that current user is correct
        if ((int) $post_user_id !== (int) $session->get('user-id')) {
            return prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
        }

        // check if expected security level is reached
        $dataUser = DB::queryFirstRow(
            'SELECT *
            FROM ' . prefixTable('users') . ' WHERE id = %i',
            $post_user_id
        );

        // check if badly written
        $dataUser['fonction_id'] = array_filter(
            explode(',', str_replace(';', ',', $dataUser['fonction_id']))
        );
        $dataUser['fonction_id'] = implode(',', $dataUser['fonction_id']);
        DB::update(
            prefixTable('users'),
            array(
                'fonction_id' => $dataUser['fonction_id'],
            ),
            'id = %i',
            $post_user_id
        );

        if (empty($dataUser['fonction_id']) === false) {
            $data = DB::queryFirstRow(
                'SELECT complexity
                FROM ' . prefixTable('roles_title') . '
                WHERE id IN (' . $dataUser['fonction_id'] . ')
                ORDER BY complexity DESC'
            );
        } else {
            // In case user has no roles yet
            $data = array();
            $data['complexity'] = 0;
        }

        if ((int) $post_password_complexity < (int) $data['complexity']) {
            return prepareExchangedData(
                array(
                    'error' => true,
                    'message' => '<div style="margin:10px 0 10px 15px;">' . $lang->get('complexity_level_not_reached') . '.<br>' .
                        $lang->get('expected_complexity_level') . ': <b>' . TP_PW_COMPLEXITY[$data['complexity']][1] . '</b></div>',
                ),
                'encode'
            );
        }

        // Check that the 2 passwords are differents
        if ($post_current_password === $post_new_password) {
            return prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('password_already_used'),
                ),
                'encode'
            );
        }

        // update sessions
        $session->set('user-last_pw_change', mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('y')));
        $session->set('user-validite_pw', 1);

        // BEfore updating, check that the pwd is correct
        if ($passwordManager->verifyPassword($post_new_password_hashed, $post_new_password) === true && empty($dataUser['private_key']) === false) {
            $special_action = 'none';
            if ($post_change_request === 'reset_user_password_expected') {
                $session->set('user-private_key', decryptPrivateKey($post_current_password, $dataUser['private_key']));
            }

            // update DB
            DB::update(
                prefixTable('users'),
                array(
                    'pw' => $post_new_password_hashed,
                    'last_pw_change' => mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('y')),
                    'last_pw' => $post_current_password,
                    'special' => $special_action,
                    'private_key' => encryptPrivateKey($post_new_password, $session->get('user-private_key')),
                ),
                'id = %i',
                $post_user_id
            );
            // update LOG
            logEvents($SETTINGS, 'user_mngt', 'at_user_pwd_changed', (string) $session->get('user-id'), $session->get('user-login'), $post_user_id);

            // Send back
            return prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );
        }
        // Send back
        return prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('pw_hash_not_correct'),
            ),
            'encode'
        );
    }
    return prepareExchangedData(
        array(
            'error' => true,
            'message' => $lang->get('error_not_allowed_to'),
        ),
        'encode'
    );
}

function generateQRCode(
    $post_id,
    $post_demand_origin,
    $post_send_mail,
    $post_login,
    $post_pwd,
    $post_token,
    array $SETTINGS
): string
{
    // Load user's language
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');

    // is this allowed by setting
    if (isKeyExistingAndEqual('ga_reset_by_user', 0, $SETTINGS) === true
        && (null === $post_demand_origin || $post_demand_origin !== 'users_management_list')
    ) {
        // User cannot ask for a new code
        return prepareExchangedData(
            array(
                'error' => true,
                'message' => "113 ".$lang->get('error_not_allowed_to')." - ".isKeyExistingAndEqual('ga_reset_by_user', 1, $SETTINGS),
            ),
            'encode'
        );
    }
    
    // Check if user exists
    if (isValueSetNullEmpty($post_id) === true) {
        // Get data about user
        $dataUser = DB::queryFirstRow(
            'SELECT id, email, pw
            FROM ' . prefixTable('users') . '
            WHERE login = %s',
            $post_login
        );
    } else {
        $dataUser = DB::queryFirstRow(
            'SELECT id, login, email, pw
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_id
        );
        $post_login = $dataUser['login'];
    }
    // Get number of returned users
    $counter = DB::count();

    // Do treatment
    if ($counter === 0) {
        // Not a registered user !
        logEvents($SETTINGS, 'failed_auth', 'user_not_exists', '', stripslashes($post_login), stripslashes($post_login));
        return prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('no_user'),
                'tst' => 1,
            ),
            'encode'
        );
    }

    $passwordManager = new PasswordManager();
    if (
        isSetArrayOfValues([$post_pwd, $dataUser['pw']]) === true
        && $passwordManager->verifyPassword($dataUser['pw'], $post_pwd) === false
        && $post_demand_origin !== 'users_management_list'
    ) {
        // checked the given password
        logEvents($SETTINGS, 'failed_auth', 'password_is_not_correct', '', stripslashes($post_login), stripslashes($post_login));
        return prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('no_user'),
                'tst' => $post_demand_origin,
            ),
            'encode'
        );
    }
    
    if (empty($dataUser['email']) === true) {
        return prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('no_email_set'),
            ),
            'encode'
        );
    }

    // Check if token already used
    $dataToken = DB::queryFirstRow(
        'SELECT end_timestamp, reason
        FROM ' . prefixTable('tokens') . '
        WHERE token = %s AND user_id = %i',
        $post_token,
        $dataUser['id']
    );
    $tokenId = '';
    if (DB::count() > 0 && is_null($dataToken['end_timestamp']) === false && $dataToken['reason'] === 'auth_qr_code') {
        // This token has already been used
        return prepareExchangedData(
            array(
                'error' => true,
                'message' => 'TOKEN already used',//$lang->get('no_email_set'),
            ),
            'encode'
        );
    } elseif(DB::count() === 0) {
        // Store token for this action
        DB::insert(
            prefixTable('tokens'),
            array(
                'user_id' => (int) $dataUser['id'],
                'token' => $post_token,
                'reason' => 'auth_qr_code',
                'creation_timestamp' => time(),
            )
        );
        $tokenId = DB::insertId();
    }
    
    // generate new GA user code
    $tfa = new TwoFactorAuth($SETTINGS['ga_website_name']);
    $gaSecretKey = $tfa->createSecret();
    $gaTemporaryCode = GenerateCryptKey(12, false, true, true, false, true);

    DB::update(
        prefixTable('users'),
        [
            'ga' => $gaSecretKey,
            'ga_temporary_code' => $gaTemporaryCode,
        ],
        'id = %i',
        $dataUser['id']
    );

    // Log event
    logEvents($SETTINGS, 'user_connection', 'at_2fa_google_code_send_by_email', (string) $dataUser['id'], stripslashes($post_login), stripslashes($post_login));

    // Update token status
    DB::update(
        prefixTable('tokens'),
        [
            'end_timestamp' => time(),
        ],
        'id = %i',
        $tokenId
    );

    // send mail?
    if ((int) $post_send_mail === 1) {
        prepareSendingEmail(
            $lang->get('email_ga_subject'),
            str_replace(
                '#2FACode#',
                $gaTemporaryCode,
                $lang->get('email_ga_text')
            ),
            $dataUser['email']
        );

        // send back
        return prepareExchangedData(
            array(
                'error' => false,
                'message' => $post_send_mail,
                'email' => $dataUser['email'],
                'email_result' => str_replace(
                    '#email#',
                    '<b>' . obfuscateEmail($dataUser['email']) . '</b>',
                    addslashes($lang->get('admin_email_result_ok'))
                ),
            ),
            'encode'
        );
    }
    
    // send back
    return prepareExchangedData(
        array(
            'error' => false,
            'message' => '',
            'email' => $dataUser['email'],
            'email_result' => str_replace(
                '#email#',
                '<b>' . obfuscateEmail($dataUser['email']) . '</b>',
                addslashes($lang->get('admin_email_result_ok'))
            ),
        ),
        'encode'
    );
}

function sendEmailsNotSent(
    array $SETTINGS
)
{
    $emailSettings = new EmailSettings($SETTINGS);
    $emailService = new EmailService();

    if (isKeyExistingAndEqual('enable_send_email_on_user_login', 1, $SETTINGS) === true) {
        $row = DB::queryFirstRow(
            'SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s',
            'cron',
            'sending_emails'
        );

        if ((int) (time() - $row['valeur']) >= 300 || (int) $row['valeur'] === 0) {
            $rows = DB::query(
                'SELECT *
                FROM ' . prefixTable('emails') .
                ' WHERE status != %s',
                'sent'
            );
            foreach ($rows as $record) {
                // Send email
                $ret = json_decode(
                    $emailService->sendMail(
                        $record['subject'],
                        $record['body'],
                        $record['receivers'],
                        $emailSettings
                    ),
                    true
                );

                // update item_id in files table
                DB::update(
                    prefixTable('emails'),
                    array(
                        'status' => $ret['error'] === 'error_mail_not_send' ? 'not_sent' : 'sent',
                    ),
                    'timestamp = %s',
                    $record['timestamp']
                );
            }
        }
        // update cron time
        DB::update(
            prefixTable('misc'),
            array(
                'valeur' => time(),
                'updated_at' => time(),
            ),
            'intitule = %s AND type = %s',
            'sending_emails',
            'cron'
        );
    }
}


function refreshUserItemsSeenList(
    array $SETTINGS
): string
{
    $session = SessionManager::getSession();

    // get list of last items seen
    $arr_html = array();
    $rows = DB::query(
        'SELECT i.id AS id, i.label AS label, i.id_tree AS id_tree, l.date, i.perso AS perso, i.restricted_to AS restricted
        FROM ' . prefixTable('log_items') . ' AS l
        RIGHT JOIN ' . prefixTable('items') . ' AS i ON (l.id_item = i.id)
        WHERE l.action = %s AND l.id_user = %i
        ORDER BY l.date DESC
        LIMIT 0, 100',
        'at_shown',
        $session->get('user-id')
    );
    if (DB::count() > 0) {
        foreach ($rows as $record) {
            if (in_array($record['id']->id, array_column($arr_html, 'id')) === false) {
                array_push(
                    $arr_html,
                    array(
                        'id' => $record['id'],
                        'label' => htmlspecialchars(stripslashes(htmlspecialchars_decode($record['label'], ENT_QUOTES)), ENT_QUOTES),
                        'tree_id' => $record['id_tree'],
                        'perso' => $record['perso'],
                        'restricted' => $record['restricted'],
                    )
                );
                if (count($arr_html) >= (int) $SETTINGS['max_latest_items']) {
                    break;
                }
            }
        }
    }

    // get wainting suggestions
    $nb_suggestions_waiting = 0;
    if (isKeyExistingAndEqual('enable_suggestion', 1, $SETTINGS) === true
        && ((int) $session->get('user-admin') === 1 || (int) $session->get('user-manager') === 1)
    ) {
        DB::query('SELECT * FROM ' . prefixTable('suggestion'));
        $nb_suggestions_waiting = DB::count();
    }

    return json_encode(
        array(
            'error' => '',
            'existing_suggestions' => $nb_suggestions_waiting,
            'html_json' => $arr_html,
        ),
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    );
}

function sendingStatistics(
    array $SETTINGS
): void
{
    $session = SessionManager::getSession();
    if (
        isSetArrayOfValues([$SETTINGS['send_statistics_items'], $SETTINGS['send_stats_time']]) === true
        && isKeyExistingAndEqual('send_stats', 1, $SETTINGS) === true
        && (int) ($SETTINGS['send_stats_time'] + TP_ONE_DAY_SECONDS) > time()
    ) {
        // get statistics data
        $stats_data = getStatisticsData($SETTINGS);

        // get statistics items to share
        $statsToSend = [];
        $statsToSend['ip'] = $_SERVER['SERVER_ADDR'];
        $statsToSend['timestamp'] = time();
        foreach (array_filter(explode(';', $SETTINGS['send_statistics_items'])) as $data) {
            if ($data === 'stat_languages') {
                $tmp = '';
                foreach ($stats_data[$data] as $key => $value) {
                    $tmp .= $tmp === '' ? $key . '-' . $value : ',' . $key . '-' . $value;
                }
                $statsToSend[$data] = $tmp;
            } elseif ($data === 'stat_country') {
                $tmp = '';
                foreach ($stats_data[$data] as $key => $value) {
                    $tmp .= $tmp === '' ? $key . '-' . $value : ',' . $key . '-' . $value;
                }
                $statsToSend[$data] = $tmp;
            } else {
                $statsToSend[$data] = $stats_data[$data];
            }
        }

        // connect to Teampass Statistics database
        $link2 = new MeekroDB(
            'teampass.pw',
            'teampass_user',
            'ZMlEfRzKzFLZNzie',
            'teampass_followup',
            '3306',
            'utf8'
        );

        $link2->insert(
            'statistics',
            $statsToSend
        );

        // update table misc with current timestamp
        DB::update(
            prefixTable('misc'),
            array(
                'valeur' => time(),
                'updated_at' => time(),
            ),
            'type = %s AND intitule = %s',
            'admin',
            'send_stats_time'
        );

        //permits to test only once by session
        $session->set('system-send_stats_done', 1);
        $SETTINGS['send_stats_time'] = time();
    }
}

function generateBugReport(
    array $data,
    array $SETTINGS
): array
{
    $excludedVars = array(
        'bck_script_passkey',
        'email_smtp_server',
        'email_auth_username',
        'email_auth_pwd',
        'email_from',
        'onthefly-restore-key',
        'onthefly-backup-key',
        'ldap_password',
        'ldap_hosts',
        'proxy_ip',
        'ldap_bind_passwd',
        'syslog_host',
        'duo_akey',
        'duo_ikey',
        'duo_skey',
        'duo_host',
        'oauth2_client_id',
        'oauth2_tenant_id',
        'oauth2_client_secret',
        'oauth2_client_token',
        'oauth2_client_endpoint',
    );

    // Load user's language
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');

    // Anonymisation URL
    $urlFound = $SETTINGS['cpassman_url'] ?? '';
    $anonymUrl = '';
    if (!empty($urlFound)) {
        $parsed = parse_url($urlFound);
        $anonymUrl = $parsed['scheme'] . '://<anonym_url>' . ($parsed['path'] ?? '');
    }

    // Filtrage et tri des réglages
    $listOfOptions = [];
    ksort($SETTINGS);

    foreach ($SETTINGS as $key => $value) {
        if (in_array($key, $excludedVars, true)) {
            $value = '<removed>';
        } elseif (!empty($anonymUrl)) {
            $value = str_replace($urlFound, $anonymUrl, (string) $value);
        }

        $listOfOptions[] = "'$key' => '$value'";
    }
    $listOfOptions = implode("\n", $listOfOptions);

    // Récupère dernière erreur PHP
    $phpError = error_get_last();
    $phpErrorMsg = $phpError
        ? $phpError['message'] . ' - ' . $phpError['file'] . ' (' . $phpError['line'] . ')'
        : $lang->get('no_error_logged');

    // Dernières erreurs Teampass
    $teampassErrors = [];
    $errors = DB::query(
        'SELECT label, date AS error_date
         FROM ' . prefixTable('log_system') . "
         WHERE `type` = 'error'
         ORDER BY `date` DESC
         LIMIT 10"
    );

    foreach ($errors as $record) {
        $teampassErrors[] = ' * ' . date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['error_date']) . ' - ' . $record['label'];
    }
    $teampassErrors = count($teampassErrors) > 0 ? implode("\n", $teampassErrors) : $lang->get('no_error_logged');

    // Version DB
    if (!defined('DB_PASSWD_CLEAR')) {
        define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD));
    }
    $dbLink = @mysqli_connect(DB_HOST, DB_USER, DB_PASSWD_CLEAR, DB_NAME, (int) DB_PORT);
    $dbVersion = $dbLink ? mysqli_get_server_info($dbLink) : $lang->get('undefined');

    // Now prepare text
    $reportContent = <<<REPORT
### Page on which it happened
{$data['current_page']}

### Steps to reproduce
1.
2.
3.

### Expected behaviour
Tell us what should happen


### Actual behaviour
Tell us what happens instead

### Server configuration
**Operating system**: {php_uname()}
**Web server**: {$_SERVER['SERVER_SOFTWARE']}
**Database**: {$dbVersion}
**PHP version**: {PHP_VERSION}
**Teampass version**: {TP_VERSION}.{TP_VERSION_MINOR}

**Teampass configuration variables:**
```
{$listOfOptions}
```

**Updated from an older Teampass or fresh install:**

### Client configuration
**Browser**: {$data['browser_name']} - {$data['browser_version']}
**Operating system**: {$data['os']} - {$data['os_archi']} bits

### Logs

#### Web server error log
```
{$phpErrorMsg}
```

#### Teampass 10 last system errors
```
{$teampassErrors}
```

#### Log from the web-browser developer console (CTRL + SHIFT + i)
```
Insert the log here and especially the answer of the query that failed.
```
REPORT;

    // Now prepare text
    $reportData = [
        'report' => $reportContent,
        'error' => false
    ];

    return $reportData;
}

/**
 * Check that the user password is valid
 *
 * @param integer $post_user_id
 * @param string $post_user_password
 * @param array $SETTINGS
 * @return string
 */
function isUserPasswordCorrect(
    int $post_user_id,
    string $post_user_password,
    array $SETTINGS
): string
{
    $session = SessionManager::getSession();
    // Load user's language
    $lang = new Language($session->get('user-language') ?? 'english');
    
    if (isUserIdValid($post_user_id) === true) {
        // Check if user exists
        $userInfo = DB::queryFirstRow(
            'SELECT public_key, private_key, pw, auth_type
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        if (DB::count() > 0 && empty($userInfo['private_key']) === false) {
            // Get itemKey from current user
            // Get one item
            $currentUserKey = DB::queryFirstRow(
                'SELECT object_id, share_key, increment_id
                FROM ' . prefixTable('sharekeys_items') . ' AS si
                INNER JOIN ' . prefixTable('items') . ' AS i ON  (i.id = si.object_id)
                INNER JOIN ' . prefixTable('nested_tree') . ' AS nt ON  (i.id_tree = nt.id)
                WHERE user_id = %i AND nt.personal_folder = %i',
                $post_user_id,
                0
            );
            
            if (DB::count() === 0) {
                // This user has no items
                // let's consider no items in DB
                return prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => '',
                        'debug' => '',
                    ),
                    'encode'
                );
            }

            if ($currentUserKey !== null) {
                // Decrypt itemkey with user key
                // use old password to decrypt private_key
                $session->set('user-private_key', decryptPrivateKey($post_user_password, $userInfo['private_key']));
                $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $session->get('user-private_key'));

                //echo $post_user_password."  --  ".$userInfo['private_key']. ";;";

                if (empty(base64_decode($itemKey)) === false) {
                    // GOOD password
                    return prepareExchangedData(
                        array(
                            'error' => false,
                            'message' => '',
                            'debug' => '',
                        ),
                        'encode'
                    );
                }
            }

            // use the password check
            $passwordManager = new PasswordManager();
            
            if ($passwordManager->verifyPassword($userInfo['pw'], htmlspecialchars_decode($post_user_password)) === true) {
                // GOOD password
                return prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => '',
                        'debug' => '',
                    ),
                    'encode'
                );
            }
        }
    }

    return prepareExchangedData(
        array(
            'error' => true,
            'message' => $lang->get('password_is_not_correct'),
        ),
        'encode'
    );
}

function changePrivateKeyEncryptionPassword(
    int $post_user_id,
    string $post_current_code,
    string $post_new_code,
    string $post_action_type,
    array $SETTINGS
): string
{
    $session = SessionManager::getSession();
    // Load user's language
    $lang = new Language($session->get('user-language') ?? 'english');
    
    if (empty($post_new_code) === true) {
        // no user password
        return prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('error_bad_credentials'),
                'debug' => '',
            ),
            'encode'
        );
    }

    if (isUserIdValid($post_user_id) === true) {
        // Get user info
        $userData = DB::queryFirstRow(
            'SELECT private_key
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        if (DB::count() > 0 && empty($userData['private_key']) === false) {
            if ($post_action_type === 'encrypt_privkey_with_user_password') {
                // Here the user has his private key encrypted with an OTC.
                // We need to encrypt it with his real password
                $privateKey = decryptPrivateKey($post_new_code, $userData['private_key']);
                $hashedPrivateKey = encryptPrivateKey($post_current_code, $privateKey);
            } else {
                $privateKey = decryptPrivateKey($post_current_code, $userData['private_key']);
                $hashedPrivateKey = encryptPrivateKey($post_new_code, $privateKey);
            }

            // Should fail here to avoid break user private key.
            if (strlen($privateKey) === 0 || strlen($hashedPrivateKey) < 30) {
                if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
                    error_log("Error reencrypt user private key. User ID: {$post_user_id}, Given OTP: '{$post_current_code}'");
                }
                return prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_otp_secret'),
                        'debug' => '',
                    ),
                    'encode'
                );
            }

            // Update user account
            DB::update(
                prefixTable('users'),
                array(
                    'private_key' => $hashedPrivateKey,
                    'special' => 'none',
                    'otp_provided' => 1,
                ),
                'id = %i',
                $post_user_id
            );

            $session->set('user-private_key', $privateKey);
        }

        // Return
        return prepareExchangedData(
            array(
                'error' => false,
                'message' => '',
            ),
            'encode'
        );
    }
    
    return prepareExchangedData(
        array(
            'error' => true,
            'message' => $lang->get('error_no_user'),
            'debug' => '',
        ),
        'encode'
    );
}

function initializeUserPassword(
    int $post_user_id,
    string $post_special,
    string $post_user_password,
    bool $post_self_change,
    array $SETTINGS
): string
{
    // Load user's language
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');
    
    if (isUserIdValid($post_user_id) === true) {
        // Get user info
        $userData = DB::queryFirstRow(
            'SELECT email, auth_type, login
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        if (DB::count() > 0 && empty($userData['email']) === false) {
            // If user pwd is empty then generate a new one and send it to user
            if (empty($post_user_password) === true) {
                // Generate new password
                $post_user_password = generateQuickPassword();
            }

            // If LDAP enabled, then
            // check that this password is correct
            $continue = true;
            if ($userData['auth_type'] === 'ldap' && (int) $SETTINGS['ldap_mode'] === 1) {
                $continue = ldapCheckUserPassword(
                    $userData['login'],
                    $post_user_password,
                    $SETTINGS
                );
            }

            if ($continue === true) {
                // Only change if email is successfull
                $passwordManager = new PasswordManager();
                // GEnerate new keys
                $userKeys = generateUserKeys($post_user_password);

                // Update user account
                DB::update(
                    prefixTable('users'),
                    array(
                        'special' => $post_special,
                        'pw' => $passwordManager->hashPassword($post_user_password),
                        'public_key' => $userKeys['public_key'],
                        'private_key' => $userKeys['private_key'],
                        'last_pw_change' => time(),
                    ),
                    'id = %i',
                    $post_user_id
                );

                // Return
                return prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => '',
                        'user_pwd' => $post_user_password,
                        'user_email' => $userData['email'],
                    ),
                    'encode'
                );
            }
            // Return error
            return prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('no_email_set'),
                    'debug' => '',
                    'self_change' => $post_self_change,
                ),
                'encode'
            );
        }

        // Error
        return prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('no_email_set'),
                'debug' => '',
            ),
            'encode'
        );
    }
    
    return prepareExchangedData(
        array(
            'error' => true,
            'message' => $lang->get('error_no_user'),
            'debug' => '',
        ),
        'encode'
    );
}

function generateOneTimeCode(
    int $post_user_id
): string
{
    // Load user's language
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');
    
    if (isUserIdValid($post_user_id) === true) {
        // Get user info
        $userData = DB::queryFirstRow(
            'SELECT email, auth_type, login
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        if (DB::count() > 0 && empty($userData['email']) === false) {
            // Generate pwd
            $password = generateQuickPassword();

            // GEnerate new keys
            $userKeys = generateUserKeys($password);

            // Save in DB
            DB::update(
                prefixTable('users'),
                array(
                    'public_key' => $userKeys['public_key'],
                    'private_key' => $userKeys['private_key'],
                    'special' => 'generate-keys',
                ),
                'id=%i',
                $post_user_id
            );

            return prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                    'code' => $password,
                    'visible_otp' => ADMIN_VISIBLE_OTP_ON_LDAP_IMPORT,
                ),
                'encode'
            );
        }
        
        return prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('no_email_set'),
            ),
            'encode'
        );
    }
        
    return prepareExchangedData(
        array(
            'error' => true,
            'message' => $lang->get('error_no_user'),
        ),
        'encode'
    );
}

function startReEncryptingUserSharekeys(
    int $post_user_id,
    bool $post_self_change,
    array $SETTINGS
): string
{
    // Load user's language
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');
    
    if (isUserIdValid($post_user_id) === true) {
        // Check if user exists
        DB::queryFirstRow(
            'SELECT *
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        if (DB::count() > 0) {
            // CLear old sharekeys
            if ($post_self_change === false) {
                deleteUserObjetsKeys($post_user_id, $SETTINGS);
            }

            // Continu with next step
            return prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                    'step' => 'step1',
                    'userId' => $post_user_id,
                    'start' => 0,
                    'self_change' => $post_self_change,
                ),
                'encode'
            );
        }
        // Nothing to do
        return prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('error_no_user'),
            ),
            'encode'
        );
    }

    return prepareExchangedData(
        array(
            'error' => true,
            'message' => $lang->get('error_no_user'),
        ),
        'encode'
    );
}

/**
 * Permits to encrypt user's keys
 *
 * @param integer $post_user_id
 * @param boolean $post_self_change
 * @param string $post_action
 * @param integer $post_start
 * @param integer $post_length
 * @param array $SETTINGS
 * @return string
 */
function continueReEncryptingUserSharekeys(
    int     $post_user_id,
    bool    $post_self_change,
    string  $post_action,
    int     $post_start,
    int     $post_length,
    array   $SETTINGS
): string
{
    // Load user's language
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');
    
    if (isUserIdValid($post_user_id) === true) {
        // Check if user exists
        $userInfo = DB::queryFirstRow(
            'SELECT public_key
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        if (isset($userInfo['public_key']) === true) {
            $return = [];

            // WHAT STEP TO PERFORM?
            if ($post_action === 'step0') {
                // CLear old sharekeys
                if ($post_self_change === false) {
                    deleteUserObjetsKeys($post_user_id, $SETTINGS);
                }

                $return['post_action'] = 'step10';
            }
            
            // STEP 1 - ITEMS
            elseif ($post_action === 'step10') {
                $return = continueReEncryptingUserSharekeysStep10(
                    $post_user_id,
                    $post_self_change,
                    $post_action,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS
                );
            }

            // STEP 2 - LOGS
            elseif ($post_action === 'step20') {
                $return = continueReEncryptingUserSharekeysStep20(
                    $post_user_id,
                    $post_self_change,
                    $post_action,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS
                );
            }

            // STEP 3 - FIELDS
            elseif ($post_action === 'step30') {
                $return = continueReEncryptingUserSharekeysStep30(
                    $post_user_id,
                    $post_self_change,
                    $post_action,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS
                );
            }
            
            // STEP 4 - SUGGESTIONS
            elseif ($post_action === 'step40') {
                $return = continueReEncryptingUserSharekeysStep40(
                    $post_user_id,
                    $post_self_change,
                    $post_action,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS
                );
            }
            
            // STEP 5 - FILES
            elseif ($post_action === 'step50') {
                $return = continueReEncryptingUserSharekeysStep50(
                    $post_user_id,
                    $post_self_change,
                    $post_action,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS
                );
            }
            
            // STEP 6 - PERSONAL ITEMS
            elseif ($post_action === 'step60') {
                $return = continueReEncryptingUserSharekeysStep60(
                    $post_user_id,
                    $post_self_change,
                    $post_action,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS
                );
            }
            
            // Continu with next step
            return prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                    'step' => isset($return['post_action']) === true ? $return['post_action'] : '',
                    'start' => isset($return['next_start']) === true ? $return['next_start'] : 0,
                    'userId' => $post_user_id,
                    'self_change' => $post_self_change,
                ),
                'encode'
            );
        }
        
        // Nothing to do
        return prepareExchangedData(
            array(
                'error' => false,
                'message' => '',
                'step' => 'finished',
                'start' => 0,
                'userId' => $post_user_id,
                'self_change' => $post_self_change,
            ),
            'encode'
        );
    }
    
    // Nothing to do
    return prepareExchangedData(
        array(
            'error' => true,
            'message' => $lang->get('error_no_user'),
            'extra' => $post_user_id,
        ),
        'encode'
    );
}

function continueReEncryptingUserSharekeysStep10(
    int $post_user_id,
    bool $post_self_change,
    string $post_action,
    int $post_start,
    int $post_length,
    string $user_public_key,
    array $SETTINGS
): array 
{
    $session = SessionManager::getSession();
    // Loop on items
    $rows = DB::query(
        'SELECT id, pw
        FROM ' . prefixTable('items') . '
        WHERE perso = 0
        LIMIT ' . $post_start . ', ' . $post_length
    );
    foreach ($rows as $record) {
        // Get itemKey from current user
        $currentUserKey = DB::queryFirstRow(
            'SELECT share_key, increment_id
            FROM ' . prefixTable('sharekeys_items') . '
            WHERE object_id = %i AND user_id = %i',
            $record['id'],
            $session->get('user-id')
        );

        // do we have any input? (#3481)
        if ($currentUserKey === null || count($currentUserKey) === 0) {
            continue;
        }

        // Decrypt itemkey with admin key
        $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $session->get('user-private_key'));
        
        // Encrypt Item key
        $share_key_for_item = encryptUserObjectKey($itemKey, $user_public_key);
        
        // Save the key in DB
        if ($post_self_change === false) {
            DB::insert(
                prefixTable('sharekeys_items'),
                array(
                    'object_id' => (int) $record['id'],
                    'user_id' => (int) $post_user_id,
                    'share_key' => $share_key_for_item,
                )
            );
        } else {
            // Get itemIncrement from selected user
            if ((int) $post_user_id !== (int) $session->get('user-id')) {
                $currentUserKey = DB::queryFirstRow(
                    'SELECT increment_id
                    FROM ' . prefixTable('sharekeys_items') . '
                    WHERE object_id = %i AND user_id = %i',
                    $record['id'],
                    $post_user_id
                );

                if (DB::count() > 0) {
                    // NOw update
                    DB::update(
                        prefixTable('sharekeys_items'),
                        array(
                            'share_key' => $share_key_for_item,
                        ),
                        'increment_id = %i',
                        $currentUserKey['increment_id']
                    );
                } else {
                    DB::insert(
                        prefixTable('sharekeys_items'),
                        array(
                            'object_id' => (int) $record['id'],
                            'user_id' => (int) $post_user_id,
                            'share_key' => $share_key_for_item,
                        )
                    );
                }
            }
        }
    }

    // SHould we change step?
    DB::query(
        'SELECT *
        FROM ' . prefixTable('items') . '
        WHERE perso = 0'
    );

    $next_start = (int) $post_start + (int) $post_length;
    return [
        'next_start' => $next_start > DB::count() ? 0 : $next_start,
        'post_action' => $next_start > DB::count() ? 'step20' : 'step10',
    ];
}

function continueReEncryptingUserSharekeysStep20(
    int $post_user_id,
    bool $post_self_change,
    string $post_action,
    int $post_start,
    int $post_length,
    string $user_public_key,
    array $SETTINGS
): array
{
    $session = SessionManager::getSession();
    // Loop on logs
    $rows = DB::query(
        'SELECT increment_id
        FROM ' . prefixTable('log_items') . '
        WHERE raison LIKE "at_pw :%" AND encryption_type = "teampass_aes"
        LIMIT ' . $post_start . ', ' . $post_length
    );
    foreach ($rows as $record) {
        // Get itemKey from current user
        $currentUserKey = DB::queryFirstRow(
            'SELECT share_key
            FROM ' . prefixTable('sharekeys_logs') . '
            WHERE object_id = %i AND user_id = %i',
            $record['increment_id'],
            $session->get('user-id')
        );

        // do we have any input? (#3481)
        if ($currentUserKey === null || count($currentUserKey) === 0) {
            continue;
        }

        // Decrypt itemkey with admin key
        $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $session->get('user-private_key'));

        // Encrypt Item key
        $share_key_for_item = encryptUserObjectKey($itemKey, $user_public_key);

        // Save the key in DB
        if ($post_self_change === false) {
            DB::insert(
                prefixTable('sharekeys_logs'),
                array(
                    'object_id' => (int) $record['increment_id'],
                    'user_id' => (int) $post_user_id,
                    'share_key' => $share_key_for_item,
                )
            );
        } else {
            // Get itemIncrement from selected user
            if ((int) $post_user_id !== (int) $session->get('user-id')) {
                $currentUserKey = DB::queryFirstRow(
                    'SELECT increment_id
                    FROM ' . prefixTable('sharekeys_items') . '
                    WHERE object_id = %i AND user_id = %i',
                    $record['id'],
                    $post_user_id
                );
            }

            // NOw update
            DB::update(
                prefixTable('sharekeys_logs'),
                array(
                    'share_key' => $share_key_for_item,
                ),
                'increment_id = %i',
                $currentUserKey['increment_id']
            );
        }
    }

    // SHould we change step?
    DB::query(
        'SELECT increment_id
        FROM ' . prefixTable('log_items') . '
        WHERE raison LIKE "at_pw :%" AND encryption_type = "teampass_aes"'
    );

    $next_start = (int) $post_start + (int) $post_length;
    return [
        'next_start' => $next_start > DB::count() ? 0 : $next_start,
        'post_action' => $next_start > DB::count() ? 'step30' : 'step20',
    ];
}

function continueReEncryptingUserSharekeysStep30(
    int $post_user_id,
    bool $post_self_change,
    string $post_action,
    int $post_start,
    int $post_length,
    string $user_public_key,
    array $SETTINGS
): array
{
    $session = SessionManager::getSession();
    // Loop on fields
    $rows = DB::query(
        'SELECT id
        FROM ' . prefixTable('categories_items') . '
        WHERE encryption_type = "teampass_aes"
        LIMIT ' . $post_start . ', ' . $post_length
    );
    foreach ($rows as $record) {
        // Get itemKey from current user
        $currentUserKey = DB::queryFirstRow(
            'SELECT share_key
            FROM ' . prefixTable('sharekeys_fields') . '
            WHERE object_id = %i AND user_id = %i',
            $record['id'],
            $session->get('user-id')
        );

        // do we have any input? (#3481)
        if ($currentUserKey === null || count($currentUserKey) === 0) {
            continue;
        }

        // Decrypt itemkey with admin key
        $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $session->get('user-private_key'));

        // Encrypt Item key
        $share_key_for_item = encryptUserObjectKey($itemKey, $user_public_key);

        // Save the key in DB
        if ($post_self_change === false) {
            DB::insert(
                prefixTable('sharekeys_fields'),
                array(
                    'object_id' => (int) $record['id'],
                    'user_id' => (int) $post_user_id,
                    'share_key' => $share_key_for_item,
                )
            );
        } else {
            // Get itemIncrement from selected user
            if ((int) $post_user_id !== (int) $session->get('user-id')) {
                $currentUserKey = DB::queryFirstRow(
                    'SELECT increment_id
                    FROM ' . prefixTable('sharekeys_items') . '
                    WHERE object_id = %i AND user_id = %i',
                    $record['id'],
                    $post_user_id
                );
            }

            // NOw update
            DB::update(
                prefixTable('sharekeys_fields'),
                array(
                    'share_key' => $share_key_for_item,
                ),
                'increment_id = %i',
                $currentUserKey['increment_id']
            );
        }
    }

    // SHould we change step?
    DB::query(
        'SELECT *
        FROM ' . prefixTable('categories_items') . '
        WHERE encryption_type = "teampass_aes"'
    );

    $next_start = (int) $post_start + (int) $post_length;
    return [
        'next_start' => $next_start > DB::count() ? 0 : $next_start,
        'post_action' => $next_start > DB::count() ? 'step40' : 'step30',
    ];
}

function continueReEncryptingUserSharekeysStep40(
    int $post_user_id,
    bool $post_self_change,
    string $post_action,
    int $post_start,
    int $post_length,
    string $user_public_key,
    array $SETTINGS
): array
{
    $session = SessionManager::getSession();
    // Loop on suggestions
    $rows = DB::query(
        'SELECT id
        FROM ' . prefixTable('suggestion') . '
        LIMIT ' . $post_start . ', ' . $post_length
    );
    foreach ($rows as $record) {
        // Get itemKey from current user
        $currentUserKey = DB::queryFirstRow(
            'SELECT share_key
            FROM ' . prefixTable('sharekeys_suggestions') . '
            WHERE object_id = %i AND user_id = %i',
            $record['id'],
            $session->get('user-id')
        );

        // do we have any input? (#3481)
        if ($currentUserKey === null || count($currentUserKey) === 0) {
            continue;
        }

        // Decrypt itemkey with admin key
        $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $session->get('user-private_key'));

        // Encrypt Item key
        $share_key_for_item = encryptUserObjectKey($itemKey, $user_public_key);

        // Save the key in DB
        if ($post_self_change === false) {
            DB::insert(
                prefixTable('sharekeys_suggestions'),
                array(
                    'object_id' => (int) $record['id'],
                    'user_id' => (int) $post_user_id,
                    'share_key' => $share_key_for_item,
                )
            );
        } else {
            // Get itemIncrement from selected user
            if ((int) $post_user_id !== (int) $session->get('user-id')) {
                $currentUserKey = DB::queryFirstRow(
                    'SELECT increment_id
                    FROM ' . prefixTable('sharekeys_items') . '
                    WHERE object_id = %i AND user_id = %i',
                    $record['id'],
                    $post_user_id
                );
            }

            // NOw update
            DB::update(
                prefixTable('sharekeys_suggestions'),
                array(
                    'share_key' => $share_key_for_item,
                ),
                'increment_id = %i',
                $currentUserKey['increment_id']
            );
        }
    }

    // SHould we change step?
    DB::query(
        'SELECT *
        FROM ' . prefixTable('suggestion')
    );

    $next_start = (int) $post_start + (int) $post_length;
    return [
        'next_start' => $next_start > DB::count() ? 0 : $next_start,
        'post_action' => $next_start > DB::count() ? 'step50' : 'step40',
    ];
}

function continueReEncryptingUserSharekeysStep50(
    int $post_user_id,
    bool $post_self_change,
    string $post_action,
    int $post_start,
    int $post_length,
    string $user_public_key,
    array $SETTINGS
): array
{
    $session = SessionManager::getSession();
    // Loop on files
    $rows = DB::query(
        'SELECT id
        FROM ' . prefixTable('files') . '
        WHERE status = "' . TP_ENCRYPTION_NAME . '"
        LIMIT ' . $post_start . ', ' . $post_length
    ); //aes_encryption
    foreach ($rows as $record) {
        // Get itemKey from current user
        $currentUserKey = DB::queryFirstRow(
            'SELECT share_key
            FROM ' . prefixTable('sharekeys_files') . '
            WHERE object_id = %i AND user_id = %i',
            $record['id'],
            $session->get('user-id')
        );

        // do we have any input? (#3481)
        if ($currentUserKey === null || count($currentUserKey) === 0) {
            continue;
        }

        // Decrypt itemkey with admin key
        $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $session->get('user-private_key'));

        // Encrypt Item key
        $share_key_for_item = encryptUserObjectKey($itemKey, $user_public_key);

        // Save the key in DB
        if ($post_self_change === false) {
            DB::insert(
                prefixTable('sharekeys_files'),
                array(
                    'object_id' => (int) $record['id'],
                    'user_id' => (int) $post_user_id,
                    'share_key' => $share_key_for_item,
                )
            );
        } else {
            // Get itemIncrement from selected user
            if ((int) $post_user_id !== (int) $session->get('user-id')) {
                $currentUserKey = DB::queryFirstRow(
                    'SELECT increment_id
                    FROM ' . prefixTable('sharekeys_items') . '
                    WHERE object_id = %i AND user_id = %i',
                    $record['id'],
                    $post_user_id
                );
            }

            // NOw update
            DB::update(
                prefixTable('sharekeys_files'),
                array(
                    'share_key' => $share_key_for_item,
                ),
                'increment_id = %i',
                $currentUserKey['increment_id']
            );
        }
    }

    // SHould we change step?
    DB::query(
        'SELECT *
        FROM ' . prefixTable('files') . '
        WHERE status = "' . TP_ENCRYPTION_NAME . '"'
    );

    $next_start = (int) $post_start + (int) $post_length;
    return [
        'next_start' => $next_start > DB::count() ? 0 : $next_start,
        'post_action' => $next_start > DB::count() ? 'step60' : 'step50',
    ];
}

function continueReEncryptingUserSharekeysStep60(
    int $post_user_id,
    bool $post_self_change,
    string $post_action,
    int $post_start,
    int $post_length,
    string $user_public_key,
    array $SETTINGS
): array
{
    $session = SessionManager::getSession();
    // IF USER IS NOT THE SAME
    if ((int) $post_user_id === (int) $session->get('user-id')) {
        return [
            'next_start' => 0,
            'post_action' => 'finished',
        ];
    }
    
    // Loop on persoanl items
    if (count($session->get('user-personal_folders')) > 0) {
        $rows = DB::query(
            'SELECT id, pw
            FROM ' . prefixTable('items') . '
            WHERE perso = 1 AND id_tree IN %ls AND encryption_type = %s
            LIMIT ' . $post_start . ', ' . $post_length,
            $session->get('user-personal_folders'),
            "defuse"
        );
        foreach ($rows as $record) {
            // Get itemKey from current user
            $currentUserKey = DB::queryFirstRow(
                'SELECT share_key, increment_id
                FROM ' . prefixTable('sharekeys_items') . '
                WHERE object_id = %i AND user_id = %i',
                $record['id'],
                $session->get('user-id')
            );

            // Decrypt itemkey with admin key
            $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $session->get('user-private_key'));

            // Encrypt Item key
            $share_key_for_item = encryptUserObjectKey($itemKey, $user_public_key);

            // Save the key in DB
            if ($post_self_change === false) {
                DB::insert(
                    prefixTable('sharekeys_items'),
                    array(
                        'object_id' => (int) $record['id'],
                        'user_id' => (int) $post_user_id,
                        'share_key' => $share_key_for_item,
                    )
                );
            } else {
                // Get itemIncrement from selected user
                if ((int) $post_user_id !== (int) $session->get('user-id')) {
                    $currentUserKey = DB::queryFirstRow(
                        'SELECT increment_id
                        FROM ' . prefixTable('sharekeys_items') . '
                        WHERE object_id = %i AND user_id = %i',
                        $record['id'],
                        $post_user_id
                    );
                }

                // NOw update
                DB::update(
                    prefixTable('sharekeys_items'),
                    array(
                        'share_key' => $share_key_for_item,
                    ),
                    'increment_id = %i',
                    $currentUserKey['increment_id']
                );
            }
        }
    }

    // SHould we change step?
    DB::query(
        'SELECT *
        FROM ' . prefixTable('items') . '
        WHERE perso = 0'
    );

    $next_start = (int) $post_start + (int) $post_length;
    return [
        'next_start' => $next_start > DB::count() ? 0 : $next_start,
        'post_action' => $next_start > DB::count() ? 'finished' : 'step60',
    ];
}

function migrateTo3_DoUserPersonalItemsEncryption(
    int $post_user_id,
    int $post_start,
    int $post_length,
    int $post_counterItemsToTreat,
    string $post_user_psk,
    array $SETTINGS
) {
    $next_step = 'psk';
    
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');
    
    if (isUserIdValid($post_user_id) === true) {
        // Check if user exists
        $userInfo = DB::queryFirstRow(
            'SELECT public_key, encrypted_psk
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        if (DB::count() > 0) {
            // check if psk is correct.
            if (empty($userInfo['encrypted_psk']) === false) {//echo $post_user_psk." ;; ".$userInfo['encrypted_psk']." ;; ";
                $user_key_encoded = defuse_validate_personal_key(
                    html_entity_decode($post_user_psk), // convert tspecial string back to their original characters due to FILTER_SANITIZE_FULL_SPECIAL_CHARS
                    $userInfo['encrypted_psk']
                );

                if (strpos($user_key_encoded, "Error ") !== false) {
                    return prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => $lang->get('bad_psk'),
                        ),
                        'encode'
                    );
                }

                // Get number of user's personal items with no AES encryption
                if ($post_counterItemsToTreat === -1) {
                    DB::query(
                        'SELECT id
                        FROM ' . prefixTable('items') . '
                        WHERE perso = 1 AND id_tree IN %ls AND encryption_type != %s',
                        $session->get('user-personal_folders'),
                        'teampass_aes'
                    );
                    $countUserPersonalItems = DB::count();
                } else {
                    $countUserPersonalItems = $post_counterItemsToTreat;
                }

                // Loop on persoanl items
                $rows = DB::query(
                    'SELECT id, pw
                    FROM ' . prefixTable('items') . '
                    WHERE perso = 1 AND id_tree IN %ls AND encryption_type != %s
                    LIMIT ' . $post_length,
                    $session->get('user-personal_folders'),
                    'teampass_aes'
                );
                foreach ($rows as $record) {
                    // Decrypt with Defuse
                    $passwd = cryption(
                        $record['pw'],
                        $user_key_encoded,
                        'decrypt',
                        $SETTINGS
                    );

                    // Encrypt with Object Key
                    $cryptedStuff = doDataEncryption(html_entity_decode($passwd['string']));

                    // Store new password in DB
                    DB::update(
                        prefixTable('items'),
                        array(
                            'pw' => $cryptedStuff['encrypted'],
                            'encryption_type' => 'teampass_aes',
                        ),
                        'id = %i',
                        $record['id']
                    );

                    // Insert in DB the new object key for this item by user
                    DB::insert(
                        prefixTable('sharekeys_items'),
                        array(
                            'object_id' => (int) $record['id'],
                            'user_id' => (int) $post_user_id,
                            'share_key' => encryptUserObjectKey($cryptedStuff['objectKey'], $userInfo['public_key']),
                        )
                    );


                    // Does this item has Files?
                    // Loop on files
                    $rows = DB::query(
                        'SELECT id, file
                        FROM ' . prefixTable('files') . '
                        WHERE status != %s
                        AND id_item = %i',
                        TP_ENCRYPTION_NAME,
                        $record['id']
                    );
                    //aes_encryption
                    foreach ($rows as $record2) {
                        // Now decrypt the file
                        prepareFileWithDefuse(
                            'decrypt',
                            $SETTINGS['path_to_upload_folder'] . '/' . $record2['file'],
                            $SETTINGS['path_to_upload_folder'] . '/' . $record2['file'] . '.delete',
                            $post_user_psk
                        );

                        // Encrypt the file
                        $encryptedFile = encryptFile($record2['file'] . '.delete', $SETTINGS['path_to_upload_folder']);

                        DB::update(
                            prefixTable('files'),
                            array(
                                'file' => $encryptedFile['fileHash'],
                                'status' => TP_ENCRYPTION_NAME,
                            ),
                            'id = %i',
                            $record2['id']
                        );

                        // Save key
                        DB::insert(
                            prefixTable('sharekeys_files'),
                            array(
                                'object_id' => (int) $record2['id'],
                                'user_id' => (int) $session->get('user-id'),
                                'share_key' => encryptUserObjectKey($encryptedFile['objectKey'], $session->get('user-public_key')),
                            )
                        );

                        // Unlink original file
                        unlink($SETTINGS['path_to_upload_folder'] . '/' . $record2['file']);
                    }
                }

                // SHould we change step?
                $next_start = (int) $post_start + (int) $post_length;
                DB::query(
                    'SELECT id
                    FROM ' . prefixTable('items') . '
                    WHERE perso = 1 AND id_tree IN %ls AND encryption_type != %s',
                    $session->get('user-personal_folders'),
                    'teampass_aes'
                );
                if (DB::count() === 0 || ($next_start - $post_length) >= $countUserPersonalItems) {
                    // Now update user
                    DB::update(
                        prefixTable('users'),
                        array(
                            'special' => 'none',
                            'upgrade_needed' => 0,
                            'encrypted_psk' => '',
                        ),
                        'id = %i',
                        $post_user_id
                    );

                    $next_step = 'finished';
                    $next_start = 0;
                }

                // Continu with next step
                return prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => '',
                        'step' => $next_step,
                        'start' => $next_start,
                        'userId' => $post_user_id
                    ),
                    'encode'
                );
            }
        }
        
        // Nothing to do
        return prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('error_no_user'),
            ),
            'encode'
        );
    }
    
    // Nothing to do
    return prepareExchangedData(
        array(
            'error' => true,
            'message' => $lang->get('error_no_user'),
        ),
        'encode'
    );
}


function getUserInfo(
    int $post_user_id,
    array $SETTINGS
)
{
    // Load user's language
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');
    
    if (isUserIdValid($post_user_id) === true) {
        // Get user info
        $userData = DB::queryFirstRow(
            'SELECT special, auth_type, is_ready_for_usage, ongoing_process_id, otp_provided, keys_recovery_time
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        if (DB::count() > 0) {
            return prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                    'queryResults' => $userData,
                ),
                'encode'
            );
        }
    }
    return prepareExchangedData(
        array(
            'error' => true,
            'message' => $lang->get('error_no_user'),
        ),
        'encode'
    );
}

/**
 * Change user auth password
 *
 * @param integer $post_user_id
 * @param string $post_current_pwd
 * @param string $post_new_pwd
 * @param array $SETTINGS
 * @return string
 */
function changeUserAuthenticationPassword(
    int $post_user_id,
    string $post_current_pwd,
    string $post_new_pwd,
    array $SETTINGS
)
{
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');
 
    if (isUserIdValid($post_user_id) === true) {
        // Get user info
        $userData = DB::queryFirstRow(
            'SELECT auth_type, login, private_key
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        if (DB::count() > 0 && empty($userData['private_key']) === false) {
            // Now check if current password is correct
            // For this, just check if it is possible to decrypt the privatekey
            // And compare it to the one in session
            try {
                $privateKey = decryptPrivateKey($post_current_pwd, $userData['private_key']);
            } catch (Exception $e) {
                return prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('bad_password'),
                    ),
                    'encode'
                );
            }

            $lang = new Language($session->get('user-language') ?? 'english');

            if ($session->get('user-private_key') === $privateKey) {
                // Encrypt it with new password
                $hashedPrivateKey = encryptPrivateKey($post_new_pwd, $privateKey);

                // Generate new hash for auth password
                $passwordManager = new PasswordManager();

                // Prepare variables
                $newPw = $passwordManager->hashPassword($post_new_pwd);

                // Update user account
                DB::update(
                    prefixTable('users'),
                    array(
                        'private_key' => $hashedPrivateKey,
                        'pw' => $newPw,
                        'special' => 'none',
                        'last_pw_change' => time(),
                    ),
                    'id = %i',
                    $post_user_id
                );

                $session->set('user-private_key', $privateKey);

                return prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => $lang->get('done'),'',
                    ),
                    'encode'
                );
            }
            
            // ERROR
            return prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('bad_password'),
                ),
                'encode'
            );
        }
    }
        
    return prepareExchangedData(
        array(
            'error' => true,
            'message' => $lang->get('error_no_user'),
        ),
        'encode'
    );
}

/**
 * Change user LDAP auth password
 *
 * @param integer $post_user_id
 * @param string $post_previous_pwd
 * @param string $post_current_pwd
 * @return string
 */            
function changeUserLDAPAuthenticationPassword(
    int $post_user_id,
    string $post_previous_pwd,
    string $post_current_pwd
)
{
    $session = SessionManager::getSession();
    // Load user's language
    $lang = new Language($session->get('user-language') ?? 'english');
    
    if (isUserIdValid($post_user_id) === true) {
        // Get user info
        $userData = DB::queryFirstRow(
            'SELECT auth_type, login, private_key, special
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );

        if (DB::count() > 0 && empty($userData['private_key']) === false) {
            // Now check if current password is correct (only if not ldap)
            if ($userData['auth_type'] === 'ldap' && $userData['special'] === 'auth-pwd-change') {
                // As it is a change for an LDAP user
                
                // Now check if current password is correct
                // For this, just check if it is possible to decrypt the privatekey
                // And compare it to the one in session
                $privateKey = decryptPrivateKey($post_previous_pwd, $userData['private_key']);

                // Encrypt it with new password
                $hashedPrivateKey = encryptPrivateKey($post_current_pwd, $privateKey);

                // Update user account
                DB::update(
                    prefixTable('users'),
                    array(
                        'private_key' => $hashedPrivateKey,
                        'special' => 'none',
                    ),
                    'id = %i',
                    $post_user_id
                );

                $session->set('user-private_key', $privateKey);

                return prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => $lang->get('done'),'',
                    ),
                    'encode'
                );
            }

            // For this, just check if it is possible to decrypt the privatekey
            // And try to decrypt one existing key
            $privateKey = decryptPrivateKey($post_previous_pwd, $userData['private_key']);

            if (empty($privateKey) === true) {
                return prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('password_is_not_correct'),
                    ),
                    'encode'
                );
            }
            // Get one itemKey from current user
            $currentUserKey = DB::queryFirstRow(
                'SELECT ski.share_key, ski.increment_id, l.id_user
                FROM ' . prefixTable('sharekeys_items') . ' AS ski
                INNER JOIN ' . prefixTable('log_items') . ' AS l ON ski.object_id = l.id_item
                WHERE ski.user_id = %i
                ORDER BY RAND()
                LIMIT 1',
                $post_user_id
            );

            if (is_countable($currentUserKey) && count($currentUserKey) > 0) {
                // Decrypt itemkey with user key
                // use old password to decrypt private_key
                $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $privateKey);
                
                if (empty(base64_decode($itemKey)) === false) {
                    // GOOD password
                    // Encrypt it with current password
                    $hashedPrivateKey = encryptPrivateKey($post_current_pwd, $privateKey);
                    
                    // Update user account
                    DB::update(
                        prefixTable('users'),
                        array(
                            'private_key' => $hashedPrivateKey,
                            'special' => 'none',
                        ),
                        'id = %i',
                        $post_user_id
                    );
                    
                    $lang = new Language($session->get('user-language') ?? 'english');
                    $session->set('user-private_key', $privateKey);

                    return prepareExchangedData(
                        array(
                            'error' => false,
                            'message' => $lang->get('done'),
                        ),
                        'encode'
                    );
                }
            }
            
            // ERROR
            return prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('bad_password'),
                ),
                'encode'
            );
        }
    }

    // ERROR
    return prepareExchangedData(
        array(
            'error' => true,
            'message' => $lang->get('error_no_user'),
        ),
        'encode'
    );
}

/**
 * Change user LDAP auth password
 *
 * @param integer $post_user_id
 * @param string $post_current_pwd
 * @param string $post_new_pwd
 * @param array $SETTINGS
 * @return string
 */
function increaseSessionDuration(
    int $duration
): string
{
    $session = SessionManager::getSession();
    // check if session is not already expired.
    if ($session->get('user-session_duration') > time()) {
        // Calculate end of session
        $session->set('user-session_duration', (int) $session->get('user-session_duration') + $duration);
        // Update table
        DB::update(
            prefixTable('users'),
            array(
                'session_end' => $session->get('user-session_duration'),
            ),
            'id = %i',
            $session->get('user-id')
        );
        // Return data
        return '[{"new_value":"' . $session->get('user-session_duration') . '"}]';
    }
    
    return '[{"new_value":"expired"}]';
}

function generateAnOTP(string $label, bool $with_qrcode = false, string $secretKey = ''): string
{
    // generate new secret
    $tfa = new TwoFactorAuth();
    if ($secretKey === '') {
        $secretKey = $tfa->createSecret();
    }

    // generate new QR
    if ($with_qrcode === true) {
        $qrcode = $tfa->getQRCodeImageAsDataUri(
            $label,
            $secretKey
        );
    }

    // ERROR
    return prepareExchangedData(
        array(
            'error' => false,
            'message' => '',
            'secret' => $secretKey,
            'qrcode' => $qrcode ?? '',
        ),
        'encode'
    );
}