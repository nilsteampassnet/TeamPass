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
 * @file      OneTimeCodeService.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;

class OneTimeCodeService
{
    private Language $lang;

    public function __construct()
    {
        $session = SessionManager::getSession();
        $this->lang = new Language($session->get('user-language') ?? 'english');
    }

    public function generateForUser(int $userId): string
    {
        if (!isUserIdValid($userId)) {
            return $this->respondError($this->lang->get('error_no_user'));
        }

        $user = $this->getUser($userId);

        if (!$user || empty($user['email'])) {
            return $this->respondError($this->lang->get('no_email_set'));
        }

        $password = generateQuickPassword();
        $keys = generateUserKeys($password);

        $this->storeUserKeys($userId, $keys);

        return $this->respondSuccess($password);
    }

    private function getUser(int $userId): ?array
    {
        $user = DB::queryFirstRow(
            'SELECT email, auth_type, login FROM ' . prefixTable('users') . ' WHERE id = %i',
            $userId
        );

        return DB::count() > 0 ? $user : null;
    }

    private function storeUserKeys(int $userId, array $keys): void
    {
        DB::update(
            prefixTable('users'),
            [
                'public_key' => $keys['public_key'],
                'private_key' => $keys['private_key'],
                'special' => 'generate-keys',
            ],
            'id=%i',
            $userId
        );
    }

    private function respondError(string $message): string
    {
        return prepareExchangedData([
            'error' => true,
            'message' => $message,
        ], 'encode');
    }

    private function respondSuccess(string $code): string
    {
        return prepareExchangedData([
            'error' => false,
            'message' => '',
            'code' => $code,
            'visible_otp' => ADMIN_VISIBLE_OTP_ON_LDAP_IMPORT,
        ], 'encode');
    }
}
