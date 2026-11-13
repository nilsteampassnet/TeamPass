<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\EmailService\DatabaseEmailTokenStore;
use TeampassClasses\EmailService\EmailHttp;
use TeampassClasses\EmailService\EmailSettings;
use TeampassClasses\EmailService\EmailTokenManager;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/main.functions.php';
require_once __DIR__ . '/emails_transport_logic.php';
loadClasses('DB');
$session = SessionManager::getSession();
$request = Request::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');
$checks = new PerformChecks(['type' => ''], ['user_id' => $session->get('user-id'), 'user_key' => $session->get('key')]);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$callback = defined('TEAMPASS_EMAIL_OAUTH_CALLBACK');
$transaction = false;
try {
    if (!$checks->checkSession() || !$checks->userAccessPage('emails') || (int) $session->get('user-id') <= 0
        || (int) DB::queryFirstField('SELECT admin FROM ' . prefixTable('users') . ' WHERE id = %i AND deleted_at IS NULL', $session->get('user-id')) !== 1
    ) {
        throw new RuntimeException('permission');
    }
    $settings = (new ConfigManager())->getAllSettings();
    if ($callback) {
        // Consume validated state before network I/O. A replay needs fresh consent.
        $pendingCipher = $session->get('email-oauth-pending');
        $decrypted = is_string($pendingCipher) ? cryption($pendingCipher, '', 'decrypt', $settings) : [];
        $pending = json_decode((string) ($decrypted['string'] ?? ''), true);
        $state = $request->query->get('state');
        $code = $request->query->get('code');
        if ($request->getMethod() !== 'GET' || !is_array($pending) || !is_string($state) || !is_string($code) || $code === '') {
            throw new RuntimeException('configuration');
        }
        emailTransportValidateConsent($pending, $state, (int) $session->get('user-id'), $settings);
        $session->set('email-oauth-pending', null);
        $candidate = array_replace($settings, $pending['candidate']);
        $manager = new EmailTokenManager(new EmailSettings($candidate), 'gmail_api', new DatabaseEmailTokenStore($candidate['email_config_revision']), EmailHttp::client());
        $token = $manager->exchangeGoogleCode($code, $pending['verifier'], $pending['redirect_uri']);
        // No transaction spans provider I/O. Publish configuration and encrypted authorization together.
        DB::startTransaction();
        $transaction = true;
        emailTransportWriteProfile($candidate, $pending['revision']);
        $manager->storeAuthorization($token);
        DB::commit();
        $transaction = false;
        ConfigManager::invalidateCache();
        $session->set('email-oauth-result', 'success');
        header('Location: ../index.php?page=emails', true, 303);
        exit;
    }
    $key = $request->request->get('key');
    if ($request->getMethod() !== 'POST' || !is_string($key) || $key === '' || !hash_equals((string) $session->get('key'), $key)) {
        throw new RuntimeException('permission');
    }
    $data = prepareExchangedData($request->request->get('data', ''), 'decode');
    if (!is_array($data) || !is_string($data['revision'] ?? null)
        || !hash_equals((string) ($settings['email_config_revision'] ?? ''), $data['revision']) || !is_array($data['profile'] ?? null)
    ) {
        throw new RuntimeException('configuration');
    }
    $candidate = emailTransportCandidate($data['profile'], $settings, static fn(string $secret): string => emailTransportEncrypt($secret, $settings));
    $purpose = $candidate['email_transport'] === 'smtp' ? 'microsoft_smtp' : $candidate['email_transport'];
    if (($purpose !== 'microsoft_smtp' || $candidate['email_smtp_auth_mode'] === 'microsoft_oauth')
        && !(new EmailSettings($candidate))->providerConfigurationIsValid($purpose)
    ) {
        throw new RuntimeException('configuration');
    }
    if ($candidate['email_transport'] === 'gmail_api') {
        $redirect = emailTransportRedirectUri($settings);
        $manager = new EmailTokenManager(new EmailSettings($candidate), 'gmail_api', new DatabaseEmailTokenStore($candidate['email_config_revision']), EmailHttp::client());
        $provider = $manager->googleAuthorizationProvider($redirect);
        $url = $provider->getAuthorizationUrl(['access_type' => 'offline', 'prompt' => 'consent']);
        // Only profile fields belong in pending state; no installation configuration or DB credentials.
        $profile = array_intersect_key($candidate, array_flip(emailTransportSettingNames()));
        $pending = ['candidate' => $profile + ['cpassman_url' => $settings['cpassman_url']], 'revision' => $data['revision'],
            'state' => $provider->getState(), 'verifier' => $provider->getPkceCode(), 'redirect_uri' => $redirect,
            'user_id' => (int) $session->get('user-id'), 'expires_at' => time() + 600];
        $session->set('email-oauth-pending', emailTransportEncrypt((string) json_encode($pending, JSON_THROW_ON_ERROR), $settings));
        echo prepareExchangedData(['error' => false, 'authorization_url' => $url], 'encode');
        exit;
    }
    DB::startTransaction();
    $transaction = true;
    emailTransportWriteProfile($candidate, $data['revision']);
    DB::commit();
    $transaction = false;
    ConfigManager::invalidateCache();
    $session->set('email-oauth-pending', null);
    echo prepareExchangedData(['error' => false, 'revision' => $candidate['email_config_revision']], 'encode');
} catch (Throwable $e) {
    if ($transaction) { DB::rollback(); }
    if ($callback) {
        $session->set('email-oauth-result', 'error');
        header('Location: ../index.php?page=emails', true, 303);
    } else {
        if (empty($session->get('key'))) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => true, 'message' => $lang->get('email_profile_save_failed')]);
            exit;
        }
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('email_profile_save_failed'),
            'field' => emailTransportValidationField($e)], 'encode');
    }
}
