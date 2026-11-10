<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

/** Detect a selected email destination without database access or a provider request. */
function emailTransportIsConfigured(array $settings): bool
{
    $transport = (string) ($settings['email_transport'] ?? 'smtp');
    if ($transport === 'gmail_api') {
        return filter_var(trim((string) ($settings['email_google_sender'] ?? '')), FILTER_VALIDATE_EMAIL) !== false;
    }
    if ($transport === 'microsoft_graph') {
        return filter_var(trim((string) ($settings['email_microsoft_mailbox'] ?? '')), FILTER_VALIDATE_EMAIL) !== false;
    }
    if ($transport !== 'smtp') {
        return false;
    }
    $mode = (string) ($settings['email_smtp_auth_mode'] ?? '');
    if ($mode === '') {
        $mode = (string) ($settings['email_smtp_auth'] ?? '0') === '1' ? 'password' : 'none';
    }
    if ($mode === 'microsoft_oauth') {
        return filter_var(trim((string) ($settings['email_microsoft_mailbox'] ?? '')), FILTER_VALIDATE_EMAIL) !== false;
    }
    return in_array($mode, ['none', 'password'], true)
        && trim((string) ($settings['email_smtp_server'] ?? '')) !== '';
}

/** Settings that must be written as one profile, never through generic autosave. */
function emailTransportSettingNames(): array
{
    return [
        'email_transport', 'email_smtp_auth_mode', 'email_smtp_auth', 'email_config_revision',
        'email_smtp_server', 'email_port', 'email_security', 'email_smtp_verify_certificate',
        'email_auth_username', 'email_auth_pwd', 'email_from', 'email_from_name',
        'email_microsoft_tenant_id', 'email_microsoft_client_id', 'email_microsoft_client_secret',
        'email_microsoft_mailbox', 'email_google_client_id', 'email_google_client_secret', 'email_google_sender',
    ];
}

/** Validate a complete candidate without changing the active configuration or reflecting secrets. */
function emailTransportCandidate(#[SensitiveParameter] array $input, #[SensitiveParameter] array $existing, callable $encrypt): array
{
    $secretNames = ['email_auth_pwd', 'email_microsoft_client_secret', 'email_google_client_secret'];
    if (array_diff(array_keys($input), array_merge(emailTransportSettingNames(), ['secret_actions'])) !== []
        || isset($input['email_config_revision']) || isset($input['email_smtp_auth'])
    ) {
        throw new InvalidArgumentException('configuration');
    }
    $candidate = $existing;
    foreach ($input as $name => $value) {
        if ($name === 'secret_actions' || in_array($name, $secretNames, true)) { continue; }
        if (!is_string($value) || strlen($value) > 1024 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new InvalidArgumentException($name);
        }
        $candidate[$name] = trim($value);
    }
    $actions = $input['secret_actions'] ?? [];
    if (!is_array($actions) || array_diff(array_keys($actions), $secretNames) !== []) {
        throw new InvalidArgumentException('configuration');
    }
    foreach ($secretNames as $name) {
        $action = $actions[$name] ?? 'keep';
        $value = $input[$name] ?? '';
        if (!is_string($value) || strlen($value) > 8192 || !in_array($action, ['keep', 'replace', 'clear'], true)
            || ($action === 'replace' && $value === '') || ($action !== 'replace' && $value !== '')
        ) {
            throw new InvalidArgumentException($name);
        }
        if ($action === 'replace') { $candidate[$name] = $encrypt($value); }
        if ($action === 'clear') { $candidate[$name] = ''; }
    }
    if (!empty($candidate['email_auth_pwd']) && !tpIsInstanceKeyCiphertext((string) $candidate['email_auth_pwd'])) {
        $candidate['email_auth_pwd'] = $encrypt($candidate['email_auth_pwd']);
    }
    $transport = (string) ($candidate['email_transport'] ?? 'smtp');
    $mode = (string) ($candidate['email_smtp_auth_mode'] ?? '');
    if ($mode === '') { $mode = (string) ($candidate['email_smtp_auth'] ?? '0') === '1' ? 'password' : 'none'; }
    foreach ([
        'email_transport' => [$transport, ['smtp', 'microsoft_graph', 'gmail_api']],
        'email_smtp_auth_mode' => [$mode, ['none', 'password', 'microsoft_oauth']],
        'email_security' => [(string) ($candidate['email_security'] ?? 'none'), ['none', 'ssl', 'tls']],
        'email_smtp_verify_certificate' => [(string) ($candidate['email_smtp_verify_certificate'] ?? '0'), ['0', '1']],
    ] as $name => [$value, $allowed]) {
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException($name);
        }
    }
    $candidate['email_transport'] = $transport;
    $candidate['email_smtp_auth_mode'] = $mode;
    $candidate['email_smtp_auth'] = $mode === 'none' ? '0' : '1';
    if (filter_var($candidate['email_from'] ?? '', FILTER_VALIDATE_EMAIL) === false) {
        throw new InvalidArgumentException('email_from');
    }
    if ($transport === 'smtp' && $mode !== 'microsoft_oauth') {
        $host = (string) ($candidate['email_smtp_server'] ?? '');
        $port = filter_var($candidate['email_port'] ?? '', FILTER_VALIDATE_INT);
        // Keep internal DNS names, IPv4 and IPv6, but refuse PHPMailer's host lists/schemes.
        if ($host === '' || preg_match('/[\s;\/@?#]/', $host) || str_contains($host, '://')) {
            throw new InvalidArgumentException('email_smtp_server');
        }
        if ($port === false || $port < 1 || $port > 65535) {
            throw new InvalidArgumentException('email_port');
        }
        if ($mode === 'password') {
            foreach (['email_auth_username', 'email_auth_pwd'] as $name) {
                if (empty($candidate[$name])) { throw new InvalidArgumentException($name); }
            }
        }
    } else {
        $microsoft = $transport !== 'gmail_api';
        $senderName = $microsoft ? 'email_microsoft_mailbox' : 'email_google_sender';
        $secretName = $microsoft ? 'email_microsoft_client_secret' : 'email_google_client_secret';
        $sender = $candidate[$senderName] ?? '';
        $secret = $candidate[$secretName] ?? '';
        $guid = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i';
        if (filter_var($sender, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException($senderName);
        }
        if (strcasecmp($candidate['email_from'], $sender) !== 0) {
            throw new InvalidArgumentException('email_from');
        }
        if (!tpIsInstanceKeyCiphertext((string) $secret)) {
            throw new InvalidArgumentException($secretName);
        }
        if ($microsoft) {
            foreach (['email_microsoft_tenant_id', 'email_microsoft_client_id'] as $name) {
                if (preg_match($guid, $candidate[$name] ?? '') !== 1) { throw new InvalidArgumentException($name); }
            }
        } elseif (preg_match('/\A[A-Za-z0-9_-]+\.apps\.googleusercontent\.com\z/', $candidate['email_google_client_id'] ?? '') !== 1) {
            throw new InvalidArgumentException('email_google_client_id');
        }
    }
    $candidate['email_config_revision'] = bin2hex(random_bytes(16));
    return $candidate;
}

/** Expose only a known field name for validation errors, never an exception value or message. */
function emailTransportValidationField(Throwable $error): ?string
{
    return $error instanceof InvalidArgumentException && in_array($error->getMessage(), emailTransportSettingNames(), true)
        ? $error->getMessage() : null;
}

/** Use the configured canonical application URL, never an incoming Host header or arbitrary endpoint. */
function emailTransportRedirectUri(array $settings): string
{
    $base = rtrim((string) ($settings['cpassman_url'] ?? ''), '/');
    $parts = parse_url($base);
    if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
        || !isset($parts['host']) || (($parts['scheme'] ?? '') !== 'https'
            && !(($parts['scheme'] ?? '') === 'http' && in_array($parts['host'], ['localhost', '127.0.0.1', '[::1]'], true)))
    ) {
        throw new InvalidArgumentException('configuration');
    }
    return $base . '/sources/emails_oauth.php';
}

