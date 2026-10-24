<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

define('TEAMPASS_ROOT', dirname(__DIR__, 2));
require TEAMPASS_ROOT . '/app/vendor/autoload.php';
require TEAMPASS_ROOT . '/app/config/include.php';
require TEAMPASS_ROOT . '/app/sources/recovery.functions.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
$texts = require __DIR__ . '/recovery.texts.php';
$escape = static fn (string $key): string => htmlspecialchars($texts[$key], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
if (empty($_SESSION['recovery_csrf'])) {
    $_SESSION['recovery_csrf'] = bin2hex(random_bytes(32));
}
$local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
$authorized = (int) ($_SESSION['recovery_operator_until'] ?? 0) > time();
$serviceAccount = tpRecoveryServiceAccount();
$commandBase = tpRecoveryCommandBase();

/** Resolve an uploaded archive or an operator-staged file in the fixed incoming directory. */
function recoveryRequestArchive(string $field, string $extension): string
{
    if (isset($_FILES[$field]) && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
        && is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return $_FILES[$field]['tmp_name'];
    }
    $filename = $_POST[$field . '_server'] ?? '';
    if (!is_string($filename) || preg_match('/^[a-zA-Z0-9_.-]+\.' . $extension . '$/D', $filename) !== 1) {
        throw new TeamPassRecoveryException('ARCHIVE_INPUT_INVALID');
    }
    $directory = tpRecoveryJobDirectory() . '/incoming';
    $path = $directory . '/' . $filename;
    if (is_link($directory) || is_link($path) || !is_file($path)) {
        throw new TeamPassRecoveryException('ARCHIVE_INPUT_INVALID');
    }
    return $path;
}

/** Read only the non-sensitive state of the job bound to this operator session. */
function recoveryVisibleJob(string $id): array
{
    if ($id === '') {
        throw new TeamPassRecoveryException('RECOVERY_JOB_INVALID');
    }
    $directory = tpRecoveryJobDirectory($id);
    if (is_file($directory . '/receipt.json') && !is_link($directory . '/receipt.json')) {
        return json_decode((string) file_get_contents($directory . '/receipt.json'), true, 32, JSON_THROW_ON_ERROR);
    }
    return tpRecoveryPublicJob(tpRecoveryLoadJob($id));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (!$https && !$local) {
            throw new TeamPassRecoveryException('HTTPS_REQUIRED');
        }
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['recovery_csrf'], $_POST['csrf'])) {
            throw new TeamPassRecoveryException('CSRF_INVALID');
        }
        $action = $_POST['action'] ?? '';
        if ($action === 'status') {
            $id = (string) ($_SESSION['recovery_job'] ?? '');
            // The one-hour code limits new preparations, not observation of this session's job.
            $result = recoveryVisibleJob($id);
        } else {
            tpRecoveryAssertFreshTarget();
            $operatorPath = tpRecoveryJobDirectory() . '/operator.json';
            if (is_link($operatorPath) || !is_file($operatorPath)) {
                throw new TeamPassRecoveryException('OPERATOR_CODE_REQUIRED');
            }
            if (!is_readable($operatorPath)) {
                throw new TeamPassRecoveryException('OPERATOR_CODE_UNREADABLE');
            }
            $operator = json_decode((string) file_get_contents($operatorPath), true, 8, JSON_THROW_ON_ERROR);
            if (($operator['expires_at'] ?? 0) <= time()) {
                throw new TeamPassRecoveryException('OPERATOR_CODE_EXPIRED');
            }
            if ($action === 'unlock') {
                $provided = $_POST['operator'] ?? '';
                if (!is_string($provided) || !hash_equals($operator['token_hash'], hash('sha256', trim($provided)))) {
                    throw new TeamPassRecoveryException('OPERATOR_CODE_INVALID');
                }
                session_regenerate_id(true);
                $_SESSION['recovery_operator_until'] = $operator['expires_at'];
                $_SESSION['recovery_operator_hash'] = $operator['token_hash'];
                $result = ['unlocked' => true];
            } elseif ($action === 'prepare' && $authorized
                && hash_equals($operator['token_hash'], (string) ($_SESSION['recovery_operator_hash'] ?? ''))) {
                $result = tpRecoveryPrepare([
                    'kit_path' => recoveryRequestArchive('kit', 'tprecovery'),
                    'backup_path' => recoveryRequestArchive('backup', 'tpbackup'),
                    'kit_password' => $_POST['kit_password'] ?? '', 'backup_password' => $_POST['backup_password'] ?? '',
                    'connection' => ['host' => $_POST['host'] ?? '', 'port' => $_POST['port'] ?? '',
                        'database' => $_POST['database'] ?? '', 'user' => $_POST['user'] ?? '', 'password' => $_POST['password'] ?? ''],
                    'url' => $_POST['url'] ?? '',
                ]);
                $_SESSION['recovery_job'] = $result['id'];
            } else {
                throw new TeamPassRecoveryException('RECOVERY_REQUEST_REJECTED');
            }
        }
        echo json_encode(['success' => true, 'result' => $result], JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $exception instanceof TeamPassRecoveryException
            ? $exception->reason : 'RECOVERY_OPERATION_FAILED']);
    }
    exit;
}
$initialJob = null;
if (is_string($_SESSION['recovery_job'] ?? null)) {
    try {
        $initialJob = recoveryVisibleJob($_SESSION['recovery_job']);
    } catch (Throwable $exception) {
        $initialJob = null;
    }
}
if (teampassConfigState(TEAMPASS_ROOT . '/app/config') !== 'not_installed' && $initialJob === null) {
    http_response_code(409);
    echo teampassErrorPageShell($texts['drp_title'], '<p>TeamPass is already configured. Use the application login page.</p>');
    exit;
}
$scriptVersion = substr((string) hash_file('sha256', __DIR__ . '/recovery.js'), 0, 16);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape('drp_title') ?></title>
    <link rel="stylesheet" href="../plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="css/recovery.css">
