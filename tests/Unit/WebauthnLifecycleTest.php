<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: passkeys are a fourth sharekeys family and must follow their item everywhere the
 * attachments do. A lifecycle site that forgets them either leaves a passkey unusable (keys
 * never distributed) or leaves keys behind (personal passkey still readable by others, orphaned
 * encrypted key after a purge).
 */
final class WebauthnLifecycleTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3?: string}>
     */
    public static function lifecycleSites(): array
    {
        return [
            'personal to shared move' => ['/app/sources/main.functions.php', 'function movePersonalItemToSharedFolderSynchronously(', 'function finalizeItemMoveSideEffects('],
            'deleted user keys' => ['/app/sources/main.functions.php', 'function deleteUserObjetsKeys(', 'function timezone_list('],
            'personal keys purge' => ['/app/sources/main.functions.php', 'function purgeUnnecessaryKeysForUser(', "\n}\n"],
            'foreign keys purge on a personal item' => ['/app/sources/main.functions.php', 'function deleteForeignItemSharekeys(', 'function restrictItemSharekeysToOwnerIfPersonal('],
            'key check before narrowing to the owner' => ['/app/sources/main.functions.php', 'function userHoldsEveryItemSharekey(', "\n}\n"],
            'repair tool scopes' => ['/app/sources/main.functions.php', 'function restoreSharekeysScopeDefs(', "\n}\n"],
            'new user keys subtasks' => ['/app/sources/main.functions.php', 'function createUserTasks(', 'function createAllSubTasks(', "'step70' => 'SELECT w.id FROM ' . prefixTable('webauthn_credentials')"],
            'new user keys worker' => ['/app/scripts/traits/UserHandlerTrait.php', 'private function generateNewUserStep70(', 'private function generateNewUserStep99('],
            'hard delete' => ['/app/sources/utilities.queries.php', 'function tpHardDeleteItem(', 'function tpFormatDbVersionShort('],
            'orphan cleanup' => ['/app/scripts/task_maintenance_clean_orphan_objects.php', 'function cleanOrphanObjectsAndScanIntegrity(', 'updateCacheTable('],
            'web move shared to personal' => ['/app/sources/items.queries.php', "case 'move_item':", "case 'mass_move_items':"],
            'web mass move shared to personal' => ['/app/sources/items.queries.php', "case 'mass_move_items':", "\n        break;\n"],
            'lost password personal reset' => ['/app/sources/main.queries.php', 'function setUserOnlyPersonalItemsEncryption(', 'findValidPreviousPrivateKey('],
        ];
    }

    /**
     * @dataProvider lifecycleSites
     */
    public function testSiteHandlesPasskeys(string $file, string $startMarker, string $endMarker, string $needle = 'sharekeys_webauthn'): void
    {
        $section = $this->section($file, $startMarker, $endMarker);

        $this->assertStringContainsString($needle, $section);
    }

    public function testNewUserKeysTaskDispatchesThePasskeyStep(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/scripts/traits/UserHandlerTrait.php');

        $this->assertMatchesRegularExpression("/case 'step70':\s*\\\$this->generateNewUserStep70\(/", $source);
    }

    public function testRepairScreensWalkThePasskeyScope(): void
    {
        foreach (['/app/pages/tools.js.php', '/app/pages/profile.js.php'] as $file) {
            $this->assertStringContainsString("'files', 'webauthn']", (string) file_get_contents(__DIR__ . '/../..' . $file), $file);
        }
        $this->assertStringContainsString("'webauthn' => [", $this->section('/app/sources/tools.queries.php', '$detailDefs = [', '$details = [];'));
    }

    public function testCopiesAndExportsNeverTouchPasskeys(): void
    {
        // A copied credential id would bind two items to one account on the relying party, and an
        // export must never carry a credential private key.
        foreach (['/app/sources/export.queries.php', '/app/sources/folders.queries.php', '/app/includes/templates/offline-export.tpl.php'] as $file) {
            $this->assertStringNotContainsString('webauthn', (string) file_get_contents(__DIR__ . '/../..' . $file), $file);
        }
        $this->assertStringNotContainsString('webauthn', $this->section('/app/sources/items.queries.php', "case 'copy_item':", "case 'show_details_item':"));
    }

    private function section(string $file, string $startMarker, string $endMarker): string
    {
        $source = (string) file_get_contents(__DIR__ . '/../..' . $file);
        $start = strpos($source, $startMarker);
        $this->assertIsInt($start, $file . ': start marker not found: ' . $startMarker);
        $end = strpos($source, $endMarker, $start + strlen($startMarker));
        $this->assertIsInt($end, $file . ': end marker not found after ' . $startMarker);

        return substr($source, $start, $end - $start);
    }
}
