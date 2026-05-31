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
}
