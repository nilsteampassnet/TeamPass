<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 *
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file RecoveryPackageLogicTest.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/backup.functions.php';

final class RecoveryPackageLogicTest extends TestCase
{
    /** Make a synthetic snapshot without installation files or a database. */
    private function snapshot(array $constants = [], array $settings = [], string $master = 'fixture-master', string $backup = 'fixture-backup', string $generation = '', int $format = 1): array
    {
        return tpRecoveryPackageFingerprint($master, $backup, $constants + [
            'DB_PREFIX' => 'fixture_', 'SECUREFILE' => 'fixture.key', 'DB_HOST' => 'localhost',
            'DB_PASSWD' => 'fixture-encrypted-password', 'IKEY' => 'fixture-legacy-key',
        ], $settings + ['cpassman_url' => 'https://fixture.example'], $generation, $format);
    }

    /** Build the instance receipt for a successfully sent snapshot. */
    private function receipt(array $snapshot): array
    {
        return ['snapshot' => $snapshot, 'downloaded_at' => 1700000000, 'downloaded_by' => 7];
    }

    public function testMissingAndMalformedHistoryNeverClaimsAKitWasDownloaded(): void
    {
        $snapshot = $this->snapshot();
        foreach ([[], ['snapshot' => $snapshot], $this->receipt([]),
            array_replace($this->receipt($snapshot), ['downloaded_at' => 'yesterday']),
            array_replace($this->receipt($snapshot), ['downloaded_by' => 0])] as $receipt) {
            self::assertSame('missing', tpRecoveryPackageStatus($snapshot, $receipt));
        }
        self::assertSame('current', tpRecoveryPackageStatus($snapshot, $this->receipt($snapshot)));
    }

    public function testMissingKeysOrInvalidEncodingCannotClearTheAlert(): void
    {
        self::assertSame([], $this->snapshot(master: ''));
        self::assertSame([], $this->snapshot(backup: ''));
        self::assertSame([], $this->snapshot(['DB_HOST' => "\xff"]));
        self::assertSame('unavailable', tpRecoveryPackageStatus([], $this->receipt($this->snapshot())));
    }

    /** @return iterable<string,array{array,array,string,string,string,int,string}> */
    public static function changes(): iterable
    {
        yield 'backup key rotation' => [[], [], 'fixture-master', 'rotated-backup', '', 1, 'material_changed'];
        yield 'master key replacement' => [[], [], 'rotated-master', 'fixture-backup', '', 1, 'material_changed'];
        foreach (['DB_PREFIX', 'SECUREFILE', 'IKEY', 'SKEY', 'HOST'] as $name) {
            yield $name => [[$name => 'changed'], [], 'fixture-master', 'fixture-backup', '', 1, 'material_changed'];
        }
        yield 'kit format' => [[], [], 'fixture-master', 'fixture-backup', '', 2, 'material_changed'];
        foreach (['DB_HOST', 'DB_USER', 'DB_NAME', 'DB_PASSWD', 'TEAMPASS_ROOT', 'TEAMPASS_SECRETS'] as $name) {
            yield $name => [[$name => 'changed'], [], 'fixture-master', 'fixture-backup', '', 1, 'configuration_changed'];
        }
        foreach (['cpassman_url', 'cpassman_dir', 'path_to_files_folder', 'path_to_upload_folder'] as $name) {
            yield $name => [[], [$name => 'changed'], 'fixture-master', 'fixture-backup', '', 1, 'configuration_changed'];
        }
        yield 'restored deployment generation' => [[], [], 'fixture-master', 'fixture-backup', 'restored', 1, 'configuration_changed'];
        yield 'ordinary upgrade' => [['TP_VERSION' => '3.2.4'], ['teampass_version' => '3.2.4', 'teampass_version_full' => '3.2.4.1'], 'fixture-master', 'fixture-backup', '', 1, 'current'];
        yield 'schedule and unrelated security settings' => [[], ['bck_scheduled_enabled' => '1', 'timezone' => 'Europe/Paris', 'mfa_enabled' => '1'], 'fixture-master', 'fixture-backup', '', 1, 'current'];
    }

    #[DataProvider('changes')]
    public function testOnlyRelevantChangesRequireANewKit(array $constants, array $settings, string $master, string $backup, string $generation, int $format, string $state): void
    {
        self::assertSame($state, tpRecoveryPackageStatus(
            $this->snapshot($constants, $settings, $master, $backup, $generation, $format),
            $this->receipt($this->snapshot())
        ));
    }

    public function testCanonicalizationAndReceiptsDoNotExposeSecrets(): void
    {
        $constants = ['DB_CONNECT_OPTIONS' => ['b' => 2, 'a' => 1], 'DB_USER' => 'fixture-user'];
        $reordered = ['DB_USER' => 'fixture-user', 'DB_CONNECT_OPTIONS' => ['a' => 1, 'b' => 2]];
        self::assertSame($this->snapshot($constants), $this->snapshot($reordered));
        $json = json_encode($this->receipt($this->snapshot($constants)), JSON_THROW_ON_ERROR);
        foreach (['fixture-master', 'fixture-backup', 'fixture-user', 'fixture-encrypted-password', 'fixture-legacy-key'] as $secret) {
            self::assertStringNotContainsString($secret, $json);
        }
    }

    /** @return iterable<string,array{int|false,int,int,bool}> */
    public static function transfers(): iterable
    {
        yield 'complete' => [100, 100, CONNECTION_NORMAL, true];
        yield 'failed' => [false, 100, CONNECTION_NORMAL, false];
        yield 'partial' => [50, 100, CONNECTION_NORMAL, false];
        yield 'empty' => [0, 0, CONNECTION_NORMAL, false];
        yield 'aborted' => [100, 100, CONNECTION_ABORTED, false];
        yield 'timeout' => [100, 100, CONNECTION_TIMEOUT, false];
    }

    #[DataProvider('transfers')]
    public function testOnlyACompleteTransferAcknowledgesTheKit(int|false $sent, int $expected, int $connection, bool $complete): void
    {
        self::assertSame($complete, tpRecoveryPackageTransferComplete($sent, $expected, $connection));
    }
}
