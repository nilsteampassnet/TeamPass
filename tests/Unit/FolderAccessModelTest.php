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
 * @file      FolderAccessModelTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/api/Model/FolderAccessModel.php';

class FolderAccessModelTest extends TestCase
{
    public function testNormalizeFolderIdsFromCsv(): void
    {
        $model = new FolderAccessModel();

        self::assertSame([1, 2, 3], $model->normalizeFolderIds('1,2,2,abc,3'));
    }

    public function testNormalizeFolderIdsDropsNonPositiveValues(): void
    {
        $model = new FolderAccessModel();

        self::assertSame([4, 9], $model->normalizeFolderIds(['4', 0, -3, '', '9']));
    }

    public function testNormalizeItemIdsFromArray(): void
    {
        $model = new FolderAccessModel();

        self::assertSame([8, 12], $model->normalizeItemIds(['8', '12', '12', 'not-an-id']));
    }

    public function testInvalidItemFolderSqlColumnFailsClosed(): void
    {
        $model = new FolderAccessModel();

        self::assertSame(' AND 1 = 0', $model->getItemFolderSqlConstraint('i.id_tree;DROP', 7));
    }

    public function testPersonalFolderSqlConstraintOnlyExcludesOtherUsersRoots(): void
    {
        $model = new FolderAccessModel();

        $constraint = $model->getItemFolderSqlConstraint('i.id_tree', 7);

        self::assertStringContainsString('other_personal.parent_id = 0', $constraint);
    }

    public function testPersonalFolderFiltersOnlyExcludeOtherUsersRoots(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/api/Model/FolderAccessModel.php');
        self::assertIsString($source);

        self::assertSame(3, substr_count($source, 'other_personal.parent_id = 0'));
    }
}
