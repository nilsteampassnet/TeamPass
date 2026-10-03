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
$SETTINGS = (new ConfigManager())->getAllSettings();
$recipientLanguage = secureSendRecipientLanguage($session->get('user-language'), $SETTINGS);
$lang = new Language($recipientLanguage);
// POEditor codes are language tags; the legacy "code" column also contains flag aliases.
$languageTag = (string) (DB::queryFirstField(
    'SELECT code_poeditor FROM ' . prefixTable('languages') . ' WHERE name = %s',
    basename(strtolower($recipientLanguage))
) ?: 'en');
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
$page = secureSendPrepareRecipient($input, $request->getMethod(), $SETTINGS, $confirmations, (string) $request->headers->get('host', ''));
$session->set('otv-confirmations', $confirmations);
$parameters = $page['parameters'];
$link = $page['link'];
$sender = is_array($page['sender']) ? $page['sender'] : null;
$result = $page['result'];
$error = $page['error'];
$token = $page['token'];
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$entityName = brandingPublicEntityName((string) ($SETTINGS['public_entity_name'] ?? ''));
$brandName = $entityName !== '' ? $entityName : TP_TOOL_NAME;
$logoUrl = brandingSecureSendLogoUrl(
    (string) ($SETTINGS['custom_logo'] ?? ''),
    TEAMPASS_ROOT . '/public/assets/custom'
);
if ($logoUrl === '') {
    $logoUrl = './assets/images/teampass-logo2-home.png';
}
$senderDisplayName = trim((string) ($sender['display_name'] ?? ''));
$timeLimit = (int) ($result['time_limit'] ?? ($link['time_limit'] ?? 0));
$remainingViews = $result !== null && ($result['error'] ?? '') === ''
    ? (int) $result['remaining_views']
    : max(0, (int) ($link['max_views'] ?? 0) - (int) ($link['views'] ?? 0));
$expiresLabel = $timeLimit > 0
    ? date(($SETTINGS['date_format'] ?? 'Y-m-d') . ' ' . ($SETTINGS['time_format'] ?? 'H:i'), $timeLimit)
    : '';
