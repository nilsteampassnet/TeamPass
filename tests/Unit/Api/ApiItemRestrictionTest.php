<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../app/api/Model/ApiIdempotencyModel.php';
require_once __DIR__ . '/../../../app/api/Controller/Api/BaseController.php';
require_once __DIR__ . '/../../../app/api/Controller/Api/ItemController.php';

/**
 * Contract of the optional item-level restriction on POST /item/create and PUT /item/update (#5476).
 *
 * The semantics (normalization, author kept in the list) are asserted in
 * ItemRestrictionLogicTest; this class asserts the wiring: idempotency fingerprint, ordering
 * against the reservation and the transaction, and the published OpenAPI schema.
 */
class ApiItemRestrictionTest extends TestCase
{
    private const LEGACY_PARAMS = [
        'folder_id' => 7, 'label' => 'Database', 'login' => '', 'password' => 'secret',
        'email' => '', 'url' => '', 'description' => '', 'tags' => '', 'totp' => '',
        'totp_algorithm' => 'sha1', 'totp_digits' => 6, 'totp_period' => 30,
        'fields' => [], 'icon' => '', 'anyone_can_modify' => 0,
    ];

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function intent(array $params): array
    {
        $controller = new ItemController();
        $method = new ReflectionMethod($controller, 'createIdempotencyIntent');

        return $method->invoke($controller, $params);
    }

    public function testEmptyRestrictionKeepsTheLegacyFingerprint(): void
    {
        self::assertSame(self::LEGACY_PARAMS, $this->intent(self::LEGACY_PARAMS));
        self::assertSame(
            self::LEGACY_PARAMS,
            $this->intent(self::LEGACY_PARAMS + ['restricted_to' => [], 'restricted_to_roles' => []])
        );
    }

    public function testRequestedRestrictionIsPartOfTheFingerprint(): void
    {
        $model = new ApiIdempotencyModel('unit-test-idempotency-secret');
        $legacy = $model->fingerprint($this->intent(self::LEGACY_PARAMS));

        $users = $this->intent(self::LEGACY_PARAMS + ['restricted_to' => [42]]);
        $roles = $this->intent(self::LEGACY_PARAMS + ['restricted_to_roles' => [42]]);
        self::assertSame([42], $users['restricted_to']);
        self::assertSame([42], $roles['restricted_to_roles']);
        self::assertNotSame($legacy, $model->fingerprint($users));
        self::assertNotSame($legacy, $model->fingerprint($roles));
        self::assertNotSame($model->fingerprint($users), $model->fingerprint($roles));
    }

    public function testControllerNormalizesTheRestrictionBeforeTheReservation(): void
    {
        $controller = (string) file_get_contents(__DIR__ . '/../../../app/api/Controller/Api/ItemController.php');
        $start = strpos($controller, 'public function createAction(');
        $end = strpos($controller, 'public function getAction(', (int) $start);
        $create = substr($controller, (int) $start, (int) $end - (int) $start);

        $normalize = strpos($create, 'itemRestrictionNormalizeRequestedIds(');
        self::assertNotFalse($normalize);
        self::assertLessThan(strpos($create, '->reserve('), $normalize);
    }

    public function testModelValidatesBeforeAndWritesInsideTheCreateTransaction(): void
    {
        $model = (string) file_get_contents(__DIR__ . '/../../../app/api/Model/ItemModel.php');
        $start = strpos($model, 'public function addItem(');
        $end = strpos($model, 'private function getFaviconUrl', (int) $start);
        $create = substr($model, (int) $start, (int) $end - (int) $start);

        // Validation (and its 422) happens before anything is written.
        self::assertLessThan(strpos($create, 'DB::startTransaction();'), strpos($create, '$this->resolveCreateRestriction('));

        // The role rows share the item's transaction.
        $rolesInsert = strpos($create, "prefixTable('restriction_to_roles')");
        self::assertNotFalse($rolesInsert);
        self::assertGreaterThan(strpos($create, '$this->insertNewItem('), $rolesInsert);
        self::assertLessThan(strpos($create, 'DB::commit();'), $rolesInsert);

        // The item row carries the resolved user list instead of a hard-coded empty value.
        self::assertStringContainsString("'restricted_to' => (string) (\$data['restrictedTo'] ?? '')", $model);
        self::assertStringNotContainsString("'restricted_to' => '',", $model);
    }

