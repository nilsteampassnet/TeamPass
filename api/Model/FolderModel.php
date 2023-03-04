<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 * @version    API
 *
 * @file      folderModel.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */
require_once API_ROOT_PATH . "/Model/Database.php";
 
class FolderModel extends Database
{
    public function getFoldersInfo(array $foldersId): array
    {
        $rows = $this->select( "SELECT id, title FROM " . prefixTable('nested_tree') . " WHERE nlevel=1" );

        $ret = [];

        foreach ($rows as $row) {
			$isVisible = in_array((int) $row['id'], $foldersId);
            $childrens = $this->getFoldersChildren($row['id'], $foldersId);

            if ($isVisible || count($childrens) > 0) {
                array_push(
                    $ret,
                    [
                        'id' => (int) $row['id'],
                        'title' => $row['title'],
						'isVisible' => $isVisible,
                        'childrens' => $childrens
                    ]
                );
            }
        }

        return $ret;
    }

    private function getFoldersChildren(int $parentId, array $foldersId): array
    {
        $ret = [];
        $childrens = $this->select('SELECT id, title FROM ' . prefixTable('nested_tree') . ' WHERE parent_id=' . $parentId);

        if ( count($childrens) > 0) {
            foreach ($childrens as $children) {
				$isVisible = in_array((int) $children['id'], $foldersId);
                $childs = $this->getFoldersChildren($children['id'], $foldersId);

                if (in_array((int) $children['id'], $foldersId) || count($childs) > 0) {
                    array_push(
                        $ret,
                        [
                            'id' => (int) $children['id'],
                            'title' => $children['title'],
							'isVisible' => $isVisible,
                            'childrens' => $childs
                        ]
                    );
                }
            }
        }

        return $ret;
    }
}