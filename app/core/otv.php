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
 * @file      otv.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\ConfigManager\ConfigManager;

require_once __DIR__ . '/../sources/main.functions.php';
require_once __DIR__ . '/../sources/otv_render_logic.php';
require_once __DIR__ . '/../sources/secure_send.functions.php';

$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');
$SETTINGS = (new ConfigManager())->getAllSettings();
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// The public endpoint has its own POST confirmation; it never enters authenticated routing.
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (!$request->isMethod('GET') && !$request->isMethod('POST')) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

$input = $request->isMethod('POST') ? $request->request->all() : $request->query->all();
$confirmations = (array) $session->get('otv-confirmations', []);
$page = secureSendPrepareRecipient($input, $request->getMethod(), $SETTINGS, $confirmations);
$session->set('otv-confirmations', $confirmations);
$parameters = $page['parameters'];
$link = $page['link'];
$result = $page['result'];
$error = $page['error'];
$token = $page['token'];
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title><?php echo $escape(TP_TOOL_NAME . ' - ' . $lang->get('secure_send')); ?></title>
    <link rel="stylesheet" href="./plugins/adminlte/css/adminlte.min.css">
</head>
<body class="hold-transition login-page">
    <main class="card card-outline card-primary m-3" style="width: min(700px, 95vw)">
        <div class="card-header text-center"><h1 class="h3"><?php echo $escape(TP_TOOL_NAME); ?></h1></div>
        <div class="card-body">
            <h2 class="h4 text-center"><?php echo $escape($lang->get('secure_send')); ?></h2>
            <?php if ($error !== '') { ?>
                <div class="alert alert-danger" role="alert"><?php echo $escape($lang->get($error)); ?></div>
            <?php } ?>
            <?php if ($token !== '' && $parameters !== null) { ?>
                <p><?php echo $escape($lang->get('secure_send_reveal_hint')); ?></p>
                <form method="post" action="index.php?otv=1" autocomplete="off">
                    <?php foreach ($parameters + ['confirmation' => $token] as $name => $value) { ?>
                        <input type="hidden" name="<?php echo $escape($name); ?>" value="<?php echo $escape($value); ?>">
                    <?php } ?>
                    <?php if ((int) ($link['has_passphrase'] ?? 0) === 1) { ?>
                        <div class="form-group">
                            <label for="passphrase"><?php echo $escape($lang->get('secure_send_enter_passphrase')); ?></label>
                            <input type="password" name="passphrase" id="passphrase" class="form-control" maxlength="1024" autocomplete="off" required>
                        </div>
                    <?php } ?>
                    <button type="submit" class="btn btn-primary btn-block"><?php echo $escape($lang->get('secure_send_reveal')); ?></button>
                </form>
            <?php } elseif ($result !== null && $result['error'] === '') {
                $fields = $result['fields'];
                $isNote = $result['send_type'] === 'note';
                $rows = $isNote
                    ? ['label' => $escape($fields['title']), 'password' => $escape($fields['secret']), 'login' => $escape($fields['login']), 'url' => $escape($fields['url']), 'description' => nl2br($escape($fields['note']))]
                    : ['label' => otvRenderPlainField($fields['label']), 'password' => $escape($fields['password']), 'login' => otvRenderPlainField($fields['login']), 'url' => otvRenderPlainField($fields['url']), 'description' => otvSanitizeDescription($fields['description'])];
                ?>
                <p><?php echo $escape($lang->get('secure_send_recipient_intro')); ?></p>
                <div class="table-responsive">
                    <table class="table" style="overflow-wrap: anywhere">
                        <tbody>
                        <?php foreach ($rows as $label => $value) { ?>
                            <tr><th scope="row"><?php echo $escape($lang->get($label)); ?></th><td><?php echo $value; ?></td></tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <p><?php echo $escape($lang->get('secure_send_copy_carefully')); ?></p>
                <p class="text-info"><?php echo $escape(str_replace(
                    ['#DATE#', '#VIEWS#'],
                    [date(($SETTINGS['date_format'] ?? 'Y-m-d') . ' ' . ($SETTINGS['time_format'] ?? 'H:i'), $result['time_limit']), (string) $result['remaining_views']],
                    $lang->get('secure_send_visibility')
                )); ?></p>
            <?php } ?>
        </div>
    </main>
</body>
</html>
