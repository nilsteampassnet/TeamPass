<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 *
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery_package_dashboard.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

require_once __DIR__ . '/../../app/vendor/autoload.php';
require_once __DIR__ . '/../../app/sources/admin_notices.functions.php';

/** Isolate the dashboard from installation configuration and recovery files. */
final class DB
{
    public static int $stateReads = 0;

    /** Read the derived state once; unrelated dashboard queries return no result. */
    public static function queryFirstField(string $sql, ...$arguments): string
    {
        if (in_array('recovery_package_status', $arguments, true)) {
            ++self::$stateReads;
            return json_encode(['version' => 1, 'state' => 'current',
                'inputs' => tpRecoveryPackageStateInputs($GLOBALS['fixtureSettings'])]);
        }
        return '1';
    }

    /** Supply completed migration statistics without a database. */
    public static function query(string $sql, ...$arguments): array
    {
        return [['total_users' => 1, 'migrated_users' => 1, 'pending_users' => 0]];
    }

    /** No migration is pending. */
    public static function count(): int { return 0; }

    /** No account needs password repair. */
    public static function queryFirstColumn(string $sql, ...$arguments): array { return []; }
}

/** Never address a real installation table. */
function prefixTable(string $name): string { return 'fixture_' . $name; }

/** Fail the probe if the dashboard attempts any decryption. */
function cryption(...$arguments): array { throw new RuntimeException('Dashboard must not decrypt.'); }

$fixtureSettings = ['bck_script_passkey' => 'synthetic-encrypted-key', 'recovery_generation' => 'generation'];
$settings = $fixtureSettings;
if (($argv[1] ?? '') === 'restored') {
    $settings['recovery_generation'] = 'restored-generation';
}
$lang = new \TeampassClasses\Language\Language('english');
$notices = adminNoticesCollectMaintenance($settings, $lang);
$recovery = array_values(array_filter($notices, static fn (array $notice): bool => $notice['id'] === 'recovery_package'));
echo json_encode(['recovery_notices' => count($recovery), 'state_reads' => DB::$stateReads,
    'backup_loaded' => function_exists('tpRecoveryPackageCurrentSnapshot') || function_exists('tpCreateRecoveryPackage'),
    'capture_loaded' => class_exists('TeamPassBackupCapture', false)], JSON_THROW_ON_ERROR);
