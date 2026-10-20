<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 TeamPass.net
 * SPDX-License-Identifier: GPL-3.0-only
 * Distributed without any warranty. See LICENSE.md.
 */

use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\Language\Language;

require_once __DIR__ . '/main.functions.php';
require_once __DIR__ . '/device_pairing.functions.php';

$session = SessionManager::getSession();
$lang = new Language($session->get('user-language') ?? 'english');
$settings = (new ConfigManager())->getAllSettings();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

try {
    $check = new PerformChecks([], ['user_id' => $session->get('user-id'), 'user_key' => $session->get('key')]);
    $key = $_POST['key'] ?? null;
    // The unpredictable session key is the established synchronizer CSRF token for AJAX.
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !$check->checkSession() || !$check->userAccessPage('profile')
        || (int) $session->get('user-id') <= 0 || !is_string($key) || $key === ''
        || !hash_equals((string) $session->get('key'), $key)
    ) {
        throw new DevicePairingException('invalid_proof', 403);
    }
    $action = $_POST['type'] ?? '';
    $raw = $_POST['data'] ?? '';
    if (!is_string($action) || !in_array($action, ['create', 'status', 'approve', 'reject', 'cancel', 'list', 'revoke'], true)
        || !is_string($raw) || strlen($raw) > 8192
    ) {
        throw new DevicePairingException('invalid_request');
    }
    $input = $raw === '' ? [] : prepareExchangedData($raw, 'decode');
    if (!is_array($input)) {
        throw new DevicePairingException('invalid_request');
    }
    $userId = (int) $session->get('user-id');
    if ((int) ($settings['device_pairing_schema'] ?? 0) < 2) {
        throw new DevicePairingException('unavailable', 503);
    }
    devicePairingThrottle('profile:' . $userId, 120);
    if ($action === 'list') {
        $result = ['devices' => DB::query('SELECT id, label, created_at, last_used_at FROM ' . prefixTable('api_tokens') . '
            WHERE user_id = %i AND purpose = %s AND pairing_id IS NULL ORDER BY created_at DESC', $userId, 'external_device')];
    } elseif ($action === 'revoke') {
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new DevicePairingException('invalid_request');
        }
        $result = ['revoked' => apiDeviceRevokeCredential($userId, $id)];
    } else {
        devicePairingRequireHttps($_SERVER, $settings);
        if (!$session->has('device-pairing-nonce')) {
            $session->set('device-pairing-nonce', bin2hex(random_bytes(32)));
        }
        $binding = hash('sha256', $session->getId() . ':' . $session->get('device-pairing-nonce'));
        if ($action === 'approve') {
            require_once __DIR__ . '/webauthn_login.functions.php';
            $stepUp = webauthnLoginCheckStepUp($userId, $input, $settings, $lang);
            if ($stepUp !== null) {
                echo json_encode($stepUp, JSON_THROW_ON_ERROR);
                exit;
            }
        }
        $privateKey = (string) $session->get('user-private_key');
        $result = $action === 'create'
            ? devicePairingCreate($userId, $binding, (string) $session->get('key'), (int) $session->get('user-session_duration'), $privateKey, $settings)
            : devicePairingWebAction($action, $input, $userId, $binding, $privateKey, $settings);
    }
    echo json_encode(['error' => false] + $result, JSON_THROW_ON_ERROR);
} catch (DevicePairingException $e) {
    http_response_code($e->getCode());
    if ($e->getCode() === 429) {
        header('Retry-After: ' . $e->retryAfter);
    }
    echo json_encode(['error' => true, 'reason' => $e->reason, 'message' => $lang->get('device_pairing_error_' . $e->reason)], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[Device pairing] Web request failed');
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => $lang->get('device_pairing_error_server')]);
}
