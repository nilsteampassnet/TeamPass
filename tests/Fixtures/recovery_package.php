<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 *
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery_package.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

require_once __DIR__ . '/../../app/vendor/autoload.php';
require_once __DIR__ . '/../../app/sources/recovery_package.functions.php';

/** Isolate the adapter and real archive builder from production database/configuration. */
final class DB
{
    public static array $settings = [];
    public static string $receipt = '';
    public static string $cached = '';
    public static array $audit = [];
    public static int $depth = 0;
    public static int $writes = 0;
    public static bool $failAudit = false;
    public static bool $failState = false;
    private static bool $ownsTransaction = false;
    private static array $before = [];

    /** Return fresh deployment inputs, or initialize the singleton receipt. */
    public static function query(string $sql, ...$arguments): array
    {
        if (str_starts_with($sql, 'INSERT IGNORE')) {
            ++self::$writes;
            return [];
        }
        if (self::$ownsTransaction && !str_ends_with($sql, 'FOR UPDATE')) {
            throw new RuntimeException('Recording must lock the current source settings.');
        }
        return array_map(static fn ($name, $value) => ['intitule' => $name, 'valeur' => $value],
            array_keys(self::$settings), array_values(self::$settings));
    }

    /** Read the singleton receipt. */
    public static function queryFirstField(string $sql, ...$arguments): string
    {
        return in_array('recovery_package_status', $arguments, true) ? self::$cached : self::$receipt;
    }

    /** No historical key candidates are installed in this fixture. */
    public static function queryFirstRow(string $sql, ...$arguments): array
    {
        return in_array('bck_script_passkey', $arguments, true) ? ['increment_id' => 1] : [];
    }

    /** Persist the non-sensitive receipt. */
    public static function update(string $table, array $values, ...$arguments): void
    {
        ++self::$writes;
        if (($arguments[0] ?? '') === 'increment_id=%i') {
            self::$settings['bck_script_passkey'] = $values['valeur'];
        } else {
            self::$receipt = $values['valeur'];
        }
    }

    /** Persist the derived status independently from the download receipt. */
    public static function insertUpdate(string $table, array $values, array $updates): void
    {
        if (self::$failState) {
            throw new RuntimeException('Synthetic state failure');
        }
        if ($values['intitule'] !== 'recovery_package_status') {
            throw new RuntimeException('Unexpected derived setting.');
        }
        ++self::$writes;
        self::$cached = $updates['valeur'];
    }

    /** Persist the audit, or exercise an atomic rollback. */
    public static function insert(string $table, array $values): void
    {
        if ($table === 'fixture_misc') {
            return;
        }
        if (self::$failAudit) {
            throw new RuntimeException('Synthetic audit failure');
        }
        self::$audit[] = $values;
    }

    /** Preserve a caller-owned transaction. */
    public static function transactionDepth(): int { return self::$depth; }

    /** Begin the isolated receipt transaction. */
    public static function startTransaction(): void
    {
        self::$before = [self::$receipt, self::$audit, self::$cached];
        self::$ownsTransaction = true;
        ++self::$depth;
    }

    /** Complete the receipt transaction. */
    public static function commit(): void { --self::$depth; self::$ownsTransaction = false; }

    /** Restore the receipt and audit together. */
    public static function rollback(): void
    {
        [self::$receipt, self::$audit, self::$cached] = self::$before;
        --self::$depth;
        self::$ownsTransaction = false;
    }
}

/** Keep the fixture independent from installation table names. */
function prefixTable(string $name): string { return 'fixture_' . $name; }

/** Simulate randomized storage encryption while resolving the same clear key. */
function cryption(string $value, string $key, string $action, array $settings = []): array
{
    if (!empty($GLOBALS['fixtureThrowDecrypt']) && $action === 'decrypt') {
        throw new RuntimeException('Synthetic key read failure.');
    }
    return ['string' => $action === 'encrypt'
        ? (!empty($GLOBALS['fixtureReencrypt']) ? 'resealed:' : 'sealed:') . $value
        : (str_starts_with($value, 'resealed:') ? substr($value, 9)
            : (str_starts_with($value, 'sealed:') ? substr($value, 7) : ''))];
}