    public function testOpenApiDocumentsTheOptionalRestriction(): void
    {
        $spec = json_decode(
            (string) file_get_contents(__DIR__ . '/../../../app/api/openapi.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $schema = $spec['components']['schemas']['ItemCreateBody'];

        foreach (['restricted_to', 'restricted_to_roles'] as $field) {
            self::assertNotContains($field, $schema['required']);
            self::assertSame('array', $schema['properties'][$field]['type']);
            self::assertSame('integer', $schema['properties'][$field]['items']['type']);
            self::assertSame(100, $schema['properties'][$field]['maxItems']);
            self::assertStringContainsString('422', $schema['properties'][$field]['description']);
        }
        self::assertStringContainsString('always added', $schema['properties']['restricted_to']['description']);

        $update = $spec['components']['schemas']['ItemUpdateBody'];
        foreach (['restricted_to', 'restricted_to_roles'] as $field) {
            self::assertSame(['array', 'null'], $update['properties'][$field]['type']);
            self::assertArrayNotHasKey('default', $update['properties'][$field], 'Omission must keep the stored list.');
            self::assertStringContainsString('Only added', $update['properties'][$field]['description']);
        }
        self::assertStringContainsString('restricted_to_roles', $spec['paths']['/item/update']['put']['description']);
    }

    public function testUpdateAcceptsTheRestrictionAsAnUpdatableField(): void
    {
        $controller = (string) file_get_contents(__DIR__ . '/../../../app/api/Controller/Api/ItemController.php');
        $start = strpos($controller, 'public function updateAction(');
        $end = strpos($controller, 'public function deleteAction(', (int) $start);
        $update = substr($controller, (int) $start, (int) $end - (int) $start);

        $fieldsStart = strpos($update, '$updateableFields = [');
        $fields = substr($update, (int) $fieldsStart, (int) strpos($update, '];', (int) $fieldsStart) - (int) $fieldsStart);
        self::assertStringContainsString("'restricted_to',", $fields);
        self::assertStringContainsString("'restricted_to_roles',", $fields);
    }

    public function testUpdateValidatesBeforeWritingAndChangesBothHalvesAtomically(): void
    {
        $model = (string) file_get_contents(__DIR__ . '/../../../app/api/Model/ItemModel.php');
        $start = strpos($model, 'public function updateItem(');
        $end = strpos($model, 'private function getFolderTitle', (int) $start);
        $update = substr($model, (int) $start, (int) $end - (int) $start);

        $itemWrite = strpos($update, 'prefixTable(\'items\'),');
        self::assertLessThan($itemWrite, strpos($update, '$this->resolveUpdateRestriction('));

        // Users (items.restricted_to) and roles land in the same transaction.
        $transactionStart = strpos($update, 'DB::startTransaction();');
        $commit = strpos($update, 'DB::commit();', (int) $transactionStart);
        $rolesReplace = strpos($update, "DB::delete(prefixTable('restriction_to_roles')");
        self::assertNotFalse($rolesReplace);
        self::assertGreaterThan($transactionStart, $rolesReplace);
        self::assertLessThan($commit, $rolesReplace);
        self::assertStringContainsString('$restrictionUpdate !== null;', $update);

        // Dedicated history entry (bumps the revision), kept out of the generic one.
        self::assertStringContainsString("'at_restriction : '", $update);
        self::assertStringContainsString("['pw', 'pw_iv', 'pw_len', 'complexity_level', 'restricted_to']", $update);

        // A personal-to-shared move commits on its own: the restriction must wait.
        self::assertMatchesRegularExpression("/'totp_period',\s*'restricted_to', 'restricted_to_roles',/", $update);
    }

    public function testUpdateOnlyValidatesTheEntriesItAdds(): void
    {
        $model = (string) file_get_contents(__DIR__ . '/../../../app/api/Model/ItemModel.php');
        $start = strpos($model, 'private function resolveUpdateRestriction(');
        $end = strpos($model, 'private function describeRestriction(', (int) $start);
        $resolver = substr($model, (int) $start, (int) $end - (int) $start);

        self::assertStringContainsString('array_values(array_diff($userIds, $currentUserIds))', $resolver);
        self::assertStringContainsString('array_values(array_diff($roleIds, $currentRoleIds))', $resolver);
        self::assertStringContainsString('itemRestrictionUsersWithAuthor($userIds, $roleIds, $callerId)', $resolver);
        self::assertStringContainsString('return null;', $resolver);
    }

    /**
     * The web "Restricted to" list must offer every user able to open the folder: a user missing
     * from it is silently dropped from the restriction at the next edit-form save.
     */
    public function testWebRestrictionCandidatesIncludeEveryRoleSourceAndDirectGrants(): void
    {
        $handler = (string) file_get_contents(__DIR__ . '/../../../app/sources/items.queries.php');
        $start = strpos($handler, '$candidateUsers = [];');
        self::assertNotFalse($start);
        $end = strpos($handler, '// Get access level for this folder', (int) $start);
        $candidates = substr($handler, (int) $start, (int) $end - (int) $start);

        self::assertStringNotContainsString('ur.source', $candidates);
        self::assertStringContainsString("prefixTable('users_groups')", $candidates);
        self::assertStringContainsString('ug.group_id = %i', $candidates);
        self::assertStringContainsString('$listedUserIds', $candidates, 'A user in several roles is listed once.');
    }

    /**
     * The same answer discloses the folder's members (names, e-mails): it must be refused for a
     * folder outside user-accessible_folders — which, unlike the role-based access level computed
     * later, excludes the folders explicitly denied to the caller (users_groups_forbidden).
     */
    public function testWebRestrictionCandidatesRequireAnAccessibleFolder(): void
    {
        $handler = (string) file_get_contents(__DIR__ . '/../../../app/sources/items.queries.php');
        $start = strpos($handler, "case 'get_complixity_level':");
        self::assertNotFalse($start);
        $case = substr($handler, (int) $start, (int) strpos($handler, '$candidateUsers = [];', (int) $start) - (int) $start);

        $gate = strpos($case, "\$session->get('user-accessible_folders')");
        self::assertNotFalse($gate, 'The member lists must be gated on the accessible folders.');
        self::assertGreaterThan(strpos($case, 'getItemFolderIdFromDb('), $gate, 'Gate the folder actually answered for.');
        self::assertLessThan(strpos($case, '$data_this_folder = DB::queryFirstRow('), $gate);
        self::assertStringContainsString("error_not_allowed_to_access_this_folder", substr($case, (int) $gate));
    }
}
