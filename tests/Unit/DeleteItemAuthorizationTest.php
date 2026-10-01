<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static guards for the item resolution of delete_item (GHSA-ghj3-wppx-w8j3).
 *
 * delete_item checked the caller's rights on the submitted item id, then read and
 * soft-deleted with "WHERE id = %i OR item_key = %s". A user holding the delete
 * right on one item who sent another item's key (exposed in the list rows of any
 * folder they can merely read) deleted that second item too, with no log entry for
 * it. The item must be resolved once, and every later read and write keyed on that
 * id alone.
 */
class DeleteItemAuthorizationTest extends TestCase
{
    /**
     * Returns the delete_item case, from its label to the next case.
     */
    private function caseBlock(): string
    {
        $content = file_get_contents(__DIR__ . '/../../app/sources/items.queries.php');
        self::assertIsString($content);

        $start = strpos($content, "case 'delete_item':");
        self::assertNotFalse($start, 'The delete_item case must exist.');
        $end = strpos($content, 'case ', $start + strlen("case 'delete_item':"));
        self::assertNotFalse($end, 'A case must follow delete_item.');

        return substr($content, $start, $end - $start);
    }

    public function testItemIsNeverMatchedByIdOrKeyAtOnce(): void
    {
        self::assertDoesNotMatchRegularExpression(
            '/id\s*=\s*%i\s+OR\s+item_key|item_key\s*=\s*%s\s+OR\s+id/i',
            $this->caseBlock(),
            'Matching on id OR item_key lets a second, unchecked item be read or deleted.'
        );
    }

    public function testKeyIsResolvedBeforeTheAccessCheck(): void
    {
        $block = $this->caseBlock();

        $resolution = strpos($block, "WHERE item_key = %s'");
        $accessCheck = strpos($block, 'accessToItemIsGranted(');
        self::assertNotFalse($resolution, 'A key-only request must be resolved to an id.');
        self::assertNotFalse($accessCheck);
        self::assertLessThan(
            $accessCheck,
            $resolution,
            'The access check must run on the resolved id, not on an empty one.'
        );
    }

    public function testSoftDeleteIsKeyedOnTheCheckedIdOnly(): void
    {
        self::assertMatchesRegularExpression(
            "/'deleted_at' => time\(\),\s*\),\s*'id = %i',\s*\\\$inputData\['itemId'\]\s*\);/",
            $this->caseBlock(),
            'The soft delete must only touch the item whose rights were checked.'
        );
    }
}
