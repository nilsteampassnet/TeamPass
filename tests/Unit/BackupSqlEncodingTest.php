<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/backup.functions.php';

final class BackupSqlEncodingTest extends TestCase
{
    public function testNewlinesAndLiteralEscapeSequencesRemainDistinct(): void
    {
        self::assertSame('first\\nsecond', tpBackupEscapeSqlString("first\nsecond"));
        self::assertSame('first\\\\nsecond', tpBackupEscapeSqlString('first\\nsecond'));
        self::assertSame('\\0\\r\\n\\Z\\"\\\'\\\\', tpBackupEscapeSqlString("\0\r\n\x1a\"'\\"));
        self::assertSame("R&D O'Brien \"<>\"", stripcslashes(tpBackupEscapeSqlString("R&D O'Brien \"<>\"")));
    }

}
