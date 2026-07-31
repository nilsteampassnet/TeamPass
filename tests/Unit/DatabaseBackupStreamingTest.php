<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * @file      DatabaseBackupStreamingTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\TestCase;

class DatabaseBackupStreamingTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/sources/backup.functions.php');
        $this->assertNotFalse($source);
        $this->source = (string) $source;
    }

    public function testDatabaseDumpUsesAnUnbufferedRowWalker(): void
    {
        $this->assertStringContainsString(
            "DB::queryWalk('SELECT * FROM `'",
            $this->source
        );
        $this->assertStringContainsString('MYSQLI_USE_RESULT', $this->source);
        $this->assertStringNotContainsString(
            'LIMIT %i OFFSET %i',
            $this->source
        );
        $this->assertStringContainsString('$rows->free();', $this->source);
    }

    public function testDatabaseDumpIsPublishedAtomically(): void
    {
        $this->assertStringContainsString("\$plainTempPath = \$filepath . '.part';", $this->source);
        $this->assertStringContainsString(
            "\$encryptedTempPath = \$filepath . '.encrypted.part';",
            $this->source
        );
        $this->assertStringContainsString(
            'rename($plainTempPath, $filepath)',
            $this->source
        );
        $this->assertStringContainsString(
            'rename($encryptedTempPath, $filepath)',
            $this->source
        );
        $this->assertStringContainsString(
            "@fopen(\$plainTempPath, 'x+b')",
            $this->source
        );
        $this->assertStringNotContainsString("fopen(\$filepath, 'w+'", $this->source);
    }

    public function testDumpWritesAndFlushesAreChecked(): void
    {
        $this->assertStringContainsString(
            'Could not write the database backup to disk.',
            $this->source
        );
        $this->assertStringContainsString(
            'Could not flush the database backup to disk.',
            $this->source
        );
    }
}
