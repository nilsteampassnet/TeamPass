<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/item_update_logic.php';

/**
 * Behavioural tests for DB-free API item update decisions.
 */
class ItemUpdateLogicTest extends TestCase
{
    public function testResubmittingTheSamePasswordIsNotAChange(): void
    {
        self::assertFalse(itemPasswordHasChanged('same-secret', 'same-secret'));
        self::assertFalse(itemPasswordHasChanged('', ''));
        self::assertFalse(itemPasswordHasChanged("binary\0secret", "binary\0secret"));
    }

    public function testDifferentPasswordsAreAChange(): void
    {
        self::assertTrue(itemPasswordHasChanged('old-secret', 'new-secret'));
        self::assertTrue(itemPasswordHasChanged('', 'new-secret'));
        self::assertTrue(itemPasswordHasChanged('Secret', 'secret'));
    }

    public function testComparisonUsesTheTimingSafePrimitive(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/sources/item_update_logic.php');
        self::assertIsString($source);
        self::assertStringContainsString('hash_equals($currentPassword, $submittedPassword)', $source);
    }
}