/** Generate temporary names without the application bootstrap. */
function GenerateCryptKey(...$arguments): string { return substr(bin2hex(random_bytes(40)), 0, (int) ($arguments[0] ?? 20)); }

/** Preserve the controller's exchange shape without loading a real encrypted session. */
function prepareExchangedData(array $data, string $action): string { return json_encode($data, JSON_THROW_ON_ERROR); }

/** Encrypt the real ZIP and optionally replace the source after it was captured. */
function prepareFileWithDefuse(string $mode, string $source, string $destination, string $password): bool
{
    global $scenario;
    \Defuse\Crypto\File::encryptFileWithPassword($source, $destination, $password);
    if ($scenario === 'source_changed') {
        file_put_contents(TEAMPASS_SECRETS . '/' . SECUREFILE, 'replaced-after-capture');
    }
    return true;
}

$directory = $argv[1];
$scenario = $argv[2];
define('TEAMPASS_APP', $directory);
define('TEAMPASS_SECRETS', $directory);
define('SECUREFILE', 'fixture.key');
define('DB_PREFIX', 'fixture_');
define('TP_VERSION', '3.2.3');
define('TP_VERSION_MINOR', '0');
define('UPGRADE_MIN_DATE', 1780000000);
mkdir($directory . '/config', 0700);
file_put_contents($directory . '/config/settings.php', '<?php // synthetic configuration, never executed');
file_put_contents($directory . '/' . SECUREFILE, 'fixture-master');
DB::$settings = ['bck_script_passkey' => 'sealed:' . str_repeat('a', 40),
    'cpassman_url' => 'https://fixture.example', 'recovery_generation' => ''];
$snapshot = tpRecoveryPackageCurrentSnapshot();
$result = [];

