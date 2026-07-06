<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static regression guards for API writes keeping the item cache synchronized.
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

    public function testApiItemWritesSynchronizeCacheTable(): void
    {
        $source = $this->readSource('/app/api/Model/ItemModel.php');

        self::assertStringContainsString(
            "updateCacheTable('add_value', \$newID, \$userId);",
            $source,
            'API item creation must add the item to the cache table.'
        );

        self::assertStringContainsString(
            "updateCacheTable('update_value', \$itemId, (int) \$userData['id']);",
            $source,
            'API item updates must refresh the cache table.'
        );

        self::assertStringContainsString(
            "updateCacheTable('delete_value', \$itemId);",
            $source,
            'API item deletion must remove the item from the cache table.'
        );
    }

    public function testCacheUpdateCanRepairMissingCacheRows(): void
    {
        $source = $this->readSource('/app/sources/main.functions.php');

        self::assertStringContainsString(
            'function updateCacheTable(string $action, ?int $ident = null, ?int $authorId = null): void',
            $source,
            'Cache synchronization must accept an explicit author id for API contexts.'
        );

        self::assertStringContainsString(
            'function cacheTableUpdate(?int $ident = null, ?int $authorId = null): void',
            $source,
            'Cache updates must accept an explicit author id.'
        );

        self::assertStringContainsString(
            'cacheTableAdd($ident, $authorId);',
            $source,
            'Cache update must create a missing cache row instead of silently doing nothing.'
        );
    }
}