?>
<!DOCTYPE html>
<html lang="<?php echo $escape($languageTag); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title><?php echo $escape($brandName . ' - ' . $lang->get('secure_send')); ?></title>
    <link rel="stylesheet" href="./plugins/adminlte/css/adminlte.min.css?v=<?php echo $escape(TP_VERSION . '.' . TP_VERSION_MINOR); ?>">
    <link rel="stylesheet" href="./plugins/fontawesome-free/css/all.min.css?v=<?php echo $escape(TP_VERSION . '.' . TP_VERSION_MINOR); ?>">
    <style>
        :root {
            color-scheme: light dark;
            --secure-send-bg: #eef2f7;
            --secure-send-card: #fff;
            --secure-send-border: #dfe5ec;
            --secure-send-heading: #182433;
            --secure-send-text: #344054;
            --secure-send-muted: #667085;
            --secure-send-panel: #f7f9fc;
            --secure-send-accent: #1578ad;
            --secure-send-accent-soft: #e9f5fb;
        }
        body.secure-send-page {
            min-height: 100vh;
            margin: 0;
            background: var(--secure-send-bg);
            color: var(--secure-send-text);
        }
        .secure-send-shell {
            width: min(760px, calc(100% - 2rem));
            min-height: 100vh;
            margin: 0 auto;
            padding: clamp(1.25rem, 5vh, 3.5rem) 0 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .secure-send-card {
            overflow: hidden;
            border: 1px solid var(--secure-send-border);
            border-radius: 14px;
            background: var(--secure-send-card);
            box-shadow: 0 18px 45px rgba(24, 36, 51, .11);
        }
        .secure-send-brand {
            padding: 1.6rem 1.75rem 1.35rem;
            border-bottom: 1px solid var(--secure-send-border);
            text-align: center;
        }
        .secure-send-logo {
            display: block;
            width: auto;
            max-width: min(230px, 70%);
            height: auto;
            max-height: 72px;
            margin: 0 auto .85rem;
            object-fit: contain;
        }
        .secure-send-brand-name {
            margin: 0;
            color: var(--secure-send-heading);
            font-size: 1.25rem;
            font-weight: 600;
            overflow-wrap: anywhere;
        }
        .secure-send-product {
            display: inline-flex;
            align-items: center;
            margin-top: .55rem;
            padding: .28rem .65rem;
            border-radius: 999px;
            background: var(--secure-send-accent-soft);
            color: var(--secure-send-accent);
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .045em;
            text-transform: uppercase;
        }
        .secure-send-content {
            padding: 1.75rem;
        }
        .secure-send-title {
            margin: 0 0 1.35rem;
            color: var(--secure-send-heading);
            font-size: 1.45rem;
            font-weight: 600;
            text-align: center;
        }
        .secure-send-identity {
            display: flex;
            align-items: center;
            gap: .9rem;
            margin-bottom: 1.35rem;
            padding: 1rem;
            border: 1px solid #cfe4ef;
            border-radius: 10px;
            background: var(--secure-send-accent-soft);
        }
        .secure-send-identity-icon {
            width: 2.65rem;
            height: 2.65rem;
            flex: 0 0 2.65rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--secure-send-accent);
            color: #fff;
            font-size: 1.05rem;
        }
        .secure-send-identity-label,
        .secure-send-identity-meta {
            display: block;
        }
        .secure-send-identity-label {
            color: var(--secure-send-muted);
            font-size: .82rem;
        }
        .secure-send-identity-name {
            display: block;
            color: var(--secure-send-heading);
            font-size: 1.05rem;
            overflow-wrap: anywhere;
        }
        .secure-send-identity-meta {
            margin-top: .15rem;
            color: var(--secure-send-muted);
            font-size: .82rem;
        }
        .secure-send-metadata {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin: 0 0 1.35rem;
        }
        .secure-send-meta-item {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .42rem .65rem;
            border: 1px solid var(--secure-send-border);
            border-radius: 8px;
            background: var(--secure-send-panel);
            color: var(--secure-send-muted);
            font-size: .82rem;
        }
        .secure-send-card .form-control {
            border-color: #cbd5e1;
        }
        .secure-send-card .btn-primary {
            padding-top: .65rem;
            padding-bottom: .65rem;
            border-color: var(--secure-send-accent);
            background: var(--secure-send-accent);
            font-weight: 600;
        }
        .secure-send-help {
            color: var(--secure-send-muted);
            line-height: 1.55;
        }
        .secure-send-domain-hint {
            margin: 1rem 0 0;
            color: var(--secure-send-muted);
            font-size: .78rem;
            text-align: center;
        }
        .secure-send-powered {
            margin: 1rem 0 0;
            text-align: center;
            font-size: .78rem;
        }
        .secure-send-powered a {
            color: var(--secure-send-muted);
            text-decoration: none;
        }
        .secure-send-powered a:hover,
        .secure-send-powered a:focus {
            color: var(--secure-send-accent);
            text-decoration: underline;
        }
        .secure-send-fields {
            width: 100%;
            table-layout: fixed;
        }
        .secure-send-fields th {
            width: 28%;
            color: var(--secure-send-heading);
            overflow-wrap: normal;
            word-break: normal;
        }
        .secure-send-fields td {
            overflow-wrap: anywhere;
        }
        .secure-send-fields th,
        .secure-send-fields td {
            border-top-color: var(--secure-send-border);
        }
        @media (max-width: 575.98px) {
            .secure-send-shell {
                width: min(760px, calc(100% - 1rem));
                padding: .5rem 0 1rem;
                justify-content: flex-start;
            }
            .secure-send-card {
                border-radius: 10px;
            }
            .secure-send-brand,
            .secure-send-content {
                padding: 1.25rem;
            }
            .secure-send-identity {
                align-items: flex-start;
            }
            .secure-send-fields th {
                width: 36%;
            }
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --secure-send-bg: #101820;
                --secure-send-card: #18222d;
                --secure-send-border: #344252;
                --secure-send-heading: #f1f5f9;
                --secure-send-text: #d5dce5;
                --secure-send-muted: #aab5c2;
                --secure-send-panel: #202c38;
                --secure-send-accent: #39a7d8;
                --secure-send-accent-soft: #173447;
            }
            .secure-send-card {
                box-shadow: 0 18px 45px rgba(0, 0, 0, .28);
            }
            .secure-send-identity {
                border-color: #28536a;
            }
            .secure-send-card .form-control {
                border-color: #465567;
                background: #111a23;
                color: #f1f5f9;
            }
            .secure-send-card .table {
                color: var(--secure-send-text);
            }
        }
    </style>
