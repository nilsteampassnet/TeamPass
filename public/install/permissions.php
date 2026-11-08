<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file permissions.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

if (!defined('TEAMPASS_ROOT')) {
    define('TEAMPASS_ROOT', dirname(__DIR__, 2));
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST_REQUIRED']);
    exit;
}
require_once TEAMPASS_ROOT . '/app/sources/setup_permissions.php';
require_once TEAMPASS_ROOT . '/app/sources/install_security_logic.php';
$flow = is_string($_POST['flow'] ?? null) ? $_POST['flow'] : '';
$ownsSettings = false;
try {
    if ($flow === 'install') {
        require_once __DIR__ . '/install-steps/install.functions.php';
        $ownsSettings = installSessionWroteCurrentSettings();
    } elseif ($flow === 'upgrade') {
        require_once TEAMPASS_ROOT . '/app/sources/config_access_logic.php';
        if (teampassConfigState(TEAMPASS_ROOT . '/app/config') !== 'installed') {
            http_response_code(403);
            throw new RuntimeException('SETUP_SESSION_REQUIRED');
        }
        require_once TEAMPASS_ROOT . '/app/config/include.php';
        require_once TEAMPASS_ROOT . '/app/sources/main.functions.php';
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    $token = is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : null;
    if (!tpSetupPermissionsRequestAllowed($_SESSION ?? [], $flow, $token, $ownsSettings)
        || is_file(TEAMPASS_ROOT . '/storage/recovery/pending.json')) {
        http_response_code(403);
        throw new RuntimeException('SETUP_SESSION_REQUIRED');
    }
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if (!in_array($action, ['audit', 'finalize'], true)) {
        http_response_code(400);
        throw new RuntimeException('INVALID_ACTION');
    }
    if (!installAcquireWriteLock()) {
        throw new RuntimeException('SETUP_OPERATION_BUSY');
    }
    // Read filesystem metadata only. Keep the browser informed if a large
    // deployment exceeds the host's request timeout; never assume a pass.
    if (function_exists('set_time_limit')) { @set_time_limit(300); }
    $review = tpSetupPermissionsAudit(TEAMPASS_ROOT);
    if ($action === 'finalize') {
        $review = tpSetupPermissionsApprove($review, ($_POST['acknowledge_manual'] ?? '') === '1');
        tpSetupPermissionsRecord(TEAMPASS_ROOT, $flow, $review);
        if ($flow === 'upgrade') {
            // Clean legacy setup data only after approval. Runtime requests
            // must never remove installer code before this review is complete.
            DB::query('DROP TABLE IF EXISTS `_install`');
            DB::delete(prefixTable('misc'), 'type=%s AND intitule=%s', 'install', 'clear_install_folder');
        }
        unset($_SESSION['setup_permissions_ready'], $_SESSION['setup_permissions_csrf'],
            $_SESSION['upgrade_previous_maintenance'], $_SESSION['user_granted'], $_SESSION['install_settings_sha256']);
    }
    echo json_encode(['success' => true, 'review' => $review, 'finalized' => $action === 'finalize',
        'cleanup' => tpSetupPermissionsCleanupCommand(TEAMPASS_ROOT, PHP_OS_FAMILY)], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $exception) {
    $known = ['SETUP_SESSION_REQUIRED', 'SETUP_OPERATION_BUSY', 'INVALID_ACTION', 'PERMISSIONS_REVIEW_REQUIRED',
        'PERMISSIONS_MANUAL_ACK_REQUIRED', 'PERMISSIONS_AUDIT_LOG_UNAVAILABLE'];
    $reason = in_array($exception->getMessage(), $known, true) ? $exception->getMessage() : 'PERMISSIONS_AUDIT_FAILED';
    if (http_response_code() < 400) { http_response_code(409); }
    echo json_encode(['success' => false, 'error' => $reason, 'review' => $review ?? null], JSON_INVALID_UTF8_SUBSTITUTE);
}
