<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../app/api/Model/ApiIdempotencyModel.php';
require_once __DIR__ . '/../../../app/api/Controller/Api/BaseController.php';
require_once __DIR__ . '/../../../app/api/Controller/Api/ItemController.php';

/**
 * Behavioural and persistence-contract tests for idempotent item mutations.
 */
class ApiItemIdempotencyTest extends TestCase
{
    private ApiIdempotencyModel $model;

    protected function setUp(): void
    {
        $this->model = new ApiIdempotencyModel('unit-test-idempotency-secret');
    }

    public function testKeyValidationAcceptsOpaqueVisibleAscii(): void
    {
        $key = '550e8400-e29b-41d4-a716-446655440000';

        self::assertSame($key, ApiIdempotencyModel::validateKey($key));
    }

    /**
     * @dataProvider invalidKeyProvider
     */
    public function testKeyValidationRejectsEmptyMalformedAndOversizedValues(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        ApiIdempotencyModel::validateKey($key);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidKeyProvider(): array
    {
        return [
            'empty' => [''],
            'space' => ['not opaque'],
            'control' => ["bad\nkey"],
            'non-ascii' => ['clé'],
            'too long' => [str_repeat('a', ApiIdempotencyModel::MAX_KEY_LENGTH + 1)],
        ];
    }

    public function testFingerprintIgnoresObjectPropertyOrderButCoversSecrets(): void
    {
        $first = [
            'label' => 'Database',
            'password' => 'First-secret',
            'fields' => [['id' => 4, 'value' => 'Sensitive field']],
            'totp' => 'JBSWY3DPEHPK3PXP',
        ];
        $reordered = [
            'totp' => 'JBSWY3DPEHPK3PXP',
            'fields' => [['value' => 'Sensitive field', 'id' => 4]],
            'password' => 'First-secret',
            'label' => 'Database',
        ];

        $fingerprint = $this->model->fingerprint($first);
        self::assertSame($fingerprint, $this->model->fingerprint($reordered));
        self::assertNotSame($fingerprint, $this->model->fingerprint(array_replace($first, ['password' => 'Other-secret'])));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $fingerprint);
        self::assertStringNotContainsString('First-secret', $fingerprint);
        self::assertStringNotContainsString('JBSWY3DPEHPK3PXP', $fingerprint);
        self::assertNotSame(
            $fingerprint,
            (new ApiIdempotencyModel('another-server-secret'))->fingerprint($first)
        );
    }

    public function testDeleteFingerprintCoversItemAndExpectedRevision(): void
    {
        $original = $this->model->fingerprint(['item_id' => 7, 'revision' => 12]);

        self::assertNotSame($original, $this->model->fingerprint(['item_id' => 8, 'revision' => 12]));
        self::assertNotSame($original, $this->model->fingerprint(['item_id' => 7, 'revision' => 13]));
        self::assertNotSame($original, $this->model->fingerprint(['item_id' => 7, 'revision' => null]));
    }

    public function testExistingRecordStateMachineDistinguishesReplayConflictAndProcessing(): void
    {
        $fingerprint = $this->model->fingerprint(['item_id' => 7, 'revision' => 12]);
        $now = 1_800_000_000;

        self::assertSame(
            ['state' => 'replay'],
            $this->model->evaluateRecord([
                'request_fingerprint' => $fingerprint,
                'status' => 'completed',
                'locked_until' => 0,
            ], $fingerprint, $now)
        );
        self::assertSame(
            ['state' => 'conflict'],
            $this->model->evaluateRecord([
                'request_fingerprint' => str_repeat('0', 64),
                'status' => 'completed',
                'locked_until' => 0,
            ], $fingerprint, $now)
        );
        self::assertSame(
            ['state' => 'processing', 'retry_after' => 20],
            $this->model->evaluateRecord([
                'request_fingerprint' => $fingerprint,
                'status' => 'processing',
                'locked_until' => $now + 20,
            ], $fingerprint, $now)
        );
        self::assertSame(
            ['state' => 'stale'],
            $this->model->evaluateRecord([
                'request_fingerprint' => $fingerprint,
                'status' => 'processing',
                'locked_until' => $now - 1,
            ], $fingerprint, $now)
        );
    }

    public function testDeleteRevisionParserAcceptsOmissionAndUnsignedRange(): void
    {
        $controller = new ItemController();
        $method = new ReflectionMethod($controller, 'parseOptionalRevision');
        $method->setAccessible(true);

        self::assertNull($method->invoke($controller, ['id' => 1]));
        self::assertSame(0, $method->invoke($controller, ['revision' => '0']));
        self::assertSame(4294967295, $method->invoke($controller, ['revision' => '4294967295']));
    }

