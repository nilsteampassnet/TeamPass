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
 * @file      phpseclibv3_migration.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once 'main.functions.php';

// Init
loadClasses('DB');
$session = SessionManager::getSession();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Check session
if (!$session->has('user-id') || $session->get('user-id') === null) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Get request type
$type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

switch ($type) {
    /**
     * Get phpseclib v3 migration progress
     */
    case 'get_phpseclibv3_migration_progress':
        $taskId = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);

        if (empty($taskId)) {
            echo json_encode(['error' => 'Task ID is required']);
            break;
        }

        try {
            // Get main task status
            $task = DB::queryFirstRow(
                'SELECT status, is_in_progress, created_at
                 FROM ' . prefixTable('background_tasks') . '
                 WHERE increment_id = %i',
                $taskId
            );

            if (!$task) {
                echo json_encode(['error' => 'Task not found']);
                break;
            }

            // Count total subtasks
            $totalSubtasks = DB::queryFirstField(
                'SELECT COUNT(*)
                 FROM ' . prefixTable('background_subtasks') . '
                 WHERE task_id = %i',
                $taskId
            );

            // Count completed subtasks
            $completedSubtasks = DB::queryFirstField(
                'SELECT COUNT(*)
                 FROM ' . prefixTable('background_subtasks') . '
                 WHERE task_id = %i AND is_in_progress = -1 AND status = "completed"',
                $taskId
            );

            // Count failed subtasks
            $failedSubtasks = DB::queryFirstField(
                'SELECT COUNT(*)
                 FROM ' . prefixTable('background_subtasks') . '
                 WHERE task_id = %i AND status = "failed"',
                $taskId
            );

            // Get error messages if any
            $errorMessage = null;
            if ($failedSubtasks > 0) {
                $errorMessage = DB::queryFirstField(
                    'SELECT error_message
                     FROM ' . prefixTable('background_subtasks') . '
                     WHERE task_id = %i AND status = "failed"
                     ORDER BY increment_id DESC
                     LIMIT 1',
                    $taskId
                );
            }

            // Determine status
            $status = $task['status'];
            if ($failedSubtasks > 0) {
                $status = 'failed';
            } elseif ($completedSubtasks === $totalSubtasks && $totalSubtasks > 0) {
                $status = 'completed';
            } elseif ($task['is_in_progress'] == 1) {
                $status = 'in_progress';
            }

            echo json_encode([
                'status' => $status,
                'total' => (int) $totalSubtasks,
                'completed' => (int) $completedSubtasks,
                'failed' => (int) $failedSubtasks,
                'error_message' => $errorMessage,
                'created_at' => (int) $task['created_at'],
            ]);

        } catch (Exception $e) {
            echo json_encode(['error' => 'Failed to get migration progress: ' . $e->getMessage()]);
        }
        break;

    /**
     * Clear phpseclib v3 migration session variables
     */
    case 'clear_phpseclibv3_migration_session':
        try {
            $session->remove('phpseclibv3_migration_started');
            $session->remove('phpseclibv3_migration_in_progress');
            $session->remove('phpseclibv3_migration_task_id');
            $session->remove('phpseclibv3_migration_total');

            echo json_encode(['status' => 'ok']);

        } catch (Exception $e) {
            echo json_encode(['error' => 'Failed to clear session: ' . $e->getMessage()]);
        }
        break;

    /**
     * Get phpseclib v3 migration statistics (for admin dashboard)
     */
    case 'get_phpseclibv3_migration_stats':
        // Check if user is admin
        if ($session->get('user-admin') != 1) {
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        try {
            // Get stats from encryption_migration_stats table
            $stats = DB::query(
                'SELECT table_name, total_records, v1_records, v3_records, last_update
                 FROM ' . prefixTable('encryption_migration_stats') . '
                 ORDER BY table_name ASC'
            );

            // Calculate totals
            $totalRecords = 0;
            $totalV1 = 0;
            $totalV3 = 0;

            foreach ($stats as $stat) {
                $totalRecords += (int) $stat['total_records'];
                $totalV1 += (int) $stat['v1_records'];
                $totalV3 += (int) $stat['v3_records'];
            }

            $progressPercentage = $totalRecords > 0 ? round(($totalV3 / $totalRecords) * 100, 2) : 0;

            echo json_encode([
                'stats' => $stats,
                'totals' => [
                    'total_records' => $totalRecords,
                    'v1_records' => $totalV1,
                    'v3_records' => $totalV3,
                    'progress_percentage' => $progressPercentage,
                ],
            ]);

        } catch (Exception $e) {
            echo json_encode(['error' => 'Failed to get migration stats: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
