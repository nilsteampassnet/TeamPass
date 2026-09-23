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
 * @file      ldap.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use LdapRecord\Connection;
use LdapRecord\Container;
use voku\helper\AntiXSS;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\LdapExtra\LdapExtra;
use TeampassClasses\LdapExtra\OpenLdapExtra;
use TeampassClasses\LdapExtra\ActiveDirectoryExtra;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

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
    $checkUserAccess->userAccessPage('ldap') === false ||
    $checkUserAccess->checkSession() === false
) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
error_reporting(E_ERROR);
set_time_limit(0);

// --------------------------------- //

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
// Read raw, like an encrypted payload: each field is sanitized after decoding.
// FILTER_SANITIZE_FULL_SPECIAL_CHARS acts as htmlentities() and stored "é" as "&eacute;".
$post_data = filter_input(INPUT_POST, 'data', FILTER_UNSAFE_RAW);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

switch ($post_type) {
    // Audit the saved settings and return the findings, translated
    case 'ldap_check_settings':
        // Check KEY and rights
        if ($post_key !== $session->get('key')) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }

        echo prepareExchangedData(
            array(
                'error' => false,
                'findings' => ldapTranslateConfigFindings(ldapConfigAudit($SETTINGS), $lang),
            ),
            'encode'
        );

    break;

    //CASE for getting informations about the tool
    case 'ldap_test_configuration':
        // Check KEY and rights
        if ($post_key !== $session->get('key')) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }

        // decrypt and retrieve data in JSON format
        $dataReceived = prepareExchangedData(
            $post_data,
            'decode'
        );

        // prepare variables
        $post_username = filter_var($dataReceived['username'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $post_password = $dataReceived['password']; // No filtering as password can contain special chars

        // Check if data is correct
        if (empty($post_username) === true && empty($post_password) === true) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => "Error : ".$lang->get('error_empty_data'),
                ),
                'encode'
            );
            break;
        }

        // Walk the real login path (sources/ldap.functions.php). The test used to carry its own
        // copy of the search and of the bind, so it could report a success on a configuration no
        // user can log in with.
        $testResult = ldapRunConfigurationTest(
            (string) $post_username,
            (string) $post_password,
            $SETTINGS,
            $lang
        );

        // deepcode ignore ServerLeak: No important data is sent and is encrypted before being sent
        echo prepareExchangedData(
            array(
                'error' => $testResult['error'],
                'message' => $testResult['message'],
                'steps' => ldapTranslateTestSteps($testResult['steps'], $lang),
                'findings' => ldapTranslateConfigFindings($testResult['findings'], $lang),
            ),
            'encode'
        );

    break;
}

/**
 * Resolve the language keys of the configuration findings.
 *
 * The logic module stays free of translation so it can be unit-tested; the label and the
 * "apply this value" suggestion are assembled here.
 *
 * @param array $findings Output of ldapConfigAudit()
 * @param Language $lang  Language instance
 *
 * @return array<int, array{field: string, severity: string, label: string, suggestion: string}>
 */
function ldapTranslateConfigFindings(array $findings, Language $lang): array
{
    $translated = [];
    foreach ($findings as $finding) {
        $translated[] = [
            'field' => $finding['field'],
            'severity' => $finding['severity'],
            'label' => $lang->get($finding['code']),
            'suggestion' => $finding['suggestion'],
        ];
    }

    return $translated;
}

/**
 * Resolve the language keys of the test steps.
 *
 * @param array $steps   Output of ldapRunConfigurationTest()
 * @param Language $lang Language instance
 *
 * @return array<int, array{label: string, status: string, detail: string}>
 */
function ldapTranslateTestSteps(array $steps, Language $lang): array
{
    $translated = [];
    foreach ($steps as $step) {
        $translated[] = [
            'label' => $lang->get($step['code']),
            'status' => $step['status'],
            'detail' => $step['detail'],
        ];
    }

    return $translated;
}
