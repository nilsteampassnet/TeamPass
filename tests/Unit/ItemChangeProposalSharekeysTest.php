<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static guard for the removed suggest_item_change handler.
 *
 * It stored a change proposal in items_change, then distributed the proposal key with
 * storeUsersShareKey('sharekeys_items', 0, <items_change id>, ...). sharekeys_items.object_id is an
 * items id and (object_id, user_id) is unique, so the call replaced, or blanked when no password was
 * proposed, the key of every user on the unrelated item carrying that id: its password could no
 * longer be decrypted by anyone. No page called the handler any more and the proposed password was
 * never read back, so the handler was removed rather than repaired.
 */
class ItemChangeProposalSharekeysTest extends TestCase
{
    private function source(string $file): string
    {
        $path = __DIR__ . '/../../app/' . $file;
        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertIsString($content);

        return $content;
    }

    public function testChangeProposalHandlerIsGone(): void
    {
        self::assertStringNotContainsString("case 'suggest_item_change':", $this->source('sources/items.queries.php'));
        self::assertStringNotContainsString("type: 'suggest_item_change'", $this->source('pages/items.js.php'));
    }

    public function testNoHandlerWritesChangeProposals(): void
    {
        foreach (glob(__DIR__ . '/../../app/sources/*.php') ?: [] as $file) {
            self::assertDoesNotMatchRegularExpression(
                '/DB::insert\(\s*prefixTable\(\'items_change\'\)/',
                (string) file_get_contents($file),
                basename($file) . ' must not write change proposals: their keys collided with item sharekeys.'
            );
        }
    }
}
