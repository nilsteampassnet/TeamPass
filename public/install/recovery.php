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
$https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
if (session_status() !== PHP_SESSION_ACTIVE) {
    // The installation landing page can leave a plain PHPSESSID cookie. On a
    // fresh deployment, retire it before the restored encrypted handler is used.
    if (isset($_COOKIE[session_name()]) && teampassConfigState(TEAMPASS_ROOT . '/app/config') === 'not_installed') {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $cookie['path'],
            'domain' => $cookie['domain'], 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    }
    // Application sessions use an encrypted handler. Keep this DB-free operator
    // session separate so administrator/user sign-in cannot replace its progress.
    session_name('TEAMPASS_RECOVERY');
    $sessionPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/install/recovery.php'));
    session_start([
        'use_strict_mode' => true,
        'use_only_cookies' => true,
        'cookie_secure' => $https,
        'cookie_path' => rtrim($sessionPath, '/') . '/',
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
    ]);
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
$authorized = false;
try {
    $operator = tpRecoveryReadOperator(tpRecoveryJobDirectory() . '/operator.json');
    $authorized = (int) ($_SESSION['recovery_operator_until'] ?? 0) > time()
        && is_string($_SESSION['recovery_operator_hash'] ?? null)
        && hash_equals($operator['token_hash'], $_SESSION['recovery_operator_hash']);
} catch (TeamPassRecoveryException $exception) {
    // Reset, expiry and code rotation must return a stale browser to authorization.
}
if (!$authorized) {
    unset($_SESSION['recovery_operator_until'], $_SESSION['recovery_operator_hash']);
}
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
        return tpRecoveryLoadReceipt($id);
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
            $operator = tpRecoveryReadOperator(tpRecoveryJobDirectory() . '/operator.json');
            if ($action === 'unlock') {
                $provided = $_POST['operator'] ?? '';
                if (!is_string($provided) || !hash_equals($operator['token_hash'], hash('sha256', trim($provided)))) {
                    throw new TeamPassRecoveryException('OPERATOR_CODE_INVALID');
                }
                session_regenerate_id(true);
                $_SESSION['recovery_operator_until'] = $operator['expires_at'];
                $_SESSION['recovery_operator_hash'] = $operator['token_hash'];
                $result = ['unlocked' => true];
            } elseif ($action === 'prepare') {
                if (!$authorized || !hash_equals($operator['token_hash'], (string) ($_SESSION['recovery_operator_hash'] ?? ''))) {
                    throw new TeamPassRecoveryException('OPERATOR_AUTHORIZATION_REQUIRED');
                }
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
$styleVersion = substr((string) hash_file('sha256', __DIR__ . '/css/recovery.css'), 0, 16);
$scheduler = tpRecoverySchedulerInstructions(TEAMPASS_ROOT, PHP_OS_FAMILY, tpRecoveryPhpCliBinary(), $serviceAccount);
$cronServiceCommand = tpRecoveryCronServiceCommand(PHP_OS_FAMILY);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape('drp_title') ?></title>
    <link rel="stylesheet" href="../plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="css/recovery.css?v=<?= htmlspecialchars($styleVersion, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="bg-light recovery-page">
<main class="container py-4 recovery-wizard" style="max-width: 900px">
    <nav class="mb-4"><a href="install.php"><?= $escape('drp_back') ?></a></nav>
    <h1 class="h3"><i class="fas fa-life-ring mr-2" aria-hidden="true"></i><?= $escape('drp_title') ?></h1>
    <p><?= $escape('drp_intro') ?></p>
    <p class="alert alert-info"><?= $escape('drp_scope') ?></p>
    <ol class="row list-unstyled mb-4" aria-label="Recovery steps">
        <?php foreach (['Restore database', 'Publish instance', 'Secure permissions', 'Finalize restoration', 'Cleanup, access and cron'] as $index => $step): ?>
        <li class="col-6 col-md-4 mb-2"><span id="recovery-step-<?= $index + 1 ?>" class="badge badge-light d-block p-2"><?= $index + 1 ?>. <?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?></span></li>
        <?php endforeach; ?>
    </ol>
    <?php if (!$https && !$local): ?><p class="alert alert-danger"><?= $escape('drp_transport') ?></p><?php endif; ?>
    <div id="recovery-message" class="mt-3" role="alert" aria-live="assertive" aria-atomic="true" tabindex="-1"></div>
    <section id="recovery-authorization-help" class="card card-body mb-4" hidden>
        <p>Enable recovery on this server, then return to the operator-code screen before preparing restoration.</p>
        <pre id="recovery-reauthorize-command" data-recovery-command class="bg-light p-3"><?= htmlspecialchars($commandBase . ' --enable', ENT_QUOTES, 'UTF-8') ?></pre>
        <button id="recovery-reauthorize" class="btn btn-primary align-self-start" type="button">Enter operator code</button>
    </section>
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
        <pre id="recovery-enable-command" data-recovery-command class="bg-light p-3"><code><?= htmlspecialchars($commandBase . ' --enable', ENT_QUOTES, 'UTF-8') ?></code></pre>
        <?php if (PHP_OS_FAMILY !== 'Windows'): ?>
        <details class="mb-3"><summary>Common Linux service accounts</summary>
            <p>Debian/Ubuntu: <code>www-data</code>; RHEL/Rocky/AlmaLinux: usually <code>apache</code>;
                SUSE/openSUSE: usually <code>wwwrun</code>. A custom PHP-FPM pool can use another account; prefer the detected account above.</p>
        </details>
        <?php endif; ?>
        <form id="recovery-unlock" method="post" autocomplete="off" novalidate>
            <label for="operator"><?= $escape('drp_operator') ?></label>
            <input class="form-control mb-3" id="operator" name="operator" type="password" required autocomplete="off">
            <input type="hidden" name="action" value="unlock">
            <button class="btn btn-primary" type="submit"><?= $escape('drp_unlock') ?></button>
        </form>
    </section>
    <?php elseif ($initialJob === null): ?>
    <form id="recovery-prepare" method="post" class="card card-body" autocomplete="off" enctype="multipart/form-data" novalidate>
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
                    <?= $field === 'port' ? 'min="1" max="65535" step="1"' : '' ?>
                    <?= $field === 'password' ? '' : 'required' ?> autocomplete="off" value="<?= $field === 'port' ? '3306' : '' ?>">
            </div>
        <?php endforeach; ?>
        </div>
        <input type="hidden" name="action" value="prepare">
        <button class="btn btn-primary align-self-start" type="submit"><?= $escape('drp_prepare') ?></button>
    </form>
    <?php endif; ?>
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
        <section id="recovery-encryption" class="border-top pt-3 mb-3" hidden>
            <h2 class="h5">Encryption verification</h2>
            <p id="recovery-encryption-progress" role="status"></p>
            <p id="recovery-missing-sharekeys" class="alert alert-warning" hidden></p>
            <div id="recovery-unusable-tp-key" class="alert alert-warning" hidden>
                <p>The restored TP system private key cannot be used. The original master key matches the database,
                    but item decryption could not be verified with the system key.
                    Users' existing sharekeys are preserved and may still provide access.
                    After restoring access, review the TP key repair in Tools and each owner's personal-item repair.</p>
                <div id="recovery-key-acknowledgement" class="form-check" hidden>
                    <input id="recovery-acknowledge-tp-key" class="form-check-input" type="checkbox">
                    <label class="form-check-label" for="recovery-acknowledge-tp-key">I understand that item decryption could not be verified
                        and choose to publish this recovery with the unusable TP system key diagnostic.</label>
                </div>
            </div>
        </section>
        <pre id="recovery-command" data-recovery-command class="p-3 bg-light"></pre>
        <p id="recovery-activate-hint"><?= $escape('drp_activate_hint') ?></p>
        <pre id="recovery-activate" data-recovery-command class="p-3 bg-light"></pre>
        <section id="recovery-sensitive-staging" class="alert alert-warning" hidden>
            <p>A stopped recovery can retain the database password in <code>job.json</code>,
                the original key and decrypted staging files in its private job directory.
                Keep them restricted to the PHP service account while investigating.
                If you abandon this job, remove its staging with the command below.
                This does not reset the destination database or remove files already published.</p>
            <pre id="recovery-discard-command" data-recovery-command class="p-3 bg-light"></pre>
            <p class="mb-0">Files you placed in <code>storage/recovery/incoming/</code> and private CLI request files
                are not removed automatically. Preserve the encrypted backup and Recovery Package securely outside
                the deployment, then remove their staged copies and any request containing credentials.</p>
        </section>
        <section id="recovery-permissions" class="border-top pt-3 mt-3" hidden>
            <h2 class="h5">3. Secure file permissions</h2>
            <p>Publication automatically audits permissions using the File integrity rules.</p>
            <div id="recovery-permissions-audit" hidden>
                <p>No audit result is available yet. Run this fallback command, then click <strong>Refresh progress</strong>.</p>
                <pre id="recovery-permissions-command" data-recovery-command class="p-3 bg-light"></pre>
            </div>
            <p id="recovery-permissions-status" role="status"></p>
            <div id="recovery-permissions-plan" hidden>
                <p>Review and copy this <strong>single block</strong> into your server terminal as an administrator.
                    It applies the proposed rights and automatically runs the audit again as the PHP service account.
                    Code, configuration and the original master key are protected; runtime data stays writable by PHP.</p>
                <pre id="recovery-permissions-commands" data-recovery-command class="p-3 bg-light"></pre>
                <p>Wait for the block to finish, then click <strong>Refresh progress</strong>. Exit code 2 means permission findings remain.</p>
            </div>
            <p id="recovery-permissions-manual" hidden>Automatic remediation is unavailable for this platform or layout.
                Review the OS permissions/ACLs manually: PHP needs read access to code, configuration and keys, and write access only to runtime directories.
                See the <a href="https://documentation.teampass.net/#/install/file-permissions" target="_blank" rel="noopener">permission documentation</a>.</p>
        </section>
        <section id="recovery-resume" class="border-top pt-3 mt-3" hidden>
            <h2 class="h5">4. Finalize restoration</h2>
            <p>After reviewing permissions, run this command, wait for it to finish, then click <strong>Refresh progress</strong>.</p>
            <p>Keep cron stopped until the installer is removed and access checks are complete in the next step.</p>
            <pre id="recovery-resume-command" data-recovery-command class="p-3 bg-light"></pre>
            <p>Backup schedules and LAPR stay disabled until you review and enable them deliberately.</p>
        </section>
        <section id="recovery-finished" class="border-top pt-3 mt-3" hidden>
            <h2 class="h5">5. Cleanup, access checks and cron</h2>
            <p id="recovery-finished-status" class="alert alert-success"></p>
            <p class="alert alert-warning">Successful publication removes the job's sensitive staging and keeps a non-sensitive receipt.
                Files you placed in <code>storage/recovery/incoming/</code> and private CLI request files are retained.
                Preserve the encrypted backup and Recovery Package securely outside the deployment,
                then remove their staged copies and any request containing credentials.</p>
            <h3 class="h6">5.1 Remove the installer</h3>
            <p>Run this command as an administrator to remove <code>public/install</code> and allow application sign-in.
                <strong>Keep this page open for the instructions below; do not refresh it after removal.</strong></p>
            <pre id="recovery-cleanup-command" data-recovery-command class="p-3 bg-light"></pre>
            <h3 class="h6">5.2 Check the restored instance</h3>
            <p>Open TeamPass in another tab. Verify administrator and standard-user sign-in, HTTPS, the URL, decryption,
                personal folders, manager/read-only permissions, documents, MFA and external authentication.
                Reconcile external integrations and offline clients.</p>
            <a class="btn btn-primary mb-3" href="../index.php" target="_blank" rel="noopener">Open TeamPass login</a>
            <h3 class="h6">5.3 Restore scheduled processing (cron)</h3>
            <p>After the access checks, restore the host scheduler. The recovery hold is released, but restoring a database does not recreate a host cron entry.
                Review enabled tasks first; backup schedules and LAPR require separate review before enabling them.</p>
            <?php if (isset($scheduler['entry'])): ?>
            <p>Open the crontab for the PHP service account. Preserve other jobs and keep exactly one active TeamPass scheduler entry.</p>
            <pre data-recovery-command id="recovery-cron-edit" class="p-3 bg-light"><?= htmlspecialchars($scheduler['edit'], ENT_QUOTES, 'UTF-8') ?></pre>
            <p>Insert or uncomment this line in the crontab editor, then save. This is a cron entry, not a shell command:</p>
            <pre data-recovery-command data-copy-label="Copy cron entry" id="recovery-cron-entry" class="p-3 bg-light"><?= htmlspecialchars($scheduler['entry'], ENT_QUOTES, 'UTF-8') ?></pre>
            <?php if ($cronServiceCommand !== null): ?>
            <p>For this server's systemd cron service, enable/start it and check its status:</p>
            <pre data-recovery-command id="recovery-cron-service" class="p-3 bg-light"><?= htmlspecialchars($cronServiceCommand, ENT_QUOTES, 'UTF-8') ?></pre>
            <?php else: ?>
            <p>Restore the cron daemon or equivalent scheduler with your server's service manager/runbook.</p>
            <?php endif; ?>
            <?php elseif (isset($scheduler['program'])): ?>
            <p>In Windows Task Scheduler, restore or create one task running every minute under the intended PHP service identity.
                Program/script:</p>
            <pre data-recovery-command data-copy-label="Copy program" id="recovery-task-program" class="p-3 bg-light"><?= htmlspecialchars($scheduler['program'], ENT_QUOTES, 'UTF-8') ?></pre>
            <p>Add arguments:</p>
            <pre data-recovery-command data-copy-label="Copy arguments" id="recovery-task-arguments" class="p-3 bg-light"><?= htmlspecialchars($scheduler['arguments'], ENT_QUOTES, 'UTF-8') ?></pre>
            <?php else: ?>
            <p>Have an administrator restore a once-per-minute task for <code>app/sources/scheduler.php</code> under the PHP service identity.</p>
            <?php endif; ?>
            <p>After a minute, check the last cron execution and task results in TeamPass administration.
                See the <a href="https://documentation.teampass.net/#/manage/tasks" target="_blank" rel="noopener">scheduled tasks documentation</a>.</p>
        </section>
        <button id="recovery-refresh" type="button" class="btn btn-secondary align-self-start"><?= $escape('drp_status') ?></button>
    </section>
</main>
<script id="recovery-data" type="application/json"><?= json_encode([
    'csrf' => $_SESSION['recovery_csrf'], 'preparing' => $texts['drp_preparing'], 'error' => $texts['drp_error'],
    'ready' => $texts['drp_ready'], 'verified' => $texts['drp_verified'], 'complete' => $texts['drp_complete'], 'counts' => $texts['drp_counts'],
    'verificationPaused' => $texts['drp_verification_paused'],
    'running' => $texts['drp_running'], 'failed' => $texts['drp_failed'], 'initial' => $initialJob,
    'command' => $commandBase,
    'cleanup' => tpRecoveryInstallerCleanupCommand(TEAMPASS_ROOT, PHP_OS_FAMILY),
    'resumed' => $texts['drp_resumed'], 'resumedMaintenance' => $texts['drp_resumed_maintenance'],
    'permissionsNotChecked' => $texts['drp_permissions_not_checked'], 'permissionsPassed' => $texts['drp_permissions_passed'],
    'permissionsIssues' => $texts['drp_permissions_issues'], 'permissionsManual' => $texts['drp_permissions_manual'],
    'cleanupManual' => $texts['drp_cleanup_manual'],
    'fields' => ['operator' => $texts['drp_operator'], 'kit' => $texts['drp_kit'], 'backup' => $texts['drp_backup'],
        'kit_password' => $texts['drp_kit_password'], 'host' => $texts['drp_host'], 'port' => $texts['drp_port'],
        'database' => $texts['drp_database'], 'user' => $texts['drp_user'], 'url' => $texts['drp_url']],
    'errors' => [
        'OPERATOR_CODE_REQUIRED' => 'Recovery has not been enabled on this deployment. Run the enable command below, then enter the new operator code.',
        'OPERATOR_CODE_EXPIRED' => 'Recovery authorization has expired. Run the enable command again, then enter the new operator code.',
        'OPERATOR_AUTHORIZATION_REQUIRED' => 'This browser authorization no longer matches the server. Enable recovery again and enter the new operator code.',
        'OPERATOR_CODE_INVALID' => 'The operator code is incorrect. Check the pasted code or generate a new one with the enable command.',
        'OPERATOR_CODE_UNREADABLE' => 'The operator file was created by another account and PHP cannot read it. Have its ownership repaired, then use the PHP service account command shown above.',
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script src="recovery.js?v=<?= htmlspecialchars($scriptVersion, ENT_QUOTES, 'UTF-8') ?>" defer></script>
</body>
</html>
