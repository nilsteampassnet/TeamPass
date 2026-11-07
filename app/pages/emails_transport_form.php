<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

// Included only after the Emails page has checked administrative access.
if (!isset($lang, $SETTINGS, $session, $localPasswordRecoveryEnabled)) { exit; }
$emailEscape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$emailValue = static fn(string $name): string => $emailEscape(html_entity_decode((string) ($SETTINGS[$name] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
$emailMode = (string) ($SETTINGS['email_smtp_auth_mode'] ?? '');
if ($emailMode === '') { $emailMode = (string) ($SETTINGS['email_smtp_auth'] ?? '0') === '1' ? 'password' : 'none'; }
$emailOauthResult = $session->get('email-oauth-result');
$session->set('email-oauth-result', null);
if (in_array($emailOauthResult, ['success', 'error'], true)) {
    echo '<div class="alert alert-' . ($emailOauthResult === 'success' ? 'success' : 'danger') . '" role="status">'
        . $emailEscape($lang->get($emailOauthResult === 'success' ? 'email_google_authorized' : 'email_profile_save_failed')) . '</div>';
}
?>
<div id="email-transport-profile" data-revision="<?php echo $emailValue('email_config_revision'); ?>">
    <p class="text-muted"><?php echo $emailEscape($lang->get('email_profile_intro')); ?></p>
    <div class="form-group">
        <label for="email_transport"><?php echo $emailEscape($lang->get('email_transport')); ?></label>
        <select class="form-control form-control-sm no-save" id="email_transport">
            <?php foreach (['smtp' => 'email_transport_smtp', 'microsoft_graph' => 'email_transport_graph', 'gmail_api' => 'email_transport_gmail'] as $value => $label) { ?>
            <option value="<?php echo $value; ?>" <?php echo ($SETTINGS['email_transport'] ?? 'smtp') === $value ? 'selected' : ''; ?>><?php echo $emailEscape($lang->get($label)); ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="form-group" data-email-section="smtp-auth">
        <label for="email_smtp_auth_mode"><?php echo $emailEscape($lang->get('admin_email_auth')); ?></label>
        <select class="form-control form-control-sm no-save" id="email_smtp_auth_mode">
            <?php foreach (['none' => 'none', 'password' => 'email_auth_password', 'microsoft_oauth' => 'email_auth_microsoft'] as $value => $label) { ?>
            <option value="<?php echo $value; ?>" <?php echo $emailMode === $value ? 'selected' : ''; ?>><?php echo $emailEscape($lang->get($label)); ?></option>
            <?php } ?>
        </select>
    </div>
    <?php
    $emailFields = [
        ['smtp', 'email_smtp_server', 'admin_email_smtp_server'],
        ['smtp', 'email_port', 'admin_email_port'],
        ['password', 'email_auth_username', 'admin_email_auth_username'],
        ['microsoft', 'email_microsoft_tenant_id', 'email_microsoft_tenant'],
        ['microsoft', 'email_microsoft_client_id', 'email_provider_client_id'],
        ['microsoft', 'email_microsoft_mailbox', 'email_provider_mailbox'],
        ['google', 'email_google_client_id', 'email_provider_client_id'],
        ['google', 'email_google_sender', 'email_provider_mailbox'],
        ['all', 'email_from', 'admin_email_from'],
        ['all', 'email_from_name', 'admin_email_from_name'],
    ];
    foreach ($emailFields as [$section, $name, $label]) { ?>
    <div class="form-group" data-email-section="<?php echo $section; ?>">
        <label for="<?php echo $name; ?>"><?php echo $emailEscape($lang->get($label)); ?></label>
        <input class="form-control form-control-sm no-save" type="text" id="<?php echo $name; ?>" value="<?php echo $emailValue($name); ?>" maxlength="1024">
    </div>
    <?php } ?>
    <div class="form-group" data-email-section="smtp">
        <label for="email_security"><?php echo $emailEscape($lang->get('admin_email_security')); ?></label>
        <select class="form-control form-control-sm no-save" id="email_security">
            <?php foreach (['none' => 'none', 'tls' => 'email_security_starttls', 'ssl' => 'email_security_implicit'] as $value => $label) { ?>
            <option value="<?php echo $value; ?>" <?php echo ($SETTINGS['email_security'] ?? 'none') === $value ? 'selected' : ''; ?>><?php echo $emailEscape($lang->get($label)); ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="form-group" data-email-section="smtp">
        <label for="email_smtp_verify_certificate"><?php echo $emailEscape($lang->get('admin_email_verify_certificate')); ?></label>
        <select class="form-control form-control-sm no-save" id="email_smtp_verify_certificate">
            <?php foreach (['1' => 'yes', '0' => 'no'] as $value => $label) { ?>
            <option value="<?php echo $value; ?>" <?php echo (string) ($SETTINGS['email_smtp_verify_certificate'] ?? '0') === (string) $value ? 'selected' : ''; ?>><?php echo $emailEscape($lang->get($label)); ?></option>
            <?php } ?>
        </select>
        <small class="form-text text-muted"><?php echo $emailEscape($lang->get('admin_email_verify_certificate_tip')); ?></small>
    </div>
    <?php foreach (['email_auth_pwd' => 'password', 'email_microsoft_client_secret' => 'microsoft', 'email_google_client_secret' => 'google'] as $name => $section) { ?>
    <div class="form-group" data-email-section="<?php echo $section; ?>">
        <label for="<?php echo $name; ?>"><?php echo $emailEscape($lang->get($name === 'email_auth_pwd' ? 'admin_email_auth_pwd' : 'email_provider_client_secret')); ?></label>
        <input type="password" class="form-control form-control-sm no-save" id="<?php echo $name; ?>" value="" autocomplete="new-password" maxlength="8192">
        <select class="form-control form-control-sm no-save mt-1 email-secret-action" data-secret="<?php echo $name; ?>" aria-label="<?php echo $emailEscape($lang->get('email_secret_action')); ?>">
            <?php foreach (['keep' => 'email_secret_keep', 'replace' => 'email_secret_replace', 'clear' => 'email_secret_clear'] as $value => $label) { ?>
            <option value="<?php echo $value; ?>"><?php echo $emailEscape($lang->get($label)); ?></option>
            <?php } ?>
        </select>
    </div>
    <?php } ?>
    <div data-email-section="microsoft" class="alert alert-info"><?php echo $emailEscape($lang->get('email_microsoft_tip')); ?></div>
    <div data-email-section="google" class="alert alert-info">
        <?php echo $emailEscape($lang->get('email_google_tip')); ?>
        <p class="mt-2 mb-2"><?php echo $emailEscape($lang->get(($emailGoogleAuthorizationRecorded ?? false) ? 'email_google_authorization_recorded' : 'email_google_authorization_missing')); ?></p>
        <div><strong><?php echo $emailEscape($lang->get('email_google_redirect')); ?></strong>
            <code><?php try { echo $emailEscape(emailTransportRedirectUri($SETTINGS)); } catch (Throwable $e) { echo $emailEscape($lang->get('email_profile_save_failed')); } ?></code>
        </div>
    </div>
    <p class="text-muted"><?php echo $emailEscape($lang->get('email_api_limits')); ?></p>
    <button type="button" id="email-profile-save" class="btn btn-primary"><?php echo $emailEscape($lang->get('email_profile_save')); ?></button>
    <span id="email-profile-status" class="ml-2" role="status" aria-live="polite"></span>
</div>
<hr>
<div class="form-group">
    <label for="email_server_url"><?php echo $emailEscape($lang->get('admin_email_server_url')); ?></label>
    <input class="form-control form-control-sm" id="email_server_url" value="<?php echo $emailValue('email_server_url'); ?>">
    <small class="form-text text-muted"><?php echo $emailEscape($lang->get('admin_email_server_url_tip')); ?></small>
</div>
<div class="form-group">
    <label><?php echo $emailEscape($lang->get('settings_enable_local_password_recovery')); ?></label>
    <div class="toggle toggle-modern" id="enable_local_password_recovery" data-toggle-on="<?php echo $localPasswordRecoveryEnabled ? 'true' : 'false'; ?>"></div>
    <input type="hidden" id="enable_local_password_recovery_input" value="<?php echo $localPasswordRecoveryEnabled ? '1' : '0'; ?>">
    <small class="form-text text-muted"><?php echo $emailEscape($lang->get('settings_enable_local_password_recovery_tip')); ?></small>
</div>
