<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: API writes keep the item cache table synchronized.
 *
 * Static regression guards, consistent with SecurityHardeningTest and
 * ApiHardeningPhase4Test. Matching is whitespace-insensitive and does not
 * depend on exact local variable names, so routine reformatting will not break
 * these tests — only removing the guarded behaviour will.
 *
 * Locked invariants:
 * - API item create/update/delete each synchronize the cache table;
 * - create/update pass an explicit author id (no web session in API context);
 * - the cache helpers accept an optional $authorId argument;
 * - cacheTableUpdate() self-heals a missing cache row via cacheTableAdd();
 * - the cache row insert is race-safe: a UNIQUE(id) constraint on the cache table
 *   combined with INSERT IGNORE prevents duplicate rows from concurrent self-heals.
 */
class ApiItemCacheSyncTest extends TestCase
{
    private function readSource(string $relativePath): string
    {
        $path = __DIR__ . '/../../..' . $relativePath;
        self::assertFileExists($path, "Source file '$relativePath' not found");
        $content = file_get_contents($path);
        self::assertIsString($content);
        return $content;
    }

    /**
     * Extract a single top-level function body from PHP source by its name.
     *
     * @param string $source   Full PHP source.
     * @param string $function Function name (without parentheses).
     *
     * @return string The function definition up to the next top-level function.
     */
    private function functionBody(string $source, string $function): string
    {
        $start = strpos($source, 'function ' . $function . '(');
        self::assertNotFalse($start, "Function '$function' not found in source");
        $next = strpos($source, "\nfunction ", $start + 1);
        return $next === false ? substr($source, $start) : substr($source, $start, $next - $start);
    }

    public function testApiItemWritesSynchronizeCacheTable(): void
    {
        $source = $this->readSource('/app/api/Model/ItemModel.php');

        // create: add_value with an ident and an explicit author id
        self::assertMatchesRegularExpression(
            '/updateCacheTable\(\s*\'add_value\'\s*,\s*\$\w+\s*,\s*\$\w+\s*\)/',
            $source,
            'API item creation must add the item to the cache with an explicit author id.'
        );

        // update: update_value with an ident and an explicit author id
        self::assertMatchesRegularExpression(
            '/updateCacheTable\(\s*\'update_value\'\s*,\s*\$\w+\s*,\s*.+?\)/',
            $source,
            'API item update must refresh the cache with an explicit author id.'
        );

        // delete: delete_value with an ident
        self::assertMatchesRegularExpression(
            '/updateCacheTable\(\s*\'delete_value\'\s*,\s*\$\w+\s*\)/',
            $source,
            'API item deletion must remove the item from the cache.'
        );
    }

    public function testCacheHelpersAcceptExplicitAuthorId(): void
    {
        $source = $this->readSource('/app/sources/main.functions.php');

        self::assertMatchesRegularExpression(
            '/function updateCacheTable\(\s*string\s+\$action\s*,\s*\?int\s+\$ident\s*=\s*null\s*,\s*\?int\s+\$authorId\s*=\s*null\s*\)/',
            $source,
            'updateCacheTable() must accept an explicit author id for API contexts.'
        );

        self::assertMatchesRegularExpression(
            '/function cacheTableUpdate\(\s*\?int\s+\$ident\s*=\s*null\s*,\s*\?int\s+\$authorId\s*=\s*null\s*\)/',
            $source,
            'cacheTableUpdate() must accept an explicit author id.'
        );

        self::assertMatchesRegularExpression(
            '/function cacheTableAdd\(\s*\?int\s+\$ident\s*=\s*null\s*,\s*\?int\s+\$authorId\s*=\s*null\s*\)/',
            $source,
            'cacheTableAdd() must accept an explicit author id.'
        );
    }

    public function testCacheUpdateSelfHealsMissingRow(): void
    {
        $source = $this->readSource('/app/sources/main.functions.php');
        $body = $this->functionBody($source, 'cacheTableUpdate');

        // The repair is gated on the update affecting no row...
        self::assertStringContainsString(
            'DB::affectedRows()',
            $body,
            'cacheTableUpdate() must detect a missing cache row via DB::affectedRows().'
        );

        // ...and recreates it through cacheTableAdd() instead of silently doing nothing.
        self::assertMatchesRegularExpression(
            '/cacheTableAdd\(\s*\$\w+\s*,\s*\$\w+\s*\)/',
            $body,
            'cacheTableUpdate() must recreate a missing cache row via cacheTableAdd().'
        );
    }

    public function testCacheInsertIsRaceSafe(): void
    {
        // The self-heal insert must use INSERT IGNORE so a concurrent insert cannot
        // create a duplicate cache row.
        $source = $this->readSource('/app/sources/main.functions.php');
        $body = $this->functionBody($source, 'cacheTableAdd');
        self::assertMatchesRegularExpression(
            '/DB::insertIgnore\(\s*prefixTable\(\'cache\'\)/',
            $body,
            'cacheTableAdd() must insert the cache row with DB::insertIgnore().'
        );

        // The UNIQUE(id) constraint that makes INSERT IGNORE race-safe must be part
        // of the cache table definition created by the installer.
        $install = $this->readSource('/public/install/install-steps/run.step5.php');
        self::assertMatchesRegularExpression(
            '/UNIQUE\s+KEY\s+`idx_cache_id_unique`\s*\(\s*`id`\s*\)/i',
            $install,
            'The cache table must declare a UNIQUE constraint on the id column.'
        );
    }
}
