<?php
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
 * @file      ItemHandlerTrait.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

trait ItemHandlerTrait {    

    private function generateUserPasswordKeys($arguments) {
        if (LOG_TASKS=== true) $this->logger->log('Processing generateUserPasswordKeys : '.print_r($arguments, true), 'DEBUG');
        // Generate keys for user passwords   
        storeUsersShareKey(
            prefixTable('sharekeys_items'),
            0,
            (int) $arguments['item_id'],
            (string) (array_key_exists('pwd', $arguments) === true ? $arguments['pwd'] : (array_key_exists('object_key', $arguments) === true ? $arguments['object_key'] : '')),
            false,
            false,
            [],
            array_key_exists('all_users_except_id', $arguments) === true ? $arguments['all_users_except_id'] : -1
        );
    }


    /**
     * Generate keys for user files
     * @param array $taskData
     */
    private function generateUserFileKeys($taskData) {    
        if (LOG_TASKS=== true) $this->logger->log('Processing generateUserFileKeys : '.print_r($taskData, true), 'DEBUG');
        foreach($taskData['files_keys'] as $file) {
            storeUsersShareKey(
                prefixTable('sharekeys_files'),
                0,
                (int) $file['object_id'],
                (string) $file['object_key'],
                false,
                false,
                [],
                array_key_exists('all_users_except_id', $taskData) === true ? $taskData['all_users_except_id'] : -1,
            );
        }
    }


    /**
     * Generate keys for user fields
     * @param array $arguments
     */
    private function generateUserFieldKeys($arguments) {
        if (LOG_TASKS=== true) $this->logger->log('Processing generateUserFieldKeys : '.print_r($arguments, true), 'DEBUG');
        foreach($arguments['fields_keys'] as $field) {
            $this->logger->log('Processing generateUserFieldKeys for: ' . $field['object_id'], 'DEBUG');
            storeUsersShareKey(
                prefixTable('sharekeys_fields'),
                0,
                (int) $field['object_id'],
                (string) $field['object_key'],
                false,
                false,
                [],
                array_key_exists('all_users_except_id', $arguments) === true ? $arguments['all_users_except_id'] : -1,
            );
        }
    }


    /**
     * Handle item copy
     * @param array $arguments
     */
    private function handleItemCopy($arguments) {
        storeUsersShareKey(
            prefixTable('sharekeys_items'),
            0,
            $arguments['item_id'],
            $arguments['object_key'] ?? '',
            false,
            false,
            [],
            $arguments['all_users_except_id'] ?? -1
        );
    }


    /**
     * Handle item update for passwords
     * @param array $arguments
     */
    private function handleItemUpdateCreateKeys($arguments) {
        storeUsersShareKey(
            prefixTable('sharekeys_items'),
            0,
            (int) $arguments['item_id'],
            (string) (array_key_exists('pwd', $arguments) === true ? $arguments['pwd'] : (array_key_exists('object_key', $arguments) === true ? $arguments['object_key'] : '')),
            false,
            false,
            [],
            array_key_exists('all_users_except_id', $arguments) === true ? $arguments['all_users_except_id'] : -1
        );
    }

    /**
     * Handle item update for fields
     * @param array $arguments
     */
    private function handleFieldUpdateCreateKeys($arguments) {
        foreach ($arguments['fields_keys'] ?? [] as $field) {
            storeUsersShareKey(
                prefixTable('sharekeys_items'),
                0,
                $field['object_id'],
                $field['object_key'],
                false,
                false,
                [],
                $arguments['all_users_except_id'] ?? -1
            );
        }
    }
}