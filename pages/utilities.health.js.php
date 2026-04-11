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
 * @file      utilities.health.js.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;

// Load functions
require_once __DIR__ . '/../sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

if ($session->get('key') === null) {
    die('Hacking attempt...');
}

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('utilities.health') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>
<script type="text/javascript">
/* global store, toastr */

var tpHealthReportCache = null;
var tpHealthKey = "<?php echo $session->get('key'); ?>";


var TP_HEALTH_L10N = {
    status_ok: "<?php echo addslashes($lang->get('health_status_ok')); ?>",
    status_warning: "<?php echo addslashes($lang->get('warning')); ?>",
    status_error: "<?php echo addslashes($lang->get('error')); ?>",
    status_info: "<?php echo addslashes($lang->get('information')); ?>",
    generic_error: "<?php echo addslashes($lang->get('error')); ?>",
    no_data: "<?php echo addslashes($lang->get('health_no_data')); ?>",
    not_available: "<?php echo addslashes($lang->get('health_not_available')); ?>",
    personal: "<?php echo addslashes($lang->get('health_items_personal')); ?>",
    shared: "<?php echo addslashes($lang->get('health_items_shared')); ?>",
    ok: "<?php echo addslashes($lang->get('health_check_ok')); ?>",
    reason_mismatch: "<?php echo addslashes($lang->get('health_reason_mismatch')); ?>",
    reason_missing_seed: "<?php echo addslashes($lang->get('health_reason_missing_seed')); ?>",
    reason_missing_public_key: "<?php echo addslashes($lang->get('health_reason_missing_public_key')); ?>",
    reason_missing_hash: "<?php echo addslashes($lang->get('health_reason_missing_hash')); ?>",
    backup_old_fmt: "<?php echo addslashes($lang->get('health_backup_old_fmt')); ?>",
    backup_meta_orphans_fmt: "<?php echo addslashes($lang->get('health_backup_meta_orphans_fmt')); ?>",
    backup_compatible: "<?php echo addslashes($lang->get('health_backup_compatible')); ?>",
    backup_incompatible: "<?php echo addslashes($lang->get('health_backup_incompatible')); ?>",
    backup_unknown: "<?php echo addslashes($lang->get('health_backup_unknown')); ?>",
    backup_expected_schema_level_fmt: "<?php echo addslashes($lang->get('health_backup_expected_schema_level_fmt')); ?>",
    backup_expected_tp_files_version_fmt: "<?php echo addslashes($lang->get('health_backup_expected_tp_files_version_fmt')); ?>",
    backup_total_files: "<?php echo addslashes($lang->get('health_backup_total_files')); ?>",
    backup_schema_unknown: "<?php echo addslashes($lang->get('health_backup_schema_unknown')); ?>",
    backup_meta_missing: "<?php echo addslashes($lang->get('health_backup_meta_missing')); ?>",
    backup_meta_orphans: "<?php echo addslashes($lang->get('health_backup_meta_orphans')); ?>",
    backup_tp_version_mismatch: "<?php echo addslashes($lang->get('health_backup_tp_version_mismatch')); ?>",
    backup_last_backup: "<?php echo addslashes($lang->get('health_backup_last_backup')); ?>",
    backup_last_compatible_backup: "<?php echo addslashes($lang->get('health_backup_last_compatible_backup')); ?>",
    backup_type_scheduled: "<?php echo addslashes($lang->get('health_backup_scheduled')); ?>",
    backup_type_onthefly: "<?php echo addslashes($lang->get('health_backup_onthefly')); ?>",
    excluded_label: "<?php echo addslashes($lang->get('health_excluded_label')); ?>",
    excluded_deleted_users: "<?php echo addslashes($lang->get('health_excluded_deleted_users')); ?>",
    excluded_system_accounts: "<?php echo addslashes($lang->get('health_excluded_system_accounts')); ?>",
    excluded_disabled_users: "<?php echo addslashes($lang->get('health_excluded_disabled_users')); ?>",
    export_filename_default: "<?php echo addslashes($lang->get('health_export_filename')); ?>"
    ,
    corrupted_scan_running: "<?php echo addslashes($lang->get('health_corrupted_scan_running')); ?>",
    corrupted_scan_failed: "<?php echo addslashes($lang->get('health_corrupted_scan_failed')); ?>",
    corrupted_no_result: "<?php echo addslashes($lang->get('health_corrupted_no_result')); ?>",
    corrupted_truncated_note_fmt: "<?php echo addslashes($lang->get('health_corrupted_truncated_note_fmt')); ?>",
    corrupted_script_missing_fmt: "<?php echo addslashes($lang->get('health_corrupted_script_missing_fmt')); ?>",
    corrupted_script_invalid: "<?php echo addslashes($lang->get('health_corrupted_script_invalid')); ?>",
    corrupted_reason_empty_key: "<?php echo addslashes($lang->get('health_corrupted_reason_empty_key')); ?>",
    corrupted_reason_decrypt_failed: "<?php echo addslashes($lang->get('health_corrupted_reason_decrypt_failed')); ?>",
    corrupted_reason_binary_bytes: "<?php echo addslashes($lang->get('health_corrupted_reason_binary_bytes')); ?>",
    corrupted_reason_len_mismatch: "<?php echo addslashes($lang->get('health_corrupted_reason_len_mismatch')); ?>",
    corrupted_reason_exception_fmt: "<?php echo addslashes($lang->get('health_corrupted_reason_exception_fmt')); ?>",

    runtime_log_checking: "<?php echo addslashes($lang->get('health_runtime_log_loading')); ?>",
    runtime_log_failed: "<?php echo addslashes($lang->get('health_runtime_log_failed')); ?>",
    runtime_log_not_found_fmt: "<?php echo addslashes($lang->get('health_runtime_log_not_found_fmt')); ?>",
    runtime_log_not_readable_fmt: "<?php echo addslashes($lang->get('health_runtime_log_not_readable_fmt')); ?>",
    runtime_log_not_configured: "<?php echo addslashes($lang->get('health_log_not_configured')); ?>",
    runtime_log_not_detected: "<?php echo addslashes($lang->get('health_log_not_detected')); ?>",
    runtime_log_invalid_path: "<?php echo addslashes($lang->get('health_log_invalid_path')); ?>",
    runtime_log_not_used: "<?php echo addslashes($lang->get('health_log_not_used')); ?>",
    runtime_log_empty_fmt: "<?php echo addslashes($lang->get('health_runtime_log_empty_fmt')); ?>",
    runtime_log_fix_hint: "<?php echo addslashes($lang->get('health_runtime_log_fix_hint')); ?>",
    runtime_log_fix_hint_missing: "<?php echo addslashes($lang->get('health_runtime_log_fix_hint_missing')); ?>",
    runtime_logs_context_mode_fmt: "<?php echo addslashes($lang->get('health_runtime_logs_context_mode_fmt')); ?>",
    runtime_logs_context_server_fmt: "<?php echo addslashes($lang->get('health_runtime_logs_context_server_fmt')); ?>",
    logs_mode_auto: "<?php echo addslashes($lang->get('health_logs_mode_auto')); ?>",
    logs_mode_manual: "<?php echo addslashes($lang->get('health_logs_mode_manual')); ?>",
    copied: "<?php echo addslashes($lang->get('copied')); ?>",
    clipboard_error: "<?php echo addslashes($lang->get('clipboard_error')); ?>"

};

$(document).ready(function() {
    $('#health-refresh-btn').on('click', function() {
        tpLoadHealthReport();
    });

    $('#health-export-btn').on('click', function() {
        tpDownloadReportJson();
    });

    $('#health-corrupted-items-scan-btn').on('click', function() {
        tpScanCorruptedItems();
    });

    $('#health-corrupted-items-show-btn').on('click', function() {
        tpShowCorruptedItemsList();
    });

    $('#health-runtime-logs-check-btn').on('click', function() {
        tpCheckRuntimeLogs();
    });

    $('#health-server-log-copy-btn').on('click', function() {
        tpCopyRuntimeLogToClipboard('server');
    });

    $('#health-teampass-log-copy-btn').on('click', function() {
        tpCopyRuntimeLogToClipboard('teampass');
    });

    $('#health-php-fpm-log-copy-btn').on('click', function() {
        tpCopyRuntimeLogToClipboard('php-fpm');
    });

    tpLoadHealthReport();
});

function tpLoadHealthReport() {
    $('#health-export-btn').prop('disabled', true);
    $('#health-loading-row').show();

    $.post(
        'sources/utilities.queries.php',
        {
            type: 'get_health_report',
            data: prepareExchangedData(JSON.stringify({}), 'encode', tpHealthKey),
            key: tpHealthKey
        },
        function(data) {
            $('#health-loading-row').hide();

            data = prepareExchangedData(data, 'decode', tpHealthKey);

            if (data && data.error) {
                toastr.error(data.message || TP_HEALTH_L10N.generic_error);
                return;
            }

            if (!data || !data.report) {
                toastr.error(TP_HEALTH_L10N.no_data);
                return;
            }

            tpHealthReportCache = data.report;
            $('#health-export-btn').prop('disabled', false);

            tpRenderHealthReport(tpHealthReportCache);
        }
    ).fail(function() {
        $('#health-loading-row').hide();
        toastr.error(TP_HEALTH_L10N.generic_error);
    });
}

function tpRenderHealthReport(report) {
    if (!report) {
        return;
    }

    $('#health-generated-at').text('(' + (report.generated_at_human || '') + ')');

    tpRenderOverview(report);
    tpRenderSystem(report);
    tpRenderDatabase(report);
    tpRenderCrypto(report);
    tpRenderBackups(report);
    tpRenderLogs(report);
}

function tpEscapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function tpStatusToBadge(status) {
    var s = (status || 'info').toLowerCase();
    if (s === 'success') return '<span class="badge badge-success">' + tpEscapeHtml(TP_HEALTH_L10N.status_ok) + '</span>';
    if (s === 'warning') return '<span class="badge badge-warning">' + tpEscapeHtml(TP_HEALTH_L10N.status_warning) + '</span>';
    if (s === 'danger') return '<span class="badge badge-danger">' + tpEscapeHtml(TP_HEALTH_L10N.status_error) + '</span>';
    return '<span class="badge badge-info">' + tpEscapeHtml(TP_HEALTH_L10N.status_info) + '</span>';
}

function tpSetProgressBar($bar, percent) {
    var p = Math.max(0, Math.min(100, Number(percent || 0)));
    $bar.css('width', p + '%');
    $bar.attr('aria-valuenow', p);
}

function tpRenderOverview(report) {
    // Encryption
    var enc = report.overview && report.overview.encryption ? report.overview.encryption : null;
    $('#health-encryption-status').html(tpStatusToBadge(enc ? enc.status : 'info'));

    // Sessions
    var sess = report.overview && report.overview.sessions ? report.overview.sessions.count : 0;
    $('#health-sessions-count').text(Number(sess || 0));

    // Cron
    var cron = report.overview && report.overview.cron ? report.overview.cron : null;
    $('#health-cron-status').html(tpStatusToBadge(cron ? cron.status : 'info'));

    // Unknown files
    var unknown = report.overview && report.overview.unknown_files ? report.overview.unknown_files.count : 0;
    $('#health-unknown-files-count').text(Number(unknown || 0));

    // Migration progress
    var mig = report.overview && report.overview.migration ? report.overview.migration : null;
    if (mig && mig.users) {
                var usersTxt = (mig.users.percent_migrated || 0) + '% (' + (mig.users.migrated || 0) + '/' + (mig.users.total || 0) + ')';
        var excl = mig.users.excluded || {};
        var exclParts = [];
        if (Number(excl.system || 0) > 0) {
            exclParts.push(TP_HEALTH_L10N.excluded_system_accounts + ' ' + Number(excl.system || 0));
        }
        if (Number(excl.deleted || 0) > 0) {
            exclParts.push(TP_HEALTH_L10N.excluded_deleted_users + ' ' + Number(excl.deleted || 0));
        }
        if (Number(excl.disabled || 0) > 0) {
            exclParts.push(TP_HEALTH_L10N.excluded_disabled_users + ' ' + Number(excl.disabled || 0));
        }
        if (exclParts.length) {
            usersTxt += ' • ' + TP_HEALTH_L10N.excluded_label + ': ' + exclParts.join(' • ');
        }
        $('#health-migration-users-text').text(tpEscapeHtml(usersTxt));
        tpSetProgressBar($('#health-migration-users-bar'), mig.users.percent_migrated || 0);
    } else {
        $('#health-migration-users-text').text(TP_HEALTH_L10N.no_data);
        tpSetProgressBar($('#health-migration-users-bar'), 0);
    }

    if (mig && mig.sharekeys_items) {
        $('#health-migration-sharekeys-text').text(tpEscapeHtml((mig.sharekeys_items.percent_v3 || 0) + '% (' + (mig.sharekeys_items.v3 || 0) + '/' + (mig.sharekeys_items.total || 0) + ')'));
        tpSetProgressBar($('#health-migration-sharekeys-bar'), mig.sharekeys_items.percent_v3 || 0);
    } else {
        $('#health-migration-sharekeys-text').text(TP_HEALTH_L10N.no_data);
        tpSetProgressBar($('#health-migration-sharekeys-bar'), 0);
    }

    if (mig && mig.personal_items) {
        $('#health-migration-personal-text').text(tpEscapeHtml((mig.personal_items.percent_migrated || 0) + '% (' + (mig.personal_items.migrated || 0) + '/' + (mig.personal_items.total || 0) + ')'));
        tpSetProgressBar($('#health-migration-personal-bar'), mig.personal_items.percent_migrated || 0);
    } else {
        $('#health-migration-personal-text').text(TP_HEALTH_L10N.no_data);
        tpSetProgressBar($('#health-migration-personal-bar'), 0);
    }

    // Findings
    var find = report.overview && report.overview.findings ? report.overview.findings : {};
    $('#health-orphans-total').text(Number(find.sharekeys_orphans_total || 0));

    var integrityIssues = (Number(find.integrity_missing || 0) + Number(find.integrity_mismatch || 0));
    $('#health-integrity-issues').text(integrityIssues);

    $('#health-inconsistent-users').text(Number(find.inconsistent_users || 0));

    var corrupted = report.crypto && report.crypto.corrupted_items ? report.crypto.corrupted_items : {};
    $('#health-overview-corrupted-items').text(Number(corrupted.count || 0));

    $('#health-backup-status').html(tpStatusToBadge(find.backup_status || 'info'));

    $('#health-findings-details').text('');
}

function tpRenderSystem(report) {
    var sys = report.system || {};
    var cpu = sys.cpu || {};
    var mem = sys.memory || {};
    var disk = sys.disk || [];

    $('#health-cpu-load').text(tpEscapeHtml((cpu.load_1 || 0) + ' / ' + (cpu.load_5 || 0) + ' / ' + (cpu.load_15 || 0)));
    $('#health-cpu-cores').text(tpEscapeHtml((cpu.cores || 0) + ' ' + '<?php echo addslashes($lang->get('health_cpu_cores')); ?>'));

    // Memory
    $('#health-mem-usage').text(tpEscapeHtml((mem.used_mb || 0) + ' MB / ' + (mem.total_mb || 0) + ' MB (' + (mem.used_percent || 0) + '%)'));
    tpSetProgressBar($('#health-mem-bar'), mem.used_percent || 0);

    // Disk summary (first path)
    if (disk.length > 0) {
        var d0 = disk[0];
        $('#health-disk-summary').text(tpEscapeHtml((d0.free_gb || 0) + ' GB / ' + (d0.total_gb || 0) + ' GB'));
        $('#health-disk-details').text(tpEscapeHtml((d0.path || '') + ' (' + (d0.used_percent || 0) + '%)'));
    } else {
        $('#health-disk-summary').text(TP_HEALTH_L10N.no_data);
        $('#health-disk-details').text('');
    }

    // PHP ini table
    var php = sys.php || {};
    var ini = php.ini || {};
    var $iniBody = $('#health-php-ini');
    $iniBody.empty();

    $iniBody.append('<tr><td>PHP</td><td>' + tpEscapeHtml((php.version || '') + ' (' + (php.sapi || '') + ')') + '</td></tr>');

    Object.keys(ini).forEach(function(k) {
        $iniBody.append('<tr><td>' + tpEscapeHtml(k) + '</td><td><code>' + tpEscapeHtml(ini[k]) + '</code></td></tr>');
    });

    // Opcache table
    var op = sys.opcache || {};
    var $opBody = $('#health-opcache');
    $opBody.empty();

    if (!op.enabled) {
        $opBody.append('<tr><td colspan="2">' + tpEscapeHtml('<?php echo addslashes($lang->get('health_opcache_disabled')); ?>') + '</td></tr>');
    } else {
        var cfg = op.configuration || {};
        Object.keys(cfg).forEach(function(k) {
            $opBody.append('<tr><td>' + tpEscapeHtml(k) + '</td><td><code>' + tpEscapeHtml(cfg[k]) + '</code></td></tr>');
        });

        var st = op.status || {};
        Object.keys(st).forEach(function(k) {
            $opBody.append('<tr><td>' + tpEscapeHtml(k) + '</td><td><code>' + tpEscapeHtml(st[k]) + '</code></td></tr>');
        });
    }

    // Teampass settings
    var tp = sys.teampass_settings || {};
    var $tpBody = $('#health-teampass-settings');
    $tpBody.empty();

    Object.keys(tp).forEach(function(k) {
        $tpBody.append('<tr><td>' + tpEscapeHtml(k) + '</td><td><code>' + tpEscapeHtml(tp[k]) + '</code></td></tr>');
    });

    // Checks
    var checks = sys.checks || [];
    var $checks = $('#health-system-checks');
    $checks.empty();

    if (!checks.length) {
        $checks.append('<div class="text-muted">' + tpEscapeHtml(TP_HEALTH_L10N.no_data) + '</div>');
    } else {
        checks.forEach(function(ch) {
            var statusBadge = tpStatusToBadge(ch.status || 'info');
            $checks.append(
                '<div class="callout callout-' + tpEscapeHtml((ch.status || 'info').toLowerCase() === 'danger' ? 'danger' : (ch.status || 'info')) + '">' +
                '<h6>' + statusBadge + ' ' + tpEscapeHtml(ch.title || '') + '</h6>' +
                '<div>' + tpEscapeHtml(ch.text || '') + '</div>' +
                '</div>'
            );
        });
    }
}

function tpRenderDatabase(report) {
    var db = report.database || {};

    $('#health-db-version').text(tpEscapeHtml(db.version || ''));
    $('#health-db-version').attr('title', String(db.version_full || db.version || ''));
    $('#health-db-latency').text(tpEscapeHtml((db.latency_ms || 0) + ' ms'));
    $('#health-db-size').text(tpEscapeHtml((db.size_mb || 0) + ' MB'));

    var tables = db.tables_top || [];
    var $tbody = $('#health-db-tables');
    $tbody.empty();

    if (!tables.length) {
        $tbody.append('<tr><td colspan="6" class="text-muted">' + tpEscapeHtml(TP_HEALTH_L10N.no_data) + '</td></tr>');
        return;
    }

    tables.forEach(function(t) {
        $tbody.append(
            '<tr>' +
            '<td><code>' + tpEscapeHtml(t.name || '') + '</code></td>' +
            '<td>' + tpEscapeHtml(t.rows || 0) + '</td>' +
            '<td>' + tpEscapeHtml((t.size_mb || 0) + ' MB') + '</td>' +
            '<td>' + tpEscapeHtml((t.free_mb || 0) + ' MB') + '</td>' +
            '<td>' + tpEscapeHtml(t.engine || '') + '</td>' +
            '<td>' + tpStatusToBadge(t.status || 'info') + '</td>' +
            '</tr>'
        );
    });

    $('#health-db-note').text('<?php echo addslashes($lang->get('health_db_fragmentation_note')); ?>');
}