/** Encrypt pending consent as well as credentials before putting it in the session. */
function emailTransportEncrypt(#[SensitiveParameter] string $plaintext, array $settings): string
{
    $encrypted = cryption($plaintext, '', 'encrypt', $settings);
    if (($encrypted['error'] ?? true) !== false || !tpIsInstanceKeyCiphertext((string) ($encrypted['string'] ?? ''))) {
        throw new RuntimeException('configuration');
    }
    return $encrypted['string'];
}

/** Validate consent binding before exchanging a code or activating a pending configuration. */
function emailTransportValidateConsent(#[SensitiveParameter] array $pending, string $state, int $userId, array $settings): void
{
    if (!is_int($pending['expires_at'] ?? null) || $pending['expires_at'] < time()
        || ($pending['user_id'] ?? 0) !== $userId || $userId <= 0
        || !is_string($pending['state'] ?? null) || $state === '' || !hash_equals($pending['state'], $state)
        || !is_string($pending['revision'] ?? null) || !hash_equals($pending['revision'], (string) ($settings['email_config_revision'] ?? ''))
        || ($pending['redirect_uri'] ?? '') !== emailTransportRedirectUri($settings)
        || !is_array($pending['candidate'] ?? null) || !is_string($pending['verifier'] ?? null)
    ) {
        throw new InvalidArgumentException('configuration');
    }
}

/** Compare-and-swap the complete profile; caller owns the transaction. */
function emailTransportWriteProfile(#[SensitiveParameter] array $candidate, string $expectedRevision): void
{
    $active = DB::queryFirstField(
        'SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s FOR UPDATE',
        'admin', 'email_config_revision'
    );
    if (!is_string($active) || !hash_equals($active, $expectedRevision)) {
        throw new RuntimeException('configuration');
    }
    foreach (emailTransportSettingNames() as $name) {
        if (!array_key_exists($name, $candidate)) { continue; }
        DB::query(
            'INSERT INTO ' . prefixTable('misc') . ' (type, intitule, valeur, is_encrypted) VALUES (%s, %s, %s, %i)
             ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), is_encrypted = VALUES(is_encrypted)',
            'admin', $name, (string) $candidate[$name], in_array($name, tpSecretSettingNames(), true) ? 1 : 0
        );
    }
    DB::query('DELETE FROM ' . prefixTable('email_oauth_state') . ' WHERE config_revision <> %s', $candidate['email_config_revision']);
}
