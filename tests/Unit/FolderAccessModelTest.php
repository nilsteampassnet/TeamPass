<?php
declare(strict_types=1);

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

    /**
     * Guards the personal-folder fix: the deny-list must restrict to top-level
     * personal roots (parent_id = 0) in all three exclusion sites, so a user's
     * own personal subfolders are not misclassified as another user's root.
     */
    public function testPersonalFolderFiltersOnlyExcludeOtherUsersRoots(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/api/Model/FolderAccessModel.php');
        self::assertIsString($source);

        self::assertSame(3, substr_count($source, 'other_personal.parent_id = 0'));
    }
}
