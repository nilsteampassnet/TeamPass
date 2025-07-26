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
 * @file      QRCodeService.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\PasswordManager\PasswordManager;
use TeampassClasses\Language\Language;
use RobThree\Auth\TwoFactorAuth;

class QRCodeService
{
    private array $settings;
    private Language $lang;
    private PasswordManager $passwordManager;

    public function __construct(array $settings)
    {
        $this->settings = $settings;
        $session = SessionManager::getSession();
        $this->lang = new Language($session->get('user-language') ?? 'english');
        $this->passwordManager = new PasswordManager();
    }

    public function generateForUser(array $params): string
    {
        if ($this->isResetBlocked($params['origin'])) {
            return $this->respondError(
                "113 " . $this->lang->get('error_not_allowed_to') . " - " .
                isKeyExistingAndEqual('ga_reset_by_user', 1, $this->settings)
            );
        }

        $user = $this->getUser($params['id'], $params['login']);
        if (!$user) {
            logEvents($this->settings, 'failed_auth', 'user_not_exists', '', stripslashes($params['login']), stripslashes($params['login']));
            return $this->respondError($this->lang->get('no_user'), ['tst' => 1]);
        }

        if (
            isSetArrayOfValues([$params['password'], $user['pw']]) &&
            !$this->passwordManager->verifyPassword($user['pw'], $params['password']) &&
            $params['origin'] !== 'users_management_list'
        ) {
            logEvents($this->settings, 'failed_auth', 'password_is_not_correct', '', stripslashes($params['login']), stripslashes($params['login']));
            return $this->respondError($this->lang->get('no_user'), ['tst' => $params['origin']]);
        }

        if (empty($user['email'])) {
            return $this->respondError($this->lang->get('no_email_set'));
        }

        $tokenId = $this->handleToken($params['token'], $user['id']);
        if ($tokenId === false) {
            return $this->respondError('TOKEN already used');
        }

        [$secretKey, $temporaryCode] = $this->generateAndStoreSecret($user['id']);

        logEvents($this->settings, 'user_connection', 'at_2fa_google_code_send_by_email', (string)$user['id'], stripslashes($params['login']), stripslashes($params['login']));
        DB::update(prefixTable('tokens'), ['end_timestamp' => time()], 'id = %i', $tokenId);

        if ((int)$params['send_mail'] === 1) {
            $this->send2FACodeByEmail($user['email'], $temporaryCode);

            return $this->respondSuccess($user['email'], $params['send_mail']);
        }

        return $this->respondSuccess($user['email']);
    }

    private function isResetBlocked(?string $origin): bool
    {
        return isKeyExistingAndEqual('ga_reset_by_user', 0, $this->settings)
            && ($origin === null || $origin !== 'users_management_list');
    }

    private function getUser($id, string &$login): ?array
    {
        if (isValueSetNullEmpty($id)) {
            $user = DB::queryFirstRow(
                'SELECT id, email, pw FROM ' . prefixTable('users') . ' WHERE login = %s',
                $login
            );
        } else {
            $user = DB::queryFirstRow(
                'SELECT id, login, email, pw FROM ' . prefixTable('users') . ' WHERE id = %i',
                $id
            );
            $login = $user['login'] ?? $login;
        }

        return DB::count() > 0 ? $user : null;
    }

    private function handleToken(string $token, int $userId): int|false
    {
        $dataToken = DB::queryFirstRow(
            'SELECT end_timestamp, reason FROM ' . prefixTable('tokens') . ' WHERE token = %s AND user_id = %i',
            $token,
            $userId
        );

        if (
            DB::count() > 0 &&
            !is_null($dataToken['end_timestamp']) &&
            $dataToken['reason'] === 'auth_qr_code'
        ) {
            return false;
        }

        if (DB::count() === 0) {
            DB::insert(prefixTable('tokens'), [
                'user_id' => $userId,
                'token' => $token,
                'reason' => 'auth_qr_code',
                'creation_timestamp' => time(),
            ]);
            return DB::insertId();
        }

        return (int) DB::queryFirstField('SELECT id FROM ' . prefixTable('tokens') . ' WHERE token = %s AND user_id = %i', $token, $userId);
    }

    private function generateAndStoreSecret(int $userId): array
    {
        $tfa = new TwoFactorAuth($this->settings['ga_website_name']);
        $secret = $tfa->createSecret();
        $passwordManager = new PasswordManager();
        $code = $passwordManager->generatePassword(12, false, true, true, false, true);

        DB::update(prefixTable('users'), [
            'ga' => $secret,
            'ga_temporary_code' => $code,
        ], 'id = %i', $userId);

        return [$secret, $code];
    }

    private function send2FACodeByEmail(string $email, string $code): void
    {
        prepareSendingEmail(
            $this->lang->get('email_ga_subject'),
            str_replace('#2FACode#', $code, $this->lang->get('email_ga_text')),
            $email
        );
    }

    private function respondError(string $message, array $extra = []): string
    {
        return prepareExchangedData(array_merge(['error' => true, 'message' => $message], $extra), 'encode');
    }

    private function respondSuccess(string $email, string $message = ''): string
    {
        return prepareExchangedData([
            'error' => false,
            'message' => $message,
            'email' => $email,
            'email_result' => str_replace(
                '#email#',
                '<b>' . obfuscateEmail($email) . '</b>',
                addslashes($this->lang->get('admin_email_result_ok'))
            ),
        ], 'encode');
    }
}
