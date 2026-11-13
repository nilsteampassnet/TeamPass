<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file permissions.view.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

if (!defined('TEAMPASS_ROOT') || !isset($permissionsFlow)
    || ($_SESSION['setup_permissions_ready'] ?? '') !== $permissionsFlow) {
    echo '<div class="alert alert-warning">Complete the final setup tasks before reviewing permissions.</div>';
    return;
}
if (empty($_SESSION['setup_permissions_csrf'])) {
    $_SESSION['setup_permissions_csrf'] = bin2hex(random_bytes(32));
}
?>
<section id="setup-permissions" class="card card-outline card-primary mt-4">
    <div class="card-header"><h5 class="mb-0">Secure file permissions</h5></div>
    <div class="card-body">
        <p>Before opening TeamPass, review its normal operating permissions. PHP needs read access to code,
            configuration and the encryption key, and write access only to runtime data.</p>
        <p id="setup-permissions-status" class="alert alert-info" role="status" aria-live="polite">Checking permissions… This may take a few moments.</p>
        <p id="setup-permissions-identity" class="text-muted"></p>
        <div id="setup-permissions-corrections" hidden>
            <p>Review and run this block in your server terminal as an administrator, then check again.
                TeamPass displays these commands and never executes them.</p>
            <button id="setup-permissions-copy" type="button" class="btn btn-sm btn-outline-secondary mb-2">Copy commands</button>
            <pre id="setup-permissions-commands" class="bg-light p-3" style="white-space:pre;overflow:auto;max-height:24rem"></pre>
        </div>
        <details id="setup-permissions-findings" hidden><summary>Permission findings</summary>
            <pre id="setup-permissions-findings-text" class="p-2" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre>
        </details>
        <p id="setup-permissions-no-plan" hidden>No automatic correction block is available for this platform or layout.
            Correct the findings manually using the
            <a href="https://documentation.teampass.net/#/install/file-permissions" target="_blank" rel="noopener">permission documentation</a>,
            then check again. A manual acknowledgement cannot override these findings.</p>
        <div id="setup-permissions-manual" class="alert alert-warning" hidden>
            <p>This platform or deployment requires a manual review of service accounts, filesystem permissions and ACLs.
                For containers, preserve the image entrypoint and volume policy.
                <a href="https://documentation.teampass.net/#/install/file-permissions" target="_blank" rel="noopener">Review the permission documentation</a>.</p>
            <label><input id="setup-permissions-ack" type="checkbox">
                I have completed this manual review. My acknowledgement will be recorded as a manual review, not an automatic audit pass.</label>
        </div>
        <p>For a future upgrade, temporarily unlock the installation paths again, then restore these operating permissions.</p>
        <button id="setup-permissions-refresh" type="button" class="btn btn-secondary">Check again</button>
        <button id="setup-permissions-finalize" type="button" class="btn btn-primary" disabled>Finalize setup</button>
        <div id="setup-permissions-complete" class="mt-3" hidden>
            <p>The permission review is recorded. Remove the installer using the command below before opening TeamPass.
                Keep this page open; removal also deletes this assistant.</p>
            <button id="setup-permissions-copy-cleanup" type="button" class="btn btn-sm btn-outline-secondary mb-2">Copy cleanup command</button>
            <pre id="setup-permissions-cleanup" class="bg-light p-3" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre>
            <a href="../index.php" target="_blank" rel="noopener" class="btn btn-primary">Open TeamPass login</a>
        </div>
    </div>
</section>
<script id="setup-permissions-data" type="application/json"><?= json_encode([
    'flow' => $permissionsFlow, 'csrf' => $_SESSION['setup_permissions_csrf'],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="permissions.js" defer></script>