</head>
<body class="hold-transition secure-send-page">
    <div class="secure-send-shell">
        <main class="secure-send-card">
            <header class="secure-send-brand">
                <img class="secure-send-logo" src="<?php echo $escape($logoUrl); ?>" alt="">
                <p class="secure-send-brand-name"><?php echo $escape($brandName); ?></p>
                <span class="secure-send-product"><i class="fa-solid fa-shield-halved mr-2" aria-hidden="true"></i><?php echo $escape($lang->get('secure_send')); ?></span>
            </header>
            <div class="secure-send-content">
                <h1 class="secure-send-title"><?php echo $escape($lang->get('secure_send_recipient_title')); ?></h1>
            <?php if ($error !== '') { ?>
                <div class="alert alert-danger" role="alert"><?php echo $escape($lang->get($error)); ?></div>
            <?php } ?>
            <?php if ($sender !== null) { ?>
                <section class="secure-send-identity" aria-label="<?php echo $escape($lang->get('secure_send_sender_identity')); ?>">
                    <span class="secure-send-identity-icon" aria-hidden="true"><i class="fa-solid fa-user-check"></i></span>
                    <span>
                        <?php if ($senderDisplayName !== '') { ?>
                            <span class="secure-send-identity-label"><?php echo $escape($lang->get('secure_send_shared_by')); ?></span>
                            <strong class="secure-send-identity-name"><?php echo $escape($senderDisplayName); ?></strong>
                            <span class="secure-send-identity-meta"><?php echo $escape($entityName === ''
                                ? $lang->get('secure_send_authenticated_account')
                                : str_replace('#ENTITY#', $entityName, $lang->get('secure_send_authenticated_account_entity'))); ?></span>
                        <?php } elseif ($entityName !== '') { ?>
                            <span class="secure-send-identity-label"><?php echo $escape($lang->get('secure_send_shared_securely_by')); ?></span>
                            <strong class="secure-send-identity-name"><?php echo $escape($entityName); ?></strong>
                            <span class="secure-send-identity-meta"><?php echo $escape($lang->get('secure_send_authenticated_account')); ?></span>
                        <?php } else { ?>
                            <strong class="secure-send-identity-name"><?php echo $escape($lang->get('secure_send_shared_via_teampass')); ?></strong>
                            <span class="secure-send-identity-meta"><?php echo $escape($lang->get('secure_send_authenticated_account')); ?></span>
                        <?php } ?>
                    </span>
                </section>
            <?php } ?>
            <?php if ($link !== []) { ?>
                <div class="secure-send-metadata" aria-label="<?php echo $escape($lang->get('secure_send_access_settings')); ?>">
                    <?php if ((int) ($link['has_passphrase'] ?? 0) === 1) { ?>
                        <span class="secure-send-meta-item"><i class="fa-solid fa-lock" aria-hidden="true"></i><?php echo $escape($lang->get('secure_send_protected')); ?></span>
                    <?php } ?>
                    <?php if ($expiresLabel !== '') { ?>
                        <span class="secure-send-meta-item"><i class="fa-regular fa-calendar" aria-hidden="true"></i><?php echo $escape(str_replace('#DATE#', $expiresLabel, $lang->get('secure_send_expires_on'))); ?></span>
                    <?php } ?>
                    <span class="secure-send-meta-item"><i class="fa-regular fa-eye" aria-hidden="true"></i><?php echo $remainingViews . ' ' . $escape($lang->get('secure_send_remaining_views')); ?></span>
                </div>
            <?php } ?>
            <?php if ($token !== '' && $parameters !== null) { ?>
                <p class="secure-send-help"><?php echo $escape($lang->get('secure_send_reveal_hint')); ?></p>
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
                    <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-eye mr-2" aria-hidden="true"></i><?php echo $escape($lang->get('secure_send_reveal')); ?></button>
                </form>
                <p class="secure-send-domain-hint"><i class="fa-solid fa-circle-info mr-1" aria-hidden="true"></i><?php echo $escape($lang->get('secure_send_verify_address')); ?></p>
            <?php } elseif ($result !== null && $result['error'] === '') {
                $fields = $result['fields'];
                $isNote = $result['send_type'] === 'note';
                if ($isNote) {
                    $rows = ['label' => $escape($fields['title']), 'password' => $escape($fields['secret']), 'login' => $escape($fields['login']), 'url' => $escape($fields['url']), 'description' => nl2br($escape($fields['note']))];
                } else {
                    $rows = ['label' => otvRenderPlainField($fields['label']), 'password' => $escape($fields['password'])];
                    if (($fields['otp_code'] ?? '') !== '') {
                        $rows['otp_code'] = '<code>' . $escape($fields['otp_code']) . '</code> '
                            . '<small class="text-muted">(' . (int) $fields['otp_expires_in'] . ' '
                            . $escape($lang->get('seconds')) . ')</small>';
                    }
                    if (($fields['otp_next_code'] ?? '') !== '') {
                        $nextValidity = str_replace(
                            ['#START#', '#DURATION#'],
                            [(string) (int) $fields['otp_next_valid_in'], (string) (int) $fields['otp_next_valid_for']],
                            $lang->get('secure_send_next_otp_validity')
                        );
                        $rows['secure_send_next_otp_code'] = '<code>' . $escape($fields['otp_next_code']) . '</code> '
                            . '<small class="text-muted">(' . $escape($nextValidity) . ')</small>';
                    }
                    $rows += ['login' => otvRenderPlainField($fields['login']), 'url' => otvRenderPlainField($fields['url']), 'description' => otvSanitizeDescription($fields['description'])];
                }
                ?>
                <p class="secure-send-help"><?php echo $escape($lang->get('secure_send_recipient_intro')); ?></p>
                <?php if (($fields['description_truncated'] ?? false) === true) { ?>
                    <p class="alert alert-warning"><?php echo $escape($lang->get('secure_send_description_truncated')); ?></p>
                <?php } ?>
                <div class="table-responsive">
                    <table class="table secure-send-fields">
                        <tbody>
                        <?php foreach ($rows as $label => $value) { ?>
                            <tr><th scope="row"><?php echo $escape($lang->get($label)); ?></th><td><?php echo $value; ?></td></tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <p class="secure-send-help"><?php echo $escape($lang->get('secure_send_copy_carefully')); ?></p>
                <p class="text-info mb-0"><?php echo $escape(str_replace(
                    ['#DATE#', '#VIEWS#'],
                    [date(($SETTINGS['date_format'] ?? 'Y-m-d') . ' ' . ($SETTINGS['time_format'] ?? 'H:i'), $result['time_limit']), (string) $result['remaining_views']],
                    $lang->get('secure_send_visibility')
                )); ?></p>
            <?php } ?>
            </div>
        </main>
        <footer class="secure-send-powered">
            <a href="https://teampass.net" target="_blank" rel="noopener noreferrer">Powered by <strong>TeamPass</strong></a>
        </footer>
    </div>
</body>
</html>
