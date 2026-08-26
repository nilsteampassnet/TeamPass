<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Contract and schema guards for the durable item revision timestamp.
 */
class ItemRevisionTimestampTest extends TestCase
{
    private function readSource(string $relativePath): string
    {
        $path = __DIR__ . '/../../..' . $relativePath;
        self::assertFileExists($path, "Source file '$relativePath' not found");
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }

    public function testFreshInstallAndUpgradePersistTheTimestamp(): void
    {
        $install = $this->readSource('/public/install/install-steps/run.step5.php');
        $upgrade = $this->readSource('/public/install/upgrade_run_3.2.2.php');

        self::assertStringContainsString('`revision_changed_at` BIGINT UNSIGNED NULL DEFAULT NULL', $install);
        self::assertStringContainsString("'revision_changed_at'", $upgrade);
        self::assertStringContainsString('BIGINT UNSIGNED NULL DEFAULT NULL', $upgrade);
        self::assertStringContainsString('r.`revision` = i.`revision`', $upgrade);
        self::assertStringContainsString('i.`revision_changed_at` IS NULL', $upgrade);
        self::assertStringNotContainsString('SET i.`revision_changed_at` = i.`updated_at`', $upgrade);
    }

    public function testOneTimestampValueIsWrittenToTheJournalAndItem(): void
    {
        $source = $this->readSource('/app/sources/main.functions.php');
        $start = strpos($source, 'function bumpItemRevision(');
        $end = strpos($source, 'function pruneItemRevisionsJournal(', (int) $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $function = substr($source, (int) $start, (int) $end - (int) $start);

        self::assertSame(1, substr_count($function, '$changedAt = time();'));
        self::assertSame(2, substr_count($function, "'revision_changed_at' => \$changedAt") + substr_count($function, "'changed_at' => \$changedAt"));
        self::assertStringContainsString("'changed_at' => \$changedAt", $function);
        self::assertStringContainsString("'revision_changed_at' => \$changedAt", $function);
    }

    public function testOpenApiExposesTimestampEverywhereRevisionIsReturned(): void
    {
        $spec = json_decode($this->readSource('/app/api/openapi.json'), true);
        self::assertIsArray($spec);

        foreach (['Item', 'ItemSummary', 'CreatedResult'] as $schemaName) {
            self::assertArrayHasKey(
                'revision_changed_at',
                $spec['components']['schemas'][$schemaName]['properties'],
                "$schemaName must expose revision_changed_at"
            );
        }

        $updateProperties = $spec['paths']['/item/update']['put']['responses']['200']['content']['application/json']['schema']['properties'];
        self::assertArrayHasKey('revision_changed_at', $updateProperties);

        $removedProperties = $spec['paths']['/item/changes']['get']['responses']['200']['content']['application/json']['schema']['properties']['removed']['items']['properties'];
        self::assertArrayHasKey('revision_changed_at', $removedProperties);
    }

    public function testDedicatedFindByUrlAndDeltaPathsReturnTheTimestamp(): void
    {
        $controller = $this->readSource('/app/api/Controller/Api/ItemController.php');
        $model = $this->readSource('/app/api/Model/ItemModel.php');

        self::assertStringContainsString('i.revision, i.revision_changed_at', $controller);
        self::assertStringContainsString("'revision_changed_at' => \$row['revision_changed_at']", $controller);
        self::assertStringContainsString('r.action, r.changed_at', $model);
        self::assertStringContainsString("'revision_changed_at' => (int) \$row['changed_at']", $model);
        self::assertStringContainsString("\$item['revision_changed_at'] = (int) \$winners[\$changedItemId]['changed_at']", $model);
    }
}
