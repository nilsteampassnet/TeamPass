<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 *
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * Regression guards for item creation authorization.
 *
 * @file      ItemCreationAuthorizationTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\TestCase;

class ItemCreationAuthorizationTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $path = __DIR__ . '/../../app/sources/items.queries.php';
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);
        $this->source = str_replace("\r\n", "\n", $source);
    }

    /** Extract one switch case without executing the web controller. */
    private function caseBlock(string $case, string $nextCase): string
    {
        $start = strpos($this->source, "case '$case':");
        self::assertNotFalse($start, $case);
        $end = strpos($this->source, "case '$nextCase':", $start);
        self::assertNotFalse($end, $nextCase);

        return substr($this->source, $start, $end - $start);
    }

    /** Extract one function without executing the web controller. */
    private function functionBlock(string $function, string $nextFunction): string
    {
        $start = strpos($this->source, 'function ' . $function . '(');
        self::assertNotFalse($start, $function);
        $end = strpos($this->source, 'function ' . $nextFunction . '(', $start);
        self::assertNotFalse($end, $nextFunction);

        return substr($this->source, $start, $end - $start);
    }

    public function testNewItemRequiresTheFolderCreateCapability(): void
    {
        $block = $this->caseBlock('new_item', 'update_item');
        $rightsCheck = strpos($block, 'getCurrentFolderAccessRights(');
        $createCheck = strpos($block, "\$folderRights['create'] === false");
        $insert = strpos($block, "DB::insert(\n                        prefixTable('items')");

        self::assertIsInt($rightsCheck);
        self::assertIsInt($createCheck);
        self::assertIsInt($insert);
        self::assertLessThan($createCheck, $rightsCheck);
        self::assertLessThan($insert, $createCheck);
    }

    public function testCopySeparatesSourceItemAndDestinationFolderAuthorization(): void
    {
        $block = $this->caseBlock('copy_item', 'show_details_item');

        self::assertMatchesRegularExpression(
            '/\$sourceRights = getCurrentAccessRights\(\s*'
            . '\(int\) \$session->get\(\'user-id\'\),\s*'
            . '\(int\) \$inputData\[\'itemId\'\],\s*'
            . '\(int\) \$originalRecord\[\'id_tree\'\],\s*\);/',
            $block
        );
        self::assertStringContainsString("\$sourceRights['access'] === false", $block);
        self::assertStringContainsString("isProcessOnGoing((int) \$inputData['itemId'])", $block);
        self::assertMatchesRegularExpression(
            '/\$destinationRights = getCurrentFolderAccessRights\(\s*'
            . '\(int\) \$session->get\(\'user-id\'\),\s*'
            . '\(int\) \$post_dest_id,\s*\);/',
            $block
        );
        self::assertStringContainsString("\$destinationRights['create'] === false", $block);
    }

    public function testItemRightsDelegateFolderDecisionsToTheFolderResolver(): void
    {
        $folderResolver = $this->functionBlock('getCurrentFolderAccessRights', 'getCurrentAccessRights');
        $itemResolver = $this->functionBlock('getCurrentAccessRights', 'getItemRestrictedUsersList');

        self::assertStringContainsString('getCurrentFolderAccessRights($userId, $treeId)', $itemResolver);
        foreach (['getItemRestrictedUsersList(', 'isProcessOnGoing(', 'isItemLocked('] as $itemDecision) {
            self::assertStringNotContainsString($itemDecision, $folderResolver);
        }
        self::assertStringContainsString('getItemRestrictedUsersList(', $itemResolver);
        self::assertStringContainsString('isProcessOnGoing(', $itemResolver);
        self::assertStringContainsString('isItemLocked(', $itemResolver);
    }
}