</head>
<body class="bg-light recovery-page">
<main class="container py-4 recovery-wizard" style="max-width: 900px">
    <nav class="mb-4"><a href="install.php"><?= $escape('drp_back') ?></a></nav>
    <h1 class="h3"><i class="fas fa-life-ring mr-2" aria-hidden="true"></i><?= $escape('drp_title') ?></h1>
    <p><?= $escape('drp_intro') ?></p>
    <p class="alert alert-info"><?= $escape('drp_scope') ?></p>
    <?php if (!$https && !$local): ?><p class="alert alert-danger"><?= $escape('drp_transport') ?></p><?php endif; ?>
    <?php if ($initialJob === null && !$authorized): ?>
    <section class="card card-body mb-4">
        <p><?= $escape('drp_enable_hint') ?></p>
        <?php if (PHP_OS_FAMILY !== 'Windows'): ?>
        <p>PHP process account: <strong><?= htmlspecialchars($serviceAccount ?? 'not detected', ENT_QUOTES, 'UTF-8') ?></strong>.
            Run this command on the server. Use <code>sudo -u</code>; <code>sudo php</code> runs as root and creates files PHP cannot read.</p>
        <?php if ($serviceAccount === null): ?><p>Replace <code>PHP_SERVICE_ACCOUNT</code> below with the configured PHP-FPM/Apache account.</p><?php endif; ?>
        <?php else: ?>
        <p>Run this PowerShell command under the Windows identity configured for PHP.
            Find it in the Apache service's Log On settings or IIS Application Pools → Advanced Settings → Identity.
            An IIS virtual pool identity needs an administrator-managed execution context; do not substitute an unrelated administrator account.</p>
        <?php endif; ?>
        <pre class="bg-light p-3"><code><?= htmlspecialchars($commandBase . ' --enable', ENT_QUOTES, 'UTF-8') ?></code></pre>
        <?php if (PHP_OS_FAMILY !== 'Windows'): ?>
        <details class="mb-3"><summary>Common Linux service accounts</summary>
            <p>Debian/Ubuntu: <code>www-data</code>; RHEL/Rocky/AlmaLinux: usually <code>apache</code>;
                SUSE/openSUSE: usually <code>wwwrun</code>. A custom PHP-FPM pool can use another account; prefer the detected account above.</p>
        </details>
        <?php endif; ?>
        <form id="recovery-unlock" autocomplete="off">
            <label for="operator"><?= $escape('drp_operator') ?></label>
            <input class="form-control mb-3" id="operator" name="operator" type="password" required autocomplete="off">
            <input type="hidden" name="action" value="unlock">
            <button class="btn btn-primary" type="submit"><?= $escape('drp_unlock') ?></button>
        </form>
    </section>
    <?php elseif ($initialJob === null): ?>
    <form id="recovery-prepare" class="card card-body" autocomplete="off" enctype="multipart/form-data">
        <?php foreach (['kit' => ['drp_kit', 'tprecovery'], 'backup' => ['drp_backup', 'tpbackup']] as $field => $description): ?>
        <label for="<?= $field ?>"><?= $escape($description[0]) ?></label>
        <input class="form-control-file mb-2" type="file" id="<?= $field ?>" name="<?= $field ?>" accept=".<?= $description[1] ?>">
        <label class="small text-muted" for="<?= $field ?>-server"><?= $escape('drp_server_file') ?></label>
        <input class="form-control mb-3" id="<?= $field ?>-server" name="<?= $field ?>_server" type="text" autocomplete="off">
        <label for="<?= $field ?>-password"><?= $escape('drp_' . $field . '_password') ?></label>
        <input class="form-control mb-4" id="<?= $field ?>-password" name="<?= $field ?>_password" type="password" <?= $field === 'kit' ? 'required' : '' ?> autocomplete="off">
        <?php endforeach; ?>
        <h2 class="h5"><?= $escape('drp_target') ?></h2>
        <div class="row">
        <?php foreach (['host', 'port', 'database', 'user', 'password', 'url'] as $field): ?>
            <div class="col-md-6 mb-3">
                <label for="<?= $field ?>"><?= $escape('drp_' . $field) ?></label>
                <input class="form-control" id="<?= $field ?>" name="<?= $field ?>" type="<?= $field === 'password' ? 'password' : ($field === 'port' ? 'number' : ($field === 'url' ? 'url' : 'text')) ?>"
                    <?= $field === 'password' ? '' : 'required' ?> autocomplete="off" value="<?= $field === 'port' ? '3306' : '' ?>">
            </div>
        <?php endforeach; ?>
        </div>
        <input type="hidden" name="action" value="prepare">
        <button class="btn btn-primary align-self-start" type="submit"><?= $escape('drp_prepare') ?></button>
    </form>
    <?php endif; ?>
    <div id="recovery-message" class="mt-3" role="status" aria-live="polite"></div>
    <section id="recovery-result" class="card card-body mt-3" hidden>
        <p id="recovery-instruction"><?= $escape('drp_ready') ?></p>
        <?php if (PHP_OS_FAMILY !== 'Windows'): ?>
        <p class="small text-muted">Run the complete command below in a terminal on this server as
            <strong><?= htmlspecialchars($serviceAccount ?? 'the configured PHP service account', ENT_QUOTES, 'UTF-8') ?></strong>.
            Keep the <code>sudo -u</code> prefix shown in the command; <code>sudo php</code> runs as root.</p>
        <?php else: ?>
        <p class="small text-muted">Run the complete command below in PowerShell under the Windows identity configured for PHP in Apache or IIS.</p>
        <?php endif; ?>
        <pre id="recovery-plan" class="p-3 bg-light"></pre>
        <pre id="recovery-command" class="p-3 bg-light"></pre>
        <p id="recovery-activate-hint"><?= $escape('drp_activate_hint') ?></p>
        <pre id="recovery-activate" class="p-3 bg-light"></pre>
        <button id="recovery-refresh" type="button" class="btn btn-secondary align-self-start"><?= $escape('drp_status') ?></button>
    </section>
</main>
<script id="recovery-data" type="application/json"><?= json_encode([
    'csrf' => $_SESSION['recovery_csrf'], 'preparing' => $texts['drp_preparing'], 'error' => $texts['drp_error'],
    'ready' => $texts['drp_ready'], 'verified' => $texts['drp_verified'], 'complete' => $texts['drp_complete'], 'counts' => $texts['drp_counts'],
    'running' => $texts['drp_running'], 'failed' => $texts['drp_failed'], 'initial' => $initialJob,
    'command' => $commandBase,
    'errors' => ['OPERATOR_CODE_UNREADABLE' => 'The operator file was created by another account and PHP cannot read it. Have its ownership repaired, then use the PHP service account command shown above.'],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script src="recovery.js?v=<?= htmlspecialchars($scriptVersion, ENT_QUOTES, 'UTF-8') ?>" defer></script>
</body>
</html>
