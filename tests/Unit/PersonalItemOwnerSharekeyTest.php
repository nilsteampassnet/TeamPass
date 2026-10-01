<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regression guards for issue #5407.
 *
 * Saving a personal item narrowed its keys to the item's creator. Once a shared item has been
 * moved into someone's personal folder, the creator is not the owner, so the owner's own key was
 * deleted at every save and only the TP_USER recovery key survived.
 */
class PersonalItemOwnerSharekeyTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $source = file_get_contents(__DIR__ . '/../..' . $relativePath);
        self::assertIsString($source);

        return $source;
    }

    private function section(string $source, string $startMarker, string $endMarker): string
    {
        $start = strpos($source, $startMarker);
        self::assertIsInt($start, 'Start marker must exist: ' . $startMarker);
        $end = strpos($source, $endMarker, $start + strlen($startMarker));
        self::assertIsInt($end, 'End marker must exist after: ' . $startMarker);

        return substr($source, $start, $end - $start);
    }

    public function testUpdateItemNarrowsPersonalKeysToTheEditor(): void
    {
        $items = $this->source('/app/sources/items.queries.php');

        self::assertSame(1, substr_count($items, 'EnsurePersonalItemHasOnlyKeysForOwner('));
        self::assertStringContainsString(
            "EnsurePersonalItemHasOnlyKeysForOwner((int) \$session->get('user-id'), (int) \$inputData['itemId']);",
            $items,
            'The editor holds the key storeUsersShareKey() just wrote, not the creator'
        );
        self::assertStringNotContainsString("EnsurePersonalItemHasOnlyKeysForOwner(intval(\$dataItem['id_user'])", $items);
    }

    public function testCleanupResolvesTheOwnerFromThePersonalTree(): void
    {
        $cleanup = $this->section(
            $this->source('/app/sources/main.functions.php'),
            'function EnsurePersonalItemHasOnlyKeysForOwner(',
            'function deleteForeignItemSharekeys('
        );

        self::assertStringContainsString('root.personal_folder = 1 AND root.parent_id = 0', $cleanup);
        self::assertStringContainsString('root.title = %s', $cleanup);
        self::assertStringNotContainsString("prefixTable('log_items')", $cleanup, 'The creator is not the owner');

        $check = strpos($cleanup, 'userHoldsEveryItemSharekey($itemId, $userId)');
        $purge = strpos($cleanup, 'deleteForeignItemSharekeys($itemId, $userId);');
        self::assertIsInt($check, 'The owner must hold every key before the others are deleted');
        self::assertIsInt($purge);
        self::assertLessThan($purge, $check);
    }

    public function testItemWithoutPasswordNeedsNoItemKey(): void
    {
        $check = $this->section(
            $this->source('/app/sources/main.functions.php'),
            'function userHoldsEveryItemSharekey(',
            "\n}\n"
        );

        self::assertStringContainsString('item.pw != ""', $check, 'A cleared password leaves no item key to hold');
    }
}