    /**
     * @dataProvider invalidRevisionProvider
     */
    public function testDeleteRevisionParserRejectsMalformedValues($revision): void
    {
        $controller = new ItemController();
        $method = new ReflectionMethod($controller, 'parseOptionalRevision');
        $method->setAccessible(true);

        $this->expectException(InvalidArgumentException::class);
        $method->invoke($controller, ['revision' => $revision]);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidRevisionProvider(): array
    {
        return [
            'empty' => [''],
            'negative' => ['-1'],
            'decimal' => ['1.5'],
            'scientific' => ['1e2'],
            'overflow' => ['4294967296'],
            'float' => [1.0],
        ];
    }

    public function testSchemaScopesKeysPerUserAndOperationWithoutSecretColumns(): void
    {
        $install = (string) file_get_contents(__DIR__ . '/../../../public/install/install-steps/run.step5.php');
        $upgrade = (string) file_get_contents(__DIR__ . '/../../../public/install/upgrade_run_3.2.2.php');

        foreach ([$install, $upgrade] as $schema) {
            $tableStart = strpos($schema, 'api_idempotency` (');
            self::assertNotFalse($tableStart);
            $tableDefinition = substr($schema, (int) $tableStart, 1800);

            self::assertStringContainsString('api_idempotency', $schema);
            self::assertStringContainsString('`user_id`, `operation`, `key_hash`', $schema);
            self::assertStringContainsString('`request_fingerprint` CHAR(64)', $schema);
            self::assertStringNotContainsString('`raw_key`', $tableDefinition);
            self::assertStringNotContainsString('`request_body`', $tableDefinition);
            self::assertStringNotContainsString('`password`', $tableDefinition);
            self::assertStringNotContainsString('`totp_secret`', $tableDefinition);
        }
    }

    public function testCreateAndDeleteFinalizeReplayMetadataInsideTheirMutationTransactions(): void
    {
        $model = (string) file_get_contents(__DIR__ . '/../../../app/api/Model/ItemModel.php');

        $createStart = strpos($model, 'public function addItem(');
        $createEnd = strpos($model, 'private function getFaviconUrl', (int) $createStart);
        $create = substr($model, (int) $createStart, (int) $createEnd - (int) $createStart);
        self::assertLessThan(strpos($create, '$this->insertNewItem('), strpos($create, 'DB::startTransaction();'));
        self::assertLessThan(strpos($create, 'DB::commit();'), strpos($create, 'completeReservation('));
        self::assertGreaterThan(strpos($create, 'DB::commit();'), strrpos($create, 'emitItemSyslog('));
        self::assertStringNotContainsString('$e->getMessage()', $create);

        $deleteStart = strpos($model, 'public function deleteItem(');
        $delete = substr($model, (int) $deleteStart);
        self::assertLessThan(strpos($delete, "'inactif' => '1'"), strpos($delete, 'FOR UPDATE'));
        self::assertLessThan(strpos($delete, "'inactif' => '1'"), strpos($delete, '$expectedRevision !== null'));
        self::assertLessThan(strpos($delete, 'DB::commit();'), strpos($delete, 'completeReservation('));
        self::assertGreaterThan(strpos($delete, 'DB::commit();'), strrpos($delete, 'emitItemSyslog('));
        self::assertStringNotContainsString('$e->getMessage()', $delete);
    }

    public function testControllerKeepsKeysOptionalAndShortCircuitsCompletedReplays(): void
    {
        $controller = (string) file_get_contents(
            __DIR__ . '/../../../app/api/Controller/Api/ItemController.php'
        );

        $createStart = strpos($controller, 'public function createAction(');
        $createEnd = strpos($controller, 'public function getAction(', (int) $createStart);
        $create = substr($controller, (int) $createStart, (int) $createEnd - (int) $createStart);
        self::assertStringContainsString('$idempotencyModel = null;', $create);
        self::assertLessThan(strpos($create, '->reserve('), strpos($create, 'checkNewItemData('));
        self::assertLessThan(strpos($create, '$itemModel->addItem('), strpos($create, "['state'] === 'replay'"));
        self::assertStringContainsString('Idempotency-Replayed: true', $create);
        self::assertStringContainsString('releaseReservation(', $create);

        $deleteStart = strpos($controller, 'public function deleteAction(');
        $delete = substr($controller, (int) $deleteStart);
        self::assertStringContainsString('$idempotencyModel = null;', $delete);
        self::assertLessThan(strpos($delete, '->reserve('), strpos($delete, 'canDeleteInFolder('));
        self::assertLessThan(strpos($delete, '$itemModel->deleteItem('), strpos($delete, "['state'] === 'replay'"));
        self::assertStringContainsString('Idempotency-Replayed: true', $delete);
        self::assertStringContainsString('releaseReservation(', $delete);
    }

    public function testMaintenanceKeepsActiveProcessingLeases(): void
    {
        $maintenance = (string) file_get_contents(__DIR__ . '/../../../app/sources/main.functions.php');
        $start = strpos($maintenance, 'function pruneApiIdempotencyRecords()');
        self::assertNotFalse($start);
        $block = substr($maintenance, (int) $start, 2200);

        self::assertStringContainsString('expires_at < %i', $block);
        self::assertStringContainsString('locked_until < %i', $block);
        self::assertStringNotContainsString('locked_until > %i', $block);
    }

    public function testOpenApiDocumentsCreateAndDeleteIdempotencyAndDeleteRevision(): void
    {
        $spec = json_decode(
            (string) file_get_contents(__DIR__ . '/../../../app/api/openapi.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame(
            '#/components/parameters/IdempotencyKey',
            $spec['paths']['/item/create']['post']['parameters'][0]['$ref']
        );
        self::assertSame(
            '#/components/parameters/IdempotencyKey',
            $spec['paths']['/item/delete']['delete']['parameters'][0]['$ref']
        );
        self::assertSame('revision', $spec['paths']['/item/delete']['delete']['parameters'][2]['name']);
        self::assertArrayHasKey('409', $spec['paths']['/item/create']['post']['responses']);
        self::assertArrayHasKey('409', $spec['paths']['/item/delete']['delete']['responses']);
        self::assertArrayHasKey(
            'revision_changed_at',
            $spec['paths']['/item/delete']['delete']['responses']['200']['content']['application/json']['schema']['properties']
        );
    }
}
