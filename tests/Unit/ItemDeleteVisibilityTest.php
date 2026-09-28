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
 * Regression guards for item deletion action visibility.
 *
 * @file      ItemDeleteVisibilityTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\TestCase;

class ItemDeleteVisibilityTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $path = __DIR__ . '/../../' . $relativePath;
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return str_replace("\r\n", "\n", $source);
    }

    public function testFolderAndSearchListsExposeEffectiveDeleteCapability(): void
    {
        $items = $this->source('app/sources/items.queries.php');
        $find = $this->source('app/sources/find.queries.php');

        self::assertStringContainsString('$folderRights = getCurrentFolderAccessRights(', $items);
        self::assertStringContainsString("['can_delete'] = \$canDeleteItemsInFolder ? 1 : 0", $items);
        self::assertStringContainsString(
            '$searchAccessLevels[$folderId] = evaluateFolderAccesLevel(',
            $find
        );
        self::assertStringContainsString('itemAccessFolderAllowsDelete(', $find);
        self::assertStringContainsString("\$searchAccessLevels[\$searchFolderId] ?? ''", $find);
        self::assertStringContainsString("['can_delete'] = (\$searchFolderDeletePermissions", $find);
    }

    public function testTrashActionRequiresDeleteCapabilityAndLaprAllowsOnlyHiding(): void
    {
        $javascript = $this->source('app/pages/items.js.php');

        self::assertStringContainsString('trash_link = value.can_delete !== 1', $javascript);
        self::assertStringContainsString(
            ".toggleClass('hidden', !canDelete || isManaged || isCredential);",
            $javascript
        );
        self::assertStringNotContainsString(
            "$('.tp-action[data-item-action=\"delete\"]').closest('.nav-item').toggleClass('hidden', isManaged || isCredential);",
            $javascript
        );
    }
}
