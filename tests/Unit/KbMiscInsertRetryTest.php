<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License v3.0.
 * See <https://www.gnu.org/licenses/>.
 */

namespace TeamPass\Tests\KbMiscInsertRetry;

use PHPUnit\Framework\TestCase;

/** Inject only storage failures; execute the production id generation and retry loop. */
final class DB
{
    public static array $errors = [];
    public static array $attempts = [];

    /** Record each insertion and replay its configured database error. */
    public static function insert(string $table, array $values): void
    {
        self::$attempts[] = [$table, $values];
        $error = array_shift(self::$errors);
        if ($error !== null) {
            throw $error;
        }
    }
}

/** Use a non-default prefix without reading installation settings. */
function prefixTable(string $table): string { return 'fixture_' . $table; }

/** The actual miscellaneous-row unique index must activate bounded collision retries. */
final class KbMiscInsertRetryTest extends TestCase
{
    /** Load the production functions without running the AJAX controller. */
    public static function setUpBeforeClass(): void
    {
        $source = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../app/sources/kb.queries.php'));
        foreach (['kbGenerateToken', 'kbBuildUniqueMiscIntitule', 'kbMiscEncode', 'kbInsertMiscRow'] as $function) {
            $start = strpos($source, 'function ' . $function . '(');
            self::assertNotFalse($start);
            $end = strpos($source, "\n}", $start) + 2;
            eval('namespace ' . __NAMESPACE__ . '; use Throwable; ' . substr($source, $start, $end - $start));
        }
    }

    protected function setUp(): void
    {
        DB::$attempts = [];
        DB::$errors = [];
    }

    /** A temporary collision retries with new identifiers and preserves the payload. */
    public function testActualUniqueIndexCollisionRetries(): void
    {
        DB::$errors = [new \RuntimeException("Duplicate entry for key 'uk_type_intitule'"),
            new \RuntimeException("Duplicate entry for key 'misc.uk_type_intitule'")];
        kbInsertMiscRow('kb_log', 'kb_log_at_creation', ['kb_id' => 42], 100, 42);
        self::assertCount(3, DB::$attempts);
        self::assertCount(3, array_unique(array_column(array_column(DB::$attempts, 1), 'intitule')));
        foreach (DB::$attempts as [$table, $values]) {
            self::assertSame('fixture_misc', $table);
            self::assertSame(['kb_id' => 42], json_decode($values['valeur'], true));
            self::assertSame(100, $values['created_at']);
        }
    }

    /** Other database errors propagate immediately rather than being retried or hidden. */
    public function testUnrelatedIndexErrorIsNotRetried(): void
    {
        $error = new \RuntimeException("Duplicate entry for key 'another_index'");
        DB::$errors = [$error];
        $this->expectExceptionObject($error);
        try {
            kbInsertMiscRow('kb_log', 'kb_log_at_creation', [], 100, 42);
        } finally {
            self::assertCount(1, DB::$attempts);
        }
    }

    /** A persistent collision still stops at the existing five-attempt limit. */
    public function testPersistentCollisionIsBounded(): void
    {
        $error = new \RuntimeException("Duplicate entry for key 'uk_type_intitule'");
        DB::$errors = array_fill(0, 6, $error);
        $this->expectExceptionObject($error);
        try {
            kbInsertMiscRow('kb_log', 'kb_log_at_creation', [], 100, 42);
        } finally {
            self::assertCount(5, DB::$attempts);
        }
    }
}