function tpRenderCrypto(report) {
    var crypto = report.crypto || {};
    var share = crypto.sharekeys || {};

    // Sharekeys stats
    var stats = share.tables || [];
    var $sk = $('#health-sharekeys-stats');
    $sk.empty();

    if (!stats.length) {
        $sk.append('<tr><td colspan="5" class="text-muted">' + tpEscapeHtml(TP_HEALTH_L10N.no_data) + '</td></tr>');
    } else {
        stats.forEach(function(s) {
            $sk.append(
                '<tr>' +
                '<td><code>' + tpEscapeHtml(s.table || '') + '</code></td>' +
                '<td>' + tpEscapeHtml(s.total || 0) + '</td>' +
                '<td>' + tpEscapeHtml(s.v1 || 0) + '</td>' +
                '<td>' + tpEscapeHtml(s.v3 || 0) + '</td>' +
                '<td>' + tpEscapeHtml(s.nulls || 0) + '</td>' +
                '</tr>'
            );
        });
    }

    // Items personal/shared
    var persoRaw = share.items_perso || [];
    var agg = { personal: {v1:0,v3:0,total:0}, shared:{v1:0,v3:0,total:0} };
    persoRaw.forEach(function(r) {
        var scope = Number(r.perso || 0) === 1 ? 'personal' : 'shared';
        var v = Number(r.encryption_version || 0);
        var c = Number(r.count || 0);
        if (v === 1) agg[scope].v1 += c;
        if (v === 3) agg[scope].v3 += c;
        agg[scope].total += c;
    });

    var $perso = $('#health-sharekeys-items-perso');
    $perso.empty();
    $perso.append('<tr><td>' + tpEscapeHtml(TP_HEALTH_L10N.personal) + '</td><td>' + agg.personal.v1 + '</td><td>' + agg.personal.v3 + '</td><td>' + agg.personal.total + '</td></tr>');
    $perso.append('<tr><td>' + tpEscapeHtml(TP_HEALTH_L10N.shared) + '</td><td>' + agg.shared.v1 + '</td><td>' + agg.shared.v3 + '</td><td>' + agg.shared.total + '</td></tr>');

    // Orphans
    var orphans = share.orphans || {};
    var $orph = $('#health-sharekeys-orphans');
    $orph.empty();

    var keys = Object.keys(orphans);
    if (!keys.length) {
        $orph.append('<div class="text-muted">' + tpEscapeHtml(TP_HEALTH_L10N.no_data) + '</div>');
    } else {
        keys.forEach(function(k) {
            var o = orphans[k] || {};
            $orph.append(
                '<div class="callout callout-warning">' +
                '<h6><code>' + tpEscapeHtml(k) + '</code></h6>' +
                '<div>' +
                '<?php echo addslashes($lang->get('health_orphans_missing_object')); ?>: <b>' + (o.missing_object === null ? '-' : tpEscapeHtml(o.missing_object)) + '</b> &nbsp; ' +
                '<?php echo addslashes($lang->get('health_orphans_missing_user')); ?>: <b>' + (o.missing_user === null ? '-' : tpEscapeHtml(o.missing_user)) + '</b> &nbsp; ' +
                '<?php echo addslashes($lang->get('health_orphans_inactive_user')); ?>: <b>' + (o.inactive_user === null ? '-' : tpEscapeHtml(o.inactive_user)) + '</b>' +
                '</div>' +
                '</div>'
            );
        });
    }

    // Integrity hash
    var integ = crypto.integrity_hash || {};
    $('#health-integrity-missing').text(Number(integ.missing || 0));
    $('#health-integrity-mismatch').text(Number(integ.mismatch || 0));
    $('#health-integrity-ok').text(Number(integ.ok || 0));

    var $iu = $('#health-integrity-users');
    $iu.empty();
    var iu = integ.users || [];
    if (!iu.length) {
        $iu.append('<tr><td colspan="6" class="text-muted">' + tpEscapeHtml(TP_HEALTH_L10N.no_data) + '</td></tr>');
    } else {
        iu.forEach(function(u) {
            var reason = u.reason || '';
            var reasonText = reason;
            if (reason === 'mismatch') reasonText = TP_HEALTH_L10N.reason_mismatch;
            if (reason === 'missing_seed') reasonText = TP_HEALTH_L10N.reason_missing_seed;
            if (reason === 'missing_public_key') reasonText = TP_HEALTH_L10N.reason_missing_public_key;
            if (reason === 'missing_hash') reasonText = TP_HEALTH_L10N.reason_missing_hash;

            $iu.append(
                '<tr>' +
                '<td>' + tpEscapeHtml(u.id || 0) + '</td>' +
                '<td><code>' + tpEscapeHtml(u.login || '') + '</code></td>' +
                '<td>' + tpEscapeHtml(reasonText) + '</td>' +
                '</tr>'
            );
        });
    }

    if (integ.available === false) {
        $('#health-integrity-note').text('<?php echo addslashes($lang->get('health_integrity_not_available')); ?>');
    } else {
        $('#health-integrity-note').text('');
    }

    // Inconsistent users
    var inconsist = crypto.inconsistent_users || [];
    var $inco = $('#health-inconsistent-users-table');
    $inco.empty();
    if (!inconsist.length) {
        $inco.append('<tr><td colspan="6" class="text-muted">' + tpEscapeHtml(TP_HEALTH_L10N.no_data) + '</td></tr>');
    } else {
        inconsist.forEach(function(u) {
            $inco.append(
                '<tr>' +
                '<td>' + tpEscapeHtml(u.id || 0) + '</td>' +
                '<td><code>' + tpEscapeHtml(u.login || '') + '</code></td>' +
                '<td>' + tpEscapeHtml(u.v1_sharekeys || 0) + '</td>' +
                '</tr>'
            );
        });
    }

    // Indicators (top of tab)
    $('#health-crypto-orphans').text(Number(share.orphans_total || 0));
    $('#health-crypto-integrity-issues').text(Number(integ.missing || 0) + Number(integ.mismatch || 0));
    $('#health-crypto-inconsistent-users').text(Number(inconsist.length || 0));
    // Corrupted items (on-demand scan stored in session)
    var corrupted = crypto.corrupted_items || {};
    $('#health-corrupted-items-count').text(Number(corrupted.count || 0));
    $('#health-corrupted-items-last-scan').text(tpEscapeHtml(corrupted.last_scan_at_human || ''));
    $('#health-corrupted-items-show-btn').prop('disabled', (corrupted.has_result !== true));

    // Note: excluded users (system / deleted / disabled) for coherence with main dashboard
    var note = '';
    var migOv = (report.overview && report.overview.migration) ? report.overview.migration : {};
    if (migOv && migOv.users && migOv.users.excluded) {
        var e = migOv.users.excluded || {};
        var parts = [];
        if (Number(e.system || 0) > 0) {
            parts.push(TP_HEALTH_L10N.excluded_system_accounts + ' ' + Number(e.system || 0));
        }
        if (Number(e.deleted || 0) > 0) {
            parts.push(TP_HEALTH_L10N.excluded_deleted_users + ' ' + Number(e.deleted || 0));
        }
        if (Number(e.disabled || 0) > 0) {
            parts.push(TP_HEALTH_L10N.excluded_disabled_users + ' ' + Number(e.disabled || 0));
        }
        if (parts.length) {
            note = TP_HEALTH_L10N.excluded_label + ': ' + parts.join(' • ');
        }
    }
    $('#health-crypto-note').text(tpEscapeHtml(note));

    var migOverview = (report.overview && report.overview.migration) ? report.overview.migration : {};
    var percentMigrated = 0;

    // Prefer global migration stats (encryption_migration_stats) when available
    if (migOverview.overall && Number(migOverview.overall.total_records || 0) > 0) {
        percentMigrated = Number(migOverview.overall.percent_v3 || 0);
    } else if (migOverview.sharekeys_items && Number(migOverview.sharekeys_items.total || 0) > 0) {
        // Fallback: sharekeys_items distribution is a reliable indicator in all instances
        percentMigrated = Number(migOverview.sharekeys_items.percent_v3 || 0);
    } else if (migOverview.users) {
        // Last fallback: users keys migration
        percentMigrated = Number(migOverview.users.percent_migrated || 0);
    }

    $('#health-crypto-users-migration').text(String(percentMigrated || 0) + '%');


    // User mismatch v3/v1
    var mism = crypto.users_sharekeys_mismatch || {};
    var $v3v1 = $('#health-v3-users-v1');
    var $v1v3 = $('#health-v1-users-v3');
    $v3v1.empty();
    $v1v3.empty();

    var listA = mism.v3_users_with_v1_sharekeys || [];
    var listB = mism.v1_users_with_v3_sharekeys || [];

    if (!listA.length) {
        $v3v1.append('<tr><td colspan="2" class="text-muted">' + tpEscapeHtml(TP_HEALTH_L10N.no_data) + '</td></tr>');
    } else {
        listA.forEach(function(u) {
            $v3v1.append('<tr><td><code>' + tpEscapeHtml(u.login || '') + '</code></td><td>' + tpEscapeHtml(u.sharekeys || 0) + '</td></tr>');
        });
    }

    if (!listB.length) {
        $v1v3.append('<tr><td colspan="2" class="text-muted">' + tpEscapeHtml(TP_HEALTH_L10N.no_data) + '</td></tr>');
    } else {
        listB.forEach(function(u) {
            $v1v3.append('<tr><td><code>' + tpEscapeHtml(u.login || '') + '</code></td><td>' + tpEscapeHtml(u.sharekeys || 0) + '</td></tr>');
        });
    }

    $('#health-crypto-note').text('');
}

function tpBackupCompatibilityToBadge(value) {
    var v = (value || 'unknown').toLowerCase();
    if (v === 'compatible') {
        return '<span class="badge badge-success">' + tpEscapeHtml(TP_HEALTH_L10N.backup_compatible) + '</span>';
    }
    if (v === 'incompatible') {
        return '<span class="badge badge-danger">' + tpEscapeHtml(TP_HEALTH_L10N.backup_incompatible) + '</span>';
    }
    return '<span class="badge badge-secondary">' + tpEscapeHtml(TP_HEALTH_L10N.backup_unknown) + '</span>';
}

function tpBackupSchedulerStatusToBadge(rawStatus) {
    var s = (rawStatus || '').toString().toLowerCase();
    var mapped = 'info';

    if (!s) {
        mapped = 'info';
    } else if (s.indexOf('success') !== -1 || s.indexOf('ok') !== -1 || s.indexOf('done') !== -1 || s.indexOf('finish') !== -1 || s.indexOf('complete') !== -1) {
        mapped = 'success';
    } else if (s.indexOf('error') !== -1 || s.indexOf('fail') !== -1) {
        mapped = 'danger';
    } else if (s.indexOf('warn') !== -1) {
        mapped = 'warning';
    } else if (s.indexOf('run') !== -1 || s.indexOf('progress') !== -1) {
        mapped = 'info';
    }

    return tpStatusToBadge(mapped);
}

function tpRenderBackups(report) {
    var b = report.backups || {};
    var dirs = b.directories || {};

    var scheduled = dirs.scheduled || {};
    var onthefly = dirs.onthefly || {};

    var schedStats = scheduled.stats || {};
    var flyStats = onthefly.stats || {};

    // Indicators
    var sCompat = Number(schedStats.compatible || 0);
    var sTotal = Number(schedStats.total_files || 0);
    var fCompat = Number(flyStats.compatible || 0);
    var fTotal = Number(flyStats.total_files || 0);

    $('#health-backups-scheduled-compatible').text(String(sCompat) + '/' + String(sTotal));
    $('#health-backups-onthefly-compatible').text(String(fCompat) + '/' + String(fTotal));
    $('#health-backups-anomalies').text(String(Number((b.summary && b.summary.anomalies_total) || 0)));

    // Paths
    $('#health-backup-scheduled-path').text(tpEscapeHtml(scheduled.path || ''));
    $('#health-backup-onthefly-path').text(tpEscapeHtml(onthefly.path || ''));

    // Scheduler card + "last job" indicator
    var sch = b.scheduler || {};
    $('#health-backups-scheduler-output-dir').text(tpEscapeHtml(sch.output_dir || ''));
    $('#health-backups-scheduler-next-run').text(tpEscapeHtml(sch.next_run_at_human || ''));
    $('#health-backups-scheduler-last-run').text(tpEscapeHtml(sch.last_run_at_human || ''));
    $('#health-backups-scheduler-last-message').text(tpEscapeHtml(sch.last_message || ''));
    $('#health-backups-scheduler-last-completed').text(tpEscapeHtml(sch.last_completed_at_human || ''));

    var lastStatus = sch.last_status || '';
    if (lastStatus) {
        $('#health-backups-scheduler-last-status').html(tpBackupSchedulerStatusToBadge(lastStatus));
        $('#health-backups-last-job').html(tpBackupSchedulerStatusToBadge(lastStatus));
    } else {
        $('#health-backups-scheduler-last-status').text('');
        $('#health-backups-last-job').text('-');
    }
    $('#health-backups-last-job-at').text(tpEscapeHtml(sch.last_completed_at_human || sch.last_run_at_human || ''));

    // Directories summary table
    var $sum = $('#health-backups-dirs-summary');
    $sum.empty();

    function addRow(label, a, b) {
        $sum.append('<tr><td>' + tpEscapeHtml(label) + '</td><td>' + tpEscapeHtml(a) + '</td><td>' + tpEscapeHtml(b) + '</td></tr>');
    }

    addRow(TP_HEALTH_L10N.backup_total_files, Number(schedStats.total_files || 0), Number(flyStats.total_files || 0));
    addRow(TP_HEALTH_L10N.backup_compatible, Number(schedStats.compatible || 0), Number(flyStats.compatible || 0));
    addRow(TP_HEALTH_L10N.backup_incompatible, Number(schedStats.incompatible || 0), Number(flyStats.incompatible || 0));
    addRow(TP_HEALTH_L10N.backup_schema_unknown, Number(schedStats.unknown_schema || 0), Number(flyStats.unknown_schema || 0));
    addRow(TP_HEALTH_L10N.backup_meta_missing, Number(schedStats.missing_meta || 0), Number(flyStats.missing_meta || 0));
    addRow(TP_HEALTH_L10N.backup_meta_orphans, Number(schedStats.meta_orphans || 0), Number(flyStats.meta_orphans || 0));
    addRow(TP_HEALTH_L10N.backup_tp_version_mismatch, Number(schedStats.tp_version_mismatch || 0), Number(flyStats.tp_version_mismatch || 0));
    addRow(TP_HEALTH_L10N.backup_last_backup, tpEscapeHtml(schedStats.last_backup_at_human || ''), tpEscapeHtml(flyStats.last_backup_at_human || ''));
    addRow(TP_HEALTH_L10N.backup_last_compatible_backup, tpEscapeHtml(schedStats.last_compatible_backup_at_human || ''), tpEscapeHtml(flyStats.last_compatible_backup_at_human || ''));

    // Directories note (expected schema/version)
    var dirNote = '';
    if (b.expected_schema_level) {
        dirNote += TP_HEALTH_L10N.backup_expected_schema_level_fmt.replace('%s', tpEscapeHtml(b.expected_schema_level));
    }
    if (b.expected_tp_files_version) {
        if (dirNote) dirNote += ' • ';
        dirNote += TP_HEALTH_L10N.backup_expected_tp_files_version_fmt.replace('%s', tpEscapeHtml(b.expected_tp_files_version));
    }
    $('#health-backups-dirs-note').text(dirNote);

    // Directories status (per type)
    var dirsStatusHtml = '';
    function addDirStatusLine(label, dirObj) {
        var sumObj = (dirObj && dirObj.summary) ? dirObj.summary : {};
        if (sumObj && sumObj.text) {
            dirsStatusHtml += '<div>' + tpEscapeHtml(label) + ': ' + tpStatusToBadge(sumObj.status || 'info') + '</div>';
        }
    }
    addDirStatusLine(TP_HEALTH_L10N.backup_type_scheduled, scheduled);
    addDirStatusLine(TP_HEALTH_L10N.backup_type_onthefly, onthefly);
    $('#health-backups-dirs-status').html(dirsStatusHtml);

    // Key backups (latest + latest compatible)
    var $keyFiles = $('#health-backups-key-files');
    $keyFiles.empty();

    function fileInfoHtml(fileName, dateHuman, sizeMb, comment) {
        if (!fileName) {
            return '<span class="text-muted">-</span>';
        }
        var meta = [];
        if (dateHuman) meta.push(tpEscapeHtml(dateHuman));
        if (sizeMb !== null && sizeMb !== undefined) meta.push(tpEscapeHtml(sizeMb));
        if (comment) meta.push(tpEscapeHtml(comment));

        var html = '<div><code>' + tpEscapeHtml(fileName) + '</code></div>';
        if (meta.length) {
            html += '<div class="small text-muted">' + meta.join(' • ') + '</div>';
        }
        return html;
    }

    function addKeyRow(typeLabel, dirStats) {
        dirStats = dirStats || {};
        var last = fileInfoHtml(dirStats.last_backup_file || '', dirStats.last_backup_at_human || '', dirStats.last_backup_size_mb, dirStats.last_backup_comment || '');
        var lastCompat = fileInfoHtml(dirStats.last_compatible_backup_file || '', dirStats.last_compatible_backup_at_human || '', dirStats.last_compatible_backup_size_mb, dirStats.last_compatible_backup_comment || '');
        $keyFiles.append(
            '<tr>' +
            '<td>' + tpEscapeHtml(typeLabel) + '</td>' +
            '<td>' + last + '</td>' +
            '<td>' + lastCompat + '</td>' +
            '<td>' + tpBackupCompatibilityToBadge(dirStats.last_backup_compatibility || 'unknown') + '</td>' +
            '</tr>'
        );
    }

    addKeyRow(TP_HEALTH_L10N.backup_type_scheduled, schedStats);
    addKeyRow(TP_HEALTH_L10N.backup_type_onthefly, flyStats);

    // Note (overall summary + last backup age)
    var noteHtml = '';
    var sumStatus = (b.summary && b.summary.status) ? b.summary.status : 'info';
    var sumText = (b.summary && b.summary.text) ? b.summary.text : '';
    if (sumText) {
        noteHtml += tpStatusToBadge(sumStatus) + ' ' + tpEscapeHtml(sumText);
    }
    if (b.summary && b.summary.last_backup_age_hours !== null && b.summary.last_backup_age_hours !== undefined) {
        var ageTxt = TP_HEALTH_L10N.backup_old_fmt.replace('%s', b.summary.last_backup_age_hours);
        noteHtml += (noteHtml ? ' <span class="text-muted ml-2">• ' + tpEscapeHtml(ageTxt) + '</span>' : tpEscapeHtml(ageTxt));
    }
    $('#health-backups-key-files-note').html(noteHtml);

    // Backup history
    var $hist = $('#health-backups-history');
    $hist.empty();

    var h = b.backup_history || [];
    if (!h.length) {
        $hist.append('<tr><td colspan="6" class="text-muted">' + tpEscapeHtml(TP_HEALTH_L10N.no_data) + '</td></tr>');
    } else {
        h.forEach(function(x) {
            var typeLabel = (x.type || '').toLowerCase() === 'scheduled' ? TP_HEALTH_L10N.backup_type_scheduled : TP_HEALTH_L10N.backup_type_onthefly;
            $hist.append(
                '<tr>' +
                '<td>' + tpEscapeHtml(x.mtime_human || '') + '</td>' +
                '<td>' + tpEscapeHtml(typeLabel) + '</td>' +
                '<td><code>' + tpEscapeHtml(x.name || '') + '</code></td>' +
                '<td>' + tpEscapeHtml((x.size_mb || 0) + ' MB') + '</td>' +
                '<td>' + tpBackupCompatibilityToBadge(x.compatibility || '') + '</td>' +
                '<td>' + tpEscapeHtml(x.comment || '') + '</td>' +
                '</tr>'
            );
        });
    }
    $('#health-backups-history-note').text('');

}

