<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * @file      BackupCommentSaveTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

require_once __DIR__ . '/../../app/sources/backup.functions.php';

class BackupCommentSaveTest extends TestCase
{
    /** @return array<string,array{string,string}> */
    public static function scenarios(): array
    {
        return [
            'saved' => ['saved', 'bck_onthefly_comment_saved'],
            'refused metadata write' => ['refused', 'bck_onthefly_comment_save_failed'],
            'invalid session key' => ['bad_key', 'error_not_allowed_to'],
            'standard account' => ['non_admin', 'error_not_allowed_to'],
        ];
    }

    #[DataProvider('scenarios')]
    public function testResponseMatchesTheWriteOutcomeAndAccessChecks(string $scenario, string $message): void
    {
        $root = sys_get_temp_dir() . '/teampass-backup-comment-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($root, 0700));
        try {
            $process = new Process([PHP_BINARY, __DIR__ . '/../Fixtures/backup_comment_save.php', $root, $scenario]);
            $process->setTimeout(10);
            $process->run();
            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $response = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($scenario !== 'saved', $response['error']);
            self::assertSame($message, $response['message']);
            if ($scenario === 'saved') {
                self::assertSame('Updated comment', tpReadBackupMetadata($root . '/fixture.sql')['comment']);
            }
        } finally {
            $meta = tpGetBackupMetadataPath($root . '/fixture.sql');
            if (is_dir($meta)) {
                rmdir($meta);
            } elseif (is_file($meta)) {
                unlink($meta);
            }
            if (is_file($root . '/fixture.sql')) {
                unlink($root . '/fixture.sql');
            }
            rmdir($root);
        }
    }
}