if (str_starts_with($scenario, 'info_')) {
    date_default_timezone_set('Europe/Paris');
    DB::$receipt = json_encode(['snapshot' => $snapshot, 'downloaded_at' => 1700000000, 'downloaded_by' => 7]);
    if ($scenario === 'info_missing') {
        DB::$receipt = '';
    } elseif ($scenario === 'info_malformed') {
        DB::$receipt = '{not json';
    } elseif ($scenario === 'info_changed') {
        DB::$settings['bck_script_passkey'] = 'sealed:' . str_repeat('b', 40);
    } elseif ($scenario === 'info_unavailable') {
        unlink($directory . '/' . SECUREFILE);
    } elseif ($scenario === 'info_empty_key' || $scenario === 'info_first_visit') {
        DB::$settings['bck_script_passkey'] = '';
        DB::$receipt = '';
    } elseif ($scenario === 'info_invalid_key') {
        DB::$settings['bck_script_passkey'] = 'invalid';
    } elseif ($scenario === 'info_decrypt_error') {
        tpRecoveryPackageSaveState('current', DB::$settings);
        $GLOBALS['fixtureThrowDecrypt'] = true;
    }
    $lang = new \TeampassClasses\Language\Language('french');
    $SETTINGS = DB::$settings + ['date_format' => 'd/m/Y', 'time_format' => 'H:i'];
    if (in_array($scenario, ['info_bad_key', 'info_non_admin', 'info_endpoint'], true)) {
        $session = new class($scenario) {
            /** Retain only the status endpoint's authentication fields. */
            public function __construct(private string $scenario) {}
            /** Return the synthetic key and administrator flag. */
            public function get(string $key): mixed { return $key === 'key' ? 'fixture-key' : ($this->scenario === 'info_non_admin' ? 0 : 1); }
        };
        $post_key = $scenario === 'info_bad_key' ? 'wrong' : 'fixture-key';
        $source = file_get_contents(__DIR__ . '/../../app/sources/backups.queries.php');
        $start = strpos($source, "case 'recovery_package_status':");
        $end = strpos($source, "case 'recovery_package_create':", $start);
        eval("switch ('recovery_package_status') {" . substr($source, $start, $end - $start) . '}');
        exit;
    }
    if ($scenario === 'info_first_visit') {
        $source = file_get_contents(__DIR__ . '/../../app/pages/backups.php');
        $start = strpos($source, '// Define Timezone');
        $end = strpos($source, '?>', $start);
        eval(str_replace('__DIR__', var_export(dirname(__DIR__, 2) . '/app/sources', true), substr($source, $start, $end - $start)));
        $info = $recoveryPackageInformation;
    } else {
        $info = tpRecoveryPackageGetDisplayInformation($SETTINGS, $lang);
    }
    $result = $info + ['writes' => DB::$writes, 'cached_state' => json_decode(DB::$cached, true),
        'receipt' => DB::$receipt, 'backup_key_generated' => DB::$settings['bck_script_passkey'] !== ''];
} elseif (str_starts_with($scenario, 'setting_')) {
    tpRecoveryPackageSaveState('current', DB::$settings);
    $receiptBefore = DB::$receipt;
    if ($scenario === 'setting_same_key' || $scenario === 'setting_rotated_key') {
        $GLOBALS['fixtureReencrypt'] = true;
        $result['stored'] = tpStoreBackupScriptPasskey(str_repeat($scenario === 'setting_same_key' ? 'a' : 'b', 40), DB::$settings, false)['success'];
    } else {
        DB::$settings['cpassman_url'] = 'https://new.example';
        $SETTINGS = DB::$settings;
        $post_field = 'cpassman_url';
        $post_value = DB::$settings['cpassman_url'];
        $source = file_get_contents(__DIR__ . '/../../app/sources/admin.queries.php');
        $start = strpos($source, '// Keep local settings array aligned');
        $end = strpos($source, "if (\$post_field === 'enable_local_password_recovery')", $start);
        eval(str_replace('__DIR__', var_export(dirname(__DIR__, 2) . '/app/sources', true), substr($source, $start, $end - $start)));
    }
    $result += ['state' => tpRecoveryPackageGetStoredStatus(DB::$settings),
        'receipt_unchanged' => $receiptBefore === DB::$receipt];
} elseif (str_starts_with($scenario, 'download_')) {
    $path = $directory . '/recovery-fixture.tprecovery';
    file_put_contents($path, $scenario === 'download_empty' ? '' : 'fixture encrypted package');
    $download = ['path' => $path, 'filename' => basename($path), 'token' => 'fixture-token',
        'expires_at' => time() + ($scenario === 'download_expired' ? -10 : 600), 'snapshot' => $snapshot];
    if ($scenario === 'download_missing_file') {
        unlink($path);
    } elseif ($scenario === 'download_old_snapshot') {
        DB::$settings['bck_script_passkey'] = 'sealed:' . str_repeat('b', 40);
    } elseif ($scenario === 'download_no_snapshot') {
        unset($download['snapshot']);
    }
    $session = new class($download, $scenario) {
        public array $values;

        /** Hold only the download's synthetic session fields. */
        public function __construct(array $download, string $scenario)
        {
            $this->values = ['key' => 'fixture-key', 'user-key_tmp' => 'fixture-temp',
                'user-admin' => $scenario === 'download_non_admin' ? 0 : 1, 'user-id' => 7,
                'recovery-package-download' => $download];
        }

        /** Read a synthetic session field. */
        public function get(string $name): mixed { return $this->values[$name] ?? null; }

        /** Remove a consumed or expired one-time reference. */
        public function remove(string $name): void { unset($this->values[$name]); }
    };
    $post_key = $scenario === 'download_bad_key' ? 'wrong' : 'fixture-key';
    $GLOBALS['fixtureGet'] = ['key_tmp' => $scenario === 'download_bad_temp' ? 'wrong' : 'fixture-temp',
        'token' => $scenario === 'download_bad_token' ? 'wrong' : 'fixture-token'];
    register_shutdown_function(static function () use ($directory, $path, $session): void {
        file_put_contents($directory . '/result.json', json_encode([
            'status' => http_response_code() ?: 200, 'receipt' => json_decode(DB::$receipt, true),
            'audit' => DB::$audit, 'file_exists' => is_file($path),
            'cached_state' => json_decode(DB::$cached, true),
            'session_exists' => $session->get('recovery-package-download') !== null,
        ], JSON_THROW_ON_ERROR));
    });
    // Exercise the actual controller case and its exits. Only input acquisition is
    // substituted because PHP's CLI SAPI does not populate filter_input(INPUT_GET).
    $source = file_get_contents(__DIR__ . '/../../app/sources/backups.queries.php');
    $start = strpos($source, "case 'recovery_package_download':");
    $end = strpos($source, '//CASE adding a new function', $start);
    if ($start === false || $end === false) {
        throw new RuntimeException('Cannot locate the recovery download handler.');
    }
    eval('namespace RecoveryPackageDownloadFixture; '
        . 'function filter_input($type, $name, $filter) { return $GLOBALS["fixtureGet"][$name] ?? null; } '
        . "switch ('recovery_package_download') {" . substr($source, $start, $end - $start) . '}');
} elseif (in_array($scenario, ['create', 'source_changed'], true)) {
    $settings = DB::$settings;
    $package = tpCreateRecoveryPackage($settings, 'fixture-package-passphrase', ['output_dir' => $directory]);
    if (!$package['success']) {
        throw new RuntimeException($package['message']);
    }
    \Defuse\Crypto\File::decryptFileWithPassword($package['filepath'], $directory . '/plain.zip', 'fixture-package-passphrase');
    $zip = new ZipArchive();
    $zip->open($directory . '/plain.zip');
    $result = ['packaged_master' => $zip->getFromName('secrets/' . SECUREFILE),
        'snapshot_matches_capture' => $snapshot === $package['snapshot'],
        'state' => tpRecoveryPackageGetStatus(), 'writes' => DB::$writes,
        'archive_has_receipt' => $zip->locateName('snapshot') !== false];
    $zip->close();
} else {
    if ($scenario === 'rotated') {
        DB::$settings['bck_script_passkey'] = 'sealed:' . str_repeat('b', 40);
    } elseif ($scenario === 'same_key_reencrypted') {
        DB::$settings['bck_script_passkey'] = str_repeat('a', 40);
    } elseif ($scenario === 'restored') {
        DB::$settings['recovery_generation'] = 'new-deployment';
    } elseif ($scenario === 'audit_failure') {
        DB::$failAudit = true;
    } elseif ($scenario === 'state_failure') {
        DB::$failState = true;
    } elseif ($scenario === 'caller_transaction') {
        DB::$depth = 1;
    } elseif ($scenario === 'bad_history') {
        DB::$receipt = '{not json';
    }
    $beforeWrites = DB::$writes;
    $beforeState = tpRecoveryPackageGetStatus();
    $readOnly = DB::$writes === $beforeWrites;
    $recorded = $scenario === 'bad_history' ? false : tpRecoveryPackageRecordDownload($snapshot, 7);
    if ($scenario === 'older_download') {
        $oldSnapshot = $snapshot;
        DB::$settings['bck_script_passkey'] = 'sealed:' . str_repeat('b', 40);
        tpRecoveryPackageRecordDownload(tpRecoveryPackageCurrentSnapshot(), 8);
        $beforeReceipt = DB::$receipt;
        $recorded = tpRecoveryPackageRecordDownload($oldSnapshot, 7);
        $result['preserved_newer_receipt'] = $beforeReceipt === DB::$receipt;
    }
    $result += ['recorded' => $recorded, 'state_before' => $beforeState,
        'state' => tpRecoveryPackageGetStatus(), 'read_only' => $readOnly,
        'receipt' => json_decode(DB::$receipt, true), 'audit' => DB::$audit, 'depth' => DB::$depth,
        'cached_state' => json_decode(DB::$cached, true)];
}
echo json_encode($result, JSON_THROW_ON_ERROR);
