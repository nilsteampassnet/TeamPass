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
    public function getItems($limit)
    {
        if ($limit > 0) {
            return $this->select("SELECT * FROM ".prefixTable('items')." ORDER BY id ASC LIMIT ?", ["i", $limit]);
        } else {
            return $this->select("SELECT * FROM ".prefixTable('items')." WHERE id_tree=590 ORDER BY id ASC");
        }
    }
}