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
 * @file      reset-password.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__ . '/main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();
$lang = new Language($session->get('user-language') ?? ($SETTINGS['default_language'] ?? 'english'));

date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
// The link is a credential: keep it out of the Referer sent to any resource of this page.
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

/**
 * Renders the standalone page shown by this endpoint.
 * The endpoint is unauthenticated and self-contained, like the One-Time-View one, so it
 * carries its own minimal layout rather than the application chrome.
 *
 * @param Language $lang
 * @param array<string, mixed> $SETTINGS
 * @param string $body Already-escaped HTML body
 *
 * @return void
 */
function resetPasswordRenderPage($lang, array $SETTINGS, string $body): void
{
    $homeUrl = htmlspecialchars(rtrim((string) ($SETTINGS['cpassman_url'] ?? ''), '/') . '/index.php', ENT_QUOTES, 'UTF-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars(TP_TOOL_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="./plugins/adminlte/css/adminlte.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <link rel="stylesheet" href="./plugins/fontawesome-free/css/fontawesome.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <link rel="stylesheet" href="./plugins/fontawesome-free/css/solid.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
</head>
<body class="hold-transition login-page">
    <div class="login-box">
        <div class="card">
            <div class="card-body login-card-body">
                <?php echo $body; ?>
                <p class="mt-4 mb-0 text-center">
                    <a href="<?php echo $homeUrl; ?>"><?php echo htmlspecialchars($lang->get('home'), ENT_QUOTES, 'UTF-8'); ?></a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
    <?php
}

/**
 * Builds the message block shown when the link cannot be used.
 *
 * @param Language $lang
 *
 * @return string
 */
function resetPasswordInvalidBlock($lang): string
{
    return '<div class="alert alert-danger mb-0">'
        . '<i class="fa-solid fa-triangle-exclamation mr-2"></i>'
        . htmlspecialchars($lang->get('forgot_local_password_link_invalid'), ENT_QUOTES, 'UTF-8')
        . '</div>';
}

$token = (string) $request->query->get('token', '');
if ($request->isMethod('POST') === true) {
    $token = (string) $request->request->get('token', '');
}

// Possession of the token is the whole proof: it was sent to the address registered on the
// account, so only someone controlling that mailbox can reach the reset below.
$tokenRow = getForgotLocalPasswordTokenRow($token);
if ($tokenRow === null) {
    resetPasswordRenderPage($lang, $SETTINGS, resetPasswordInvalidBlock($lang));
    exit;
}

// getUserCompleteData() is used rather than a local query so that a soft-deleted account
// (deleted_at set) is filtered out here exactly as it is on the request side.
$userInfo = getUserCompleteData(null, (int) $tokenRow['user_id']);

// Eligibility is re-evaluated at consumption time: the account may have been disabled, switched
// to another auth type, soft-deleted, or started a key process since the link was issued.
if (is_array($userInfo) === false
    || getForgotLocalPasswordContext($userInfo, $SETTINGS)['available'] !== true
) {
    deleteForgotLocalPasswordToken((int) $tokenRow['id']);
    resetPasswordRenderPage($lang, $SETTINGS, resetPasswordInvalidBlock($lang));
    exit;
}

// A GET only shows what is about to happen. The reset is performed on the POST of this form,
// so that a mail scanner or a link prefetcher cannot consume the token on the user's behalf.
if ($request->isMethod('POST') === false) {
    $body = '<p class="login-box-msg pt-0">'
        . htmlspecialchars($lang->get('forgot_local_password_confirm_intro'), ENT_QUOTES, 'UTF-8')
        . '</p>'
        . '<p class="text-center"><b>' . htmlspecialchars((string) $userInfo['login'], ENT_QUOTES, 'UTF-8') . '</b></p>'
        . '<form method="post" action="' . htmlspecialchars((string) ($_SERVER['REQUEST_URI'] ?? ''), ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">'
        . '<button type="submit" class="btn btn-primary btn-block">'
        . htmlspecialchars($lang->get('confirm'), ENT_QUOTES, 'UTF-8')
        . '</button></form>';
    resetPasswordRenderPage($lang, $SETTINGS, $body);
    exit;
}

// Consume the token before doing anything else: a failure below must not leave a replayable link.
deleteForgotLocalPasswordToken((int) $tokenRow['id']);

$temporaryPassword = GenerateCryptKey(20, false, true, true, false, true);
$resetResult = prepareExchangedData(
    handleUserKeys(
        (int) $userInfo['id'],
        $temporaryPassword,
        isset($SETTINGS['maximum_number_of_items_to_treat']) === true
            ? (int) $SETTINGS['maximum_number_of_items_to_treat']
            : NUMBER_ITEMS_IN_BATCH,
        '',
        true,
        false,
        true,
        false,
        '',
        false,
        '',
        '',
        false,
        'auth-pwd-change'
    ),
    'decode'
);

if (is_array($resetResult) !== true
    || (isset($resetResult['error']) === true && $resetResult['error'] === true)
) {
    resetPasswordRenderPage($lang, $SETTINGS, resetPasswordInvalidBlock($lang));
    exit;
}

// The password just changed, so the failures recorded against the old one are moot: clearing
// them spares a legitimate user a lockout wait. This is only reachable after the mailbox proof.
DB::delete(
    prefixTable('auth_failures'),
    'source = %s AND value = %s',
    'login',
    $userInfo['login']
);

$emailServerUrl = empty(trim((string) ($SETTINGS['email_server_url'] ?? ''))) === false
    ? (string) $SETTINGS['email_server_url']
    : (string) ($SETTINGS['cpassman_url'] ?? '');
$userLanguageName = trim((string) ($userInfo['user_language'] ?? ''));
if ($userLanguageName === '' || $userLanguageName === '0') {
    $userLanguageName = (string) ($SETTINGS['default_language'] ?? 'english');
}
$userLanguage = new Language($userLanguageName);
sendMailToUser(
    (string) $userInfo['email'],
    $userLanguage->get('forgot_local_password_email_body'),
    $userLanguage->get('forgot_local_password_email_subject'),
    [
        '#login#' => (string) $userInfo['login'],
        '#name#' => (string) ($userInfo['name'] ?? ''),
        '#lastname#' => (string) ($userInfo['lastname'] ?? ''),
        '#tp_link#' => $emailServerUrl,
    ],
    false,
    cryption($temporaryPassword, '', 'encrypt')['string'],
    trim(((string) ($userInfo['name'] ?? '')) . ' ' . ((string) ($userInfo['lastname'] ?? '')))
);

logEvents(
    $SETTINGS,
    'user_mngt',
    'at_user_pwd_changed',
    (string) $userInfo['id'],
    (string) $userInfo['login'],
    (string) $userInfo['id']
);

resetPasswordRenderPage(
    $lang,
    $SETTINGS,
    '<div class="alert alert-success mb-0">'
        . '<i class="fa-solid fa-circle-check mr-2"></i>'
        . htmlspecialchars($lang->get('forgot_local_password_reset_done'), ENT_QUOTES, 'UTF-8')
        . '</div>'
);
exit;