function tpRenderBackupFiles(tbodySelector, files) {
    var $tbody = $(tbodySelector);
    $tbody.empty();

    if (files && files.error) {
        $tbody.append('<tr><td colspan="6" class="text-muted">' + tpEscapeHtml('<?php echo addslashes($lang->get('health_backup_path_not_readable')); ?>') + '</td></tr>');
        return;
    }

    if (!files || !files.length) {
        $tbody.append('<tr><td colspan="6" class="text-muted">' + tpEscapeHtml('<?php echo addslashes($lang->get('health_backup_no_files')); ?>') + '</td></tr>');
        return;
    }

    files.forEach(function(x) {
        $tbody.append(
            '<tr>' +
            '<td><code>' + tpEscapeHtml(x.name || '') + '</code></td>' +
            '<td>' + tpEscapeHtml(x.mtime_human || '') + '</td>' +
            '<td>' + tpEscapeHtml((x.size_mb || 0) + ' MB') + '</td>' +
            '<td>' + tpEscapeHtml(x.schema_level || '') + '</td>' +
            '<td>' + tpEscapeHtml(x.tp_files_version || '') + '</td>' +
            '<td>' + tpEscapeHtml(x.comment || '') + '</td>' +
            '</tr>'
        );
    });
}

function tpRenderBackupNote(targetSelector, summary, metaOrphans) {
    var note = '';
    if (summary && summary.last_backup_age_hours !== null && summary.last_backup_age_hours !== undefined) {
        note = TP_HEALTH_L10N.backup_old_fmt.replace('%s', summary.last_backup_age_hours);
    }
    if (metaOrphans && metaOrphans > 0) {
        if (note !== '') {
            note += ' — ';
        }
        note += TP_HEALTH_L10N.backup_meta_orphans_fmt.replace('%d', metaOrphans);
    }

    $(targetSelector).text(note);
}


function tpRenderLogs(report) {
    var logs = report.logs || {};
    $('#health-total-logs').text(Number(logs.total || 0));
    $('#health-error-logs').text(Number(logs.errors || 0));
    $('#health-connection-logs').text(Number(logs.connections || 0));
    tpRenderRuntimeLogsContext(logs.runtime_context || {});

    var $labels = $('#health-top-labels');
    $labels.empty();
    var tl = logs.top_labels || [];
    if (!tl.length) {
        $labels.append('<tr><td colspan="2" class="text-muted">' + tpEscapeHtml(TP_HEALTH_L10N.no_data) + '</td></tr>');
    } else {
        tl.forEach(function(r) {
            $labels.append('<tr><td>' + tpEscapeHtml(r.label || '') + '</td><td>' + tpEscapeHtml(r.count || 0) + '</td></tr>');
        });
    }

    var $users = $('#health-top-users');
    $users.empty();
    var tu = logs.top_users || [];
    if (!tu.length) {
        $users.append('<tr><td colspan="2" class="text-muted">' + tpEscapeHtml(TP_HEALTH_L10N.no_data) + '</td></tr>');
    } else {
        tu.forEach(function(r) {
                        var userLabel = '';
            if (r.user_id && Number(r.user_id) > 0) {
                userLabel += '<code>' + tpEscapeHtml(r.user_id) + '</code>';
            } else {
                userLabel += '<code>' + tpEscapeHtml(r.qui || '') + '</code>';
            }
            var fullName = ((r.user_name || '') + ' ' + (r.user_lastname || '')).trim();
            if (fullName) {
                userLabel += ' ' + tpEscapeHtml(fullName);
            }
            $users.append('<tr><td>' + userLabel + '</td><td>' + tpEscapeHtml(r.count || 0) + '</td></tr>');
});
    }

    var $recent = $('#health-recent-events');
    $recent.empty();
    var rec = logs.recent || [];
    if (!rec.length) {
        $recent.append('<tr><td colspan="4" class="text-muted">' + tpEscapeHtml(TP_HEALTH_L10N.no_data) + '</td></tr>');
    } else {
        rec.forEach(function(r) {
            $recent.append(
                '<tr>' +
                '<td>' + tpEscapeHtml(r.date_human || '') + '</td>' +
                '<td>' + tpEscapeHtml(r.type || '') + '</td>' +
                '<td>' + tpEscapeHtml(r.label || '') + '</td>' +
                '<td>' + tpEscapeHtml(r.qui || '') + '</td>' +
                '</tr>'
            );
        });
    }
}

