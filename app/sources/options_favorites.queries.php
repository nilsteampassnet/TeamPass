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
 * @file      options_favorites.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\SessionManager\SessionManager;

require_once __DIR__ . '/main.functions.php';

loadClasses('DB');

$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => htmlspecialchars((string) $request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
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

echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('options') === false) {
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

/**
 * @param array<string, mixed> $payload
 */
function optionsFavoritesRespond(array $payload): never
{
    echo prepareExchangedData($payload, 'encode');
    exit;
}

/**
 * @param mixed $value
 */
function optionsFavoriteSanitizeKey($value): string
{
    $optionKey = trim((string) $value);
    if ($optionKey === '' || preg_match('/^[A-Za-z0-9_-]{1,100}$/', $optionKey) !== 1) {
        return '';
    }

    return $optionKey;
}

function optionsFavoriteGetNextPosition(int $userId): int
{
    $row = DB::queryFirstRow(
        'SELECT MAX(position) AS max_position FROM %l WHERE user_id = %i',
        prefixTable('users_options_favorites'),
        $userId,
    );

    return isset($row['max_position']) === true ? ((int) $row['max_position']) + 1 : 1;
}

$postType = (string) $request->request->get('type', '');
$postKey = (string) $request->request->get('key', '');
$postData = (string) $request->request->get('data', '');
$userId = (int) ($session->get('user-id') ?? 0);

if ($postKey !== (string) $session->get('key')) {
    optionsFavoritesRespond([
        'error' => true,
        'message' => $lang->get('key_is_not_correct'),
    ]);
}

if ((int) ($session->get('user-admin') ?? 0) !== 1) {
    optionsFavoritesRespond([
        'error' => true,
        'message' => $lang->get('error_not_allowed_to'),
    ]);
}

$dataReceived = [];
if ($postData !== '') {
    $decodedData = prepareExchangedData($postData, 'decode');
    if (is_array($decodedData) === true) {
        /** @var array<string, mixed> $decodedData */
        $dataReceived = $decodedData;
    }
}

switch ($postType) {
    case 'options_favorites_get':
        $rows = DB::query(
            'SELECT option_key FROM %l WHERE user_id = %i ORDER BY position ASC, created_at ASC',
            prefixTable('users_options_favorites'),
            $userId,
        );

        /** @var list<string> $optionKeys */
        $optionKeys = array_map(
            static fn (array $row): string => (string) ($row['option_key'] ?? ''),
            $rows,
        );

        optionsFavoritesRespond([
            'error' => false,
            'result' => [
                'option_keys' => array_values(array_filter($optionKeys)),
            ],
        ]);

    case 'options_favorites_add':
        $optionKey = optionsFavoriteSanitizeKey($dataReceived['option_key'] ?? '');
        if ($optionKey === '') {
            optionsFavoritesRespond([
                'error' => true,
                'message' => $lang->get('settings_favorite_invalid_key'),
            ]);
        }

        $exists = DB::queryFirstRow(
            'SELECT id FROM %l WHERE user_id = %i AND option_key = %s',
            prefixTable('users_options_favorites'),
            $userId,
            $optionKey,
        );

        if (empty($exists['id']) === true) {
            DB::insert(
                prefixTable('users_options_favorites'),
                [
                    'user_id' => $userId,
                    'option_key' => $optionKey,
                    'position' => optionsFavoriteGetNextPosition($userId),
                ],
            );
        }

        optionsFavoritesRespond([
            'error' => false,
            'result' => [
                'option_key' => $optionKey,
            ],
        ]);

    case 'options_favorites_remove':
        $optionKey = optionsFavoriteSanitizeKey($dataReceived['option_key'] ?? '');
        if ($optionKey === '') {
            optionsFavoritesRespond([
                'error' => true,
                'message' => $lang->get('settings_favorite_invalid_key'),
            ]);
        }

        DB::delete(
            prefixTable('users_options_favorites'),
            'user_id = %i AND option_key = %s',
            $userId,
            $optionKey,
        );

        optionsFavoritesRespond([
            'error' => false,
            'result' => [
                'option_key' => $optionKey,
            ],
        ]);

    default:
        optionsFavoritesRespond([
            'error' => true,
            'message' => $lang->get('server_answer_error'),
        ]);
}
