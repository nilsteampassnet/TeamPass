<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * This file is part of TeamPass, distributed under the GNU GPL version 3 or later.
 * Copyright 2009-2026 Teampass.net. See <https://www.gnu.org/licenses/>.
 */

/**
 * Identify the setup and public recipient exceptions from the executed PHP file.
 *
 * The script filename is server-provided, not REQUEST_URI or a query parameter.
 * The recipient marker is defined only by index.php's terminating OTV dispatch.
 *
 * @param string $entryScript Executed PHP file, supplied by the web server.
 * @param bool $publicRecipient Whether the front controller selected its public recipient route.
 * @return bool Whether this request uses its own setup or link authorization.
 */
function teampassNetworkAclRequestIsExempt(string $entryScript, bool $publicRecipient): bool
{
    $entry = realpath($entryScript);
    if ($entry === false) {
        return false;
    }
    $entry = str_replace('\\', '/', $entry);
    $setupRoot = realpath(TEAMPASS_ROOT . '/public/install');
    if ($setupRoot !== false && str_starts_with($entry, str_replace('\\', '/', $setupRoot) . '/')) {
        return true;
    }
    return $publicRecipient === true && $entry === str_replace('\\', '/', (string) realpath(TEAMPASS_ROOT . '/public/index.php'));
}

/**
 * Enforce the network policy before an HTTP entry point can authenticate or run an action.
 *
 * Called by the shared runtime bootstrap, never by the CLI-only functions bootstrap.
 * An API/AJAX refusal is plain JSON; no session key, SQL or matching rule is disclosed.
 *
 * @param array $settings Current administrator settings.
 * @return void
 */
function teampassEnforceNetworkAccess(array $settings): void
{
    $entryScript = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
    $publicRecipient = defined('TEAMPASS_PUBLIC_SECURE_SEND') && TEAMPASS_PUBLIC_SECURE_SEND === true;
    if (teampassNetworkAclRequestIsExempt($entryScript, $publicRecipient)) {
        return;
    }
    $decision = teampassGetNetworkAccessDecision($settings);
    if ($decision['allowed'] === true) {
        return;
    }

    $session = \TeampassClasses\SessionManager\SessionManager::getSession();
    $language = new \TeampassClasses\Language\Language($session->get('user-language') ?? ($settings['default_language'] ?? 'english'));
    $message = (string) $language->getShipped('network_security_access_denied_message');
    $status = $decision['reason'] === 'acl_unavailable' ? 503 : 403;
    http_response_code($status);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    $entry = str_replace('\\', '/', (string) realpath($entryScript));
    $webRoot = str_replace('\\', '/', (string) realpath(TEAMPASS_ROOT . '/public'));
    $api = str_starts_with($entry, $webRoot . '/api/');
    if ($api === true) {
        // Preserve the API error contract before BaseController is available.
        header_remove('Set-Cookie');
        header('Content-Type: application/problem+json; charset=UTF-8');
        echo json_encode([
            'type' => 'about:blank',
            'title' => $status === 503 ? 'Service Unavailable' : 'Forbidden',
            'status' => $status,
            'detail' => $message,
            'error' => $message,
            'code' => 'network_access_denied',
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    } elseif (str_starts_with($entry, $webRoot . '/sources/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message, 'code' => 'network_access_denied'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        $title = htmlspecialchars((string) $language->getShipped('network_security_access_denied_title'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $htmlLanguage = htmlspecialchars((string) ($session->get('user-language') ?? 'en'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!DOCTYPE html><html lang="' . $htmlLanguage . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . $title . '</title></head><body><main style="max-width:760px;margin:80px auto;padding:24px;font-family:Arial,Helvetica,sans-serif">';
        echo '<h1>' . $title . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p></main></body></html>';
    }
    exit;
}