function tpCorruptedReasonToLabel(reason, exceptionMsg) {
    var r = String(reason || '');
    if (r === 'empty_key') return TP_HEALTH_L10N.corrupted_reason_empty_key;
    if (r === 'decrypt_failed') return TP_HEALTH_L10N.corrupted_reason_decrypt_failed;
    if (r === 'binary_bytes') return TP_HEALTH_L10N.corrupted_reason_binary_bytes;
    if (r === 'len_mismatch') return TP_HEALTH_L10N.corrupted_reason_len_mismatch;
    if (r === 'exception') {
        return TP_HEALTH_L10N.corrupted_reason_exception_fmt.replace('%s', String(exceptionMsg || ''));
    }
    return r;
}

function tpScanCorruptedItems() {
    var $btn = $('#health-corrupted-items-scan-btn');
    var $icon = $btn.find('i');
    $btn.prop('disabled', true);
    $icon.removeClass('fa-search').addClass('fa-spinner fa-spin');

    $.post(
        'sources/utilities.queries.php',
        {
            type: 'health_scan_corrupted_items',
            data: prepareExchangedData(JSON.stringify({}), 'encode', tpHealthKey),
            key: tpHealthKey
        },
        function(data) {
            data = prepareExchangedData(data, 'decode', tpHealthKey);

            $btn.prop('disabled', false);
            $icon.removeClass('fa-spinner fa-spin').addClass('fa-search');

            if (data && data.error) {
                toastr.error(data.message || TP_HEALTH_L10N.corrupted_scan_failed);
                return;
            }

            if (!data || !data.result) {
                toastr.error(TP_HEALTH_L10N.corrupted_scan_failed);
                return;
            }

            var r = data.result;
            $('#health-corrupted-items-count').text(Number(r.count || 0));
            $('#health-overview-corrupted-items').text(Number(r.count || 0));
            $('#health-corrupted-items-last-scan').text(tpEscapeHtml(r.last_scan_at_human || ''));
            $('#health-corrupted-items-show-btn').prop('disabled', false);

        }
    ).fail(function() {
        $btn.prop('disabled', false);
        $icon.removeClass('fa-spinner fa-spin').addClass('fa-search');
        toastr.error(TP_HEALTH_L10N.corrupted_scan_failed);
    });
}

function tpShowCorruptedItemsList() {
    var $btn = $('#health-corrupted-items-show-btn');

    if ($btn.prop('disabled') === true) {
        toastr.warning(TP_HEALTH_L10N.corrupted_no_result);
        return;
    }

    $.post(
        'sources/utilities.queries.php',
        {
            type: 'health_get_corrupted_items_list',
            data: prepareExchangedData(JSON.stringify({}), 'encode', tpHealthKey),
            key: tpHealthKey
        },
        function(data) {
            data = prepareExchangedData(data, 'decode', tpHealthKey);

            if (data && data.error) {
                toastr.error(data.message || TP_HEALTH_L10N.corrupted_scan_failed);
                return;
            }

            var $tbody = $('#health-corrupted-items-list');
            $tbody.empty();

            var meta = data && data.meta ? data.meta : {};
            var list = data && data.items ? data.items : [];

            if (meta && meta.truncated === true && Number(meta.limit || 0) > 0) {
                $('#health-corrupted-items-modal-note').text(
                    TP_HEALTH_L10N.corrupted_truncated_note_fmt.replace('%d', Number(meta.limit || 0))
                );
            } else {
                $('#health-corrupted-items-modal-note').text('');
            }

            if (!list.length) {
                $tbody.append('<tr><td colspan="6" class="text-muted">' + tpEscapeHtml(TP_HEALTH_L10N.no_data) + '</td></tr>');
            } else {
                list.forEach(function(it) {
                    var reasonLabel = tpCorruptedReasonToLabel(it.reason, it.exception_message);
                    $tbody.append(
                        '<tr>' +
                        '<td>' + tpEscapeHtml(it.id || 0) + '</td>' +
                        '<td>' + tpEscapeHtml(it.label || '') + '</td>' +
                        '<td>' + tpEscapeHtml(reasonLabel) + '</td>' +
                        '<td>' + tpEscapeHtml(it.len_stored || 0) + '</td>' +
                        '<td>' + tpEscapeHtml(it.len_actual || 0) + '</td>' +
                        '<td>' + tpEscapeHtml(it.updated_at_human || '') + '</td>' +
                        '</tr>'
                    );
                });
            }

            $('#health-corrupted-items-modal').modal('show');
        }
    ).fail(function() {
        toastr.error(TP_HEALTH_L10N.corrupted_scan_failed);
    });
}

function tpRenderRuntimeLogsContext(context) {
    context = context || {};

    var modeLabel = String(context.mode || 'auto') === 'manual'
        ? TP_HEALTH_L10N.logs_mode_manual
        : TP_HEALTH_L10N.logs_mode_auto;
    var serverSoftware = String(context.server_software || '');
    var serverFamily = String(context.server_family || '');

    var parts = [];
    parts.push(TP_HEALTH_L10N.runtime_logs_context_mode_fmt.replace('%s', modeLabel));

    if (serverSoftware) {
        parts.push(TP_HEALTH_L10N.runtime_logs_context_server_fmt.replace('%s', serverSoftware));
    } else if (serverFamily) {
        parts.push(TP_HEALTH_L10N.runtime_logs_context_server_fmt.replace('%s', serverFamily));
    }

    $('#health-runtime-logs-context').text(parts.join(' • '));
}

function tpResetRuntimeLogPane(prefix) {
    $('#' + prefix + '-error').hide().text('');
    $('#' + prefix + '-fix').hide();
    $('#' + prefix + '-fix-text').text('');
    $('#' + prefix + '-fix-cmd').hide().text('');
    $('#' + prefix + '-content').hide().text('');
    $('#' + prefix + '-copy-btn').prop('disabled', true);
}

function tpRuntimeLogMessage(result) {
    if (!result) {
        return TP_HEALTH_L10N.runtime_log_failed;
    }

    if (result.access === 'not_used') {
        return TP_HEALTH_L10N.runtime_log_not_used;
    }
    if (result.access === 'empty') {
        return TP_HEALTH_L10N.runtime_log_empty_fmt.replace('%s', tpEscapeHtml(result.log_path || ''));
    }
    if (result.access === 'not_configured') {
        return TP_HEALTH_L10N.runtime_log_not_configured;
    }
    if (result.access === 'invalid_path') {
        return TP_HEALTH_L10N.runtime_log_invalid_path;
    }
    if (result.access === 'not_found') {
        if (!result.log_path) {
            return TP_HEALTH_L10N.runtime_log_not_detected;
        }
        return TP_HEALTH_L10N.runtime_log_not_found_fmt.replace('%s', tpEscapeHtml(result.log_path || ''));
    }
    if (result.access === 'not_readable') {
        return TP_HEALTH_L10N.runtime_log_not_readable_fmt.replace('%s', tpEscapeHtml(result.log_path || ''));
    }

    return TP_HEALTH_L10N.runtime_log_failed;
}

function tpApplyRuntimeLogResult(prefix, result) {
    tpResetRuntimeLogPane(prefix);

    if (!result) {
        $('#' + prefix + '-error').show().text(TP_HEALTH_L10N.runtime_log_failed);
        return;
    }

    if (result.access === 'ok') {
        var content = String(result.content || '');
        $('#' + prefix + '-content').show().text(content);

        if (content.length > 0) {
            $('#' + prefix + '-copy-btn').prop('disabled', false);
        }
        return;
    }

    var message = tpRuntimeLogMessage(result);
    var commands = result.fix_commands || [];

    if ((result.access === 'not_found' && TP_HEALTH_L10N.runtime_log_fix_hint_missing) || (result.access === 'not_readable' && TP_HEALTH_L10N.runtime_log_fix_hint)) {
        message += ' ' + (result.access === 'not_found' ? TP_HEALTH_L10N.runtime_log_fix_hint_missing : TP_HEALTH_L10N.runtime_log_fix_hint);
    }

    if (commands.length > 0) {
        $('#' + prefix + '-fix-text').text(message);
        $('#' + prefix + '-fix-cmd').show().text(commands.join('\n'));
        $('#' + prefix + '-fix').show();
        return;
    }

    if (result.access === 'empty') {
        $('#' + prefix + '-fix-text').text(message);
        $('#' + prefix + '-fix-cmd').hide().text('');
        $('#' + prefix + '-fix').show();
        return;
    }

    if (result.role === 'php_fpm' && (result.access === 'not_used' || result.access === 'not_configured' || result.access === 'not_found')) {
        $('#' + prefix + '-fix-text').text(message);
        $('#' + prefix + '-fix-cmd').hide().text('');
        $('#' + prefix + '-fix').show();
        return;
    }

    $('#' + prefix + '-error').show().text(message);
}

function tpCheckRuntimeLogs() {
    var $btn = $('#health-runtime-logs-check-btn');
    var $icon = $btn.find('i');
    var lines = Number($('#health-runtime-log-lines').val() || 50);

    tpResetRuntimeLogPane('health-server-log');
    tpResetRuntimeLogPane('health-teampass-log');
    tpResetRuntimeLogPane('health-php-fpm-log');

    $btn.prop('disabled', true);
    $icon.removeClass('fa-search').addClass('fa-spinner fa-spin');

    $('#health-server-log-content').show().text(TP_HEALTH_L10N.runtime_log_checking);
    $('#health-teampass-log-content').show().text(TP_HEALTH_L10N.runtime_log_checking);
    $('#health-php-fpm-log-content').show().text(TP_HEALTH_L10N.runtime_log_checking);

    $.post(
        'sources/utilities.queries.php',
        {
            type: 'health_check_runtime_logs',
            data: prepareExchangedData(JSON.stringify({lines: lines}), 'encode', tpHealthKey),
            key: tpHealthKey
        },
        function(data) {
            data = prepareExchangedData(data, 'decode', tpHealthKey);

            $btn.prop('disabled', false);
            $icon.removeClass('fa-spinner fa-spin').addClass('fa-search');

            if (data && data.error) {
                tpResetRuntimeLogPane('health-server-log');
                tpResetRuntimeLogPane('health-teampass-log');
                tpResetRuntimeLogPane('health-php-fpm-log');
                toastr.error(data.message || TP_HEALTH_L10N.runtime_log_failed);
                return;
            }

            if (!data || !data.result) {
                tpResetRuntimeLogPane('health-server-log');
                tpResetRuntimeLogPane('health-teampass-log');
                tpResetRuntimeLogPane('health-php-fpm-log');
                toastr.error(TP_HEALTH_L10N.runtime_log_failed);
                return;
            }

            var result = data.result || {};
            tpRenderRuntimeLogsContext(result.context || {});
            tpApplyRuntimeLogResult('health-server-log', result.server || null);
            tpApplyRuntimeLogResult('health-teampass-log', result.teampass || null);
            tpApplyRuntimeLogResult('health-php-fpm-log', result.php_fpm || null);
        }
    ).fail(function() {
        $btn.prop('disabled', false);
        $icon.removeClass('fa-spinner fa-spin').addClass('fa-search');
        tpResetRuntimeLogPane('health-server-log');
        tpResetRuntimeLogPane('health-teampass-log');
        tpResetRuntimeLogPane('health-php-fpm-log');
        toastr.error(TP_HEALTH_L10N.runtime_log_failed);
    });
}

function tpCopyRuntimeLogToClipboard(role) {
    var prefix = 'health-server-log';
    if (role === 'teampass') {
        prefix = 'health-teampass-log';
    } else if (role === 'php-fpm') {
        prefix = 'health-php-fpm-log';
    }
    var text = $('#' + prefix + '-content').text() || '';
    if (!text) {
        return;
    }

    tpCopyToClipboard(
        text,
        function() { toastr.success(TP_HEALTH_L10N.copied); },
        function() { toastr.error(TP_HEALTH_L10N.clipboard_error || TP_HEALTH_L10N.generic_error); }
    );
}

function tpCopyToClipboard(text, onSuccess, onError) {
    if (!text) {
        if (typeof onError === 'function') onError();
        return;
    }

    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
        navigator.clipboard.writeText(text).then(
            function() { if (typeof onSuccess === 'function') onSuccess(); },
            function() { tpFallbackCopyToClipboard(text, onSuccess, onError); }
        );
        return;
    }

    tpFallbackCopyToClipboard(text, onSuccess, onError);
}

function tpFallbackCopyToClipboard(text, onSuccess, onError) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'absolute';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);

    ta.select();
    ta.setSelectionRange(0, ta.value.length);

    var ok = false;
    try {
        ok = document.execCommand('copy');
    } catch (e) {
        ok = false;
    }

    document.body.removeChild(ta);

    if (ok && typeof onSuccess === 'function') {
        onSuccess();
    } else if (!ok && typeof onError === 'function') {
        onError();
    }
}

function tpDownloadReportJson() {
    if (!tpHealthReportCache) {
        toastr.error(TP_HEALTH_L10N.no_data);
        return;
    }

    var $btn = $('#health-export-btn');
    $btn.prop('disabled', true);

    var reportToExport = JSON.parse(JSON.stringify(tpHealthReportCache));
    var filename = (reportToExport.export_filename || TP_HEALTH_L10N.export_filename_default);

    function tpTriggerDownload(reportObj) {
        var json = JSON.stringify(reportObj, null, 2);
        var blob = new Blob([json], { type: 'application/json' });
        var url = URL.createObjectURL(blob);

        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();

        setTimeout(function() {
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }, 0);
    }

    var lines = Number($('#health-runtime-log-lines').val() || 50);

    $.post(
        'sources/utilities.queries.php',
        {
            type: 'health_check_runtime_logs',
            data: prepareExchangedData(JSON.stringify({lines: lines}), 'encode', tpHealthKey),
            key: tpHealthKey
        },
        function(data) {
            data = prepareExchangedData(data, 'decode', tpHealthKey);

            if (!reportToExport.logs) {
                reportToExport.logs = {};
            }

            if (data && data.result) {
                reportToExport.logs.runtime_context = data.result.context || {};
                reportToExport.logs.server_error_log = data.result.server || {};
                reportToExport.logs.teampass_error_log = data.result.teampass || {};
                reportToExport.logs.php_fpm_error_log = data.result.php_fpm || {};
            } else if (data && data.error) {
                reportToExport.logs.runtime_logs_error = {
                    message: (data.message || ''),
                    lines: lines
                };
            }

            tpTriggerDownload(reportToExport);
        }
    ).fail(function() {
        tpTriggerDownload(reportToExport);
    }).always(function() {
        $btn.prop('disabled', false);
    });
}
</script>
