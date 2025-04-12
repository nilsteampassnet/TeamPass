<?php
/**
 * Teampass - Task Supervisor
 * Supervise and clean up background tasks
 */

use TeampassClasses\ConfigManager\ConfigManager;
require_once __DIR__.'/../includes/config/include.php';
require_once __DIR__.'/../sources/main.functions.php';

class TaskSupervisor {
    private $db;
    private $settings;

    public function __construct() {
        $configManager = new ConfigManager();
        $this->settings = $configManager->getAllSettings();
        $this->db = DB::getInstance();
    }

    public function supervise() {
        $this->cleanStaleProcesses();
        $this->rebalanceTasks();
        $this->logTaskStatistics();
    }

    private function cleanStaleProcesses() {
        // Marquer les processus bloqués depuis plus de 30 minutes
        $this->db->query(
            'UPDATE ' . prefixTable('background_tasks') . ' 
            SET is_in_progress = -1, 
                status = "stale"
            WHERE is_in_progress = 1 
            AND started_at < %i',
            time() - 1800  // 30 minutes
        );
    }

    private function rebalanceTasks() {
        // Relancer les tâches marquées comme "stale"
        $staleTasks = $this->db->query(
            'SELECT increment_id, process_type, arguments 
            FROM ' . prefixTable('background_tasks') . '
            WHERE status = "stale"
            LIMIT 10'  // Limiter à 10 pour éviter une surcharge
        );

        foreach ($staleTasks as $task) {
            $this->db->update(
                prefixTable('background_tasks'),
                [
                    'is_in_progress' => 0,
                    'status' => 'pending',
                    'started_at' => null
                ],
                'increment_id = %i',
                $task['increment_id']
            );
        }
    }

    private function logTaskStatistics() {
        $stats = [
            'total_tasks' => $this->db->queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('background_tasks')
            ),
            'pending_tasks' => $this->db->queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('background_tasks') . ' WHERE is_in_progress = 0'
            ),
            'running_tasks' => $this->db->queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('background_tasks') . ' WHERE is_in_progress = 1'
            ),
            'completed_tasks' => $this->db->queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('background_tasks') . ' WHERE is_in_progress = -1 AND status = "completed"'
            ),
            'failed_tasks' => $this->db->queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('background_tasks') . ' WHERE is_in_progress = -1 AND status = "failed"'
            )
        ];

        // Log ou stocker ces statistiques selon votre configuration
        error_log('Teampass Task Statistics: ' . json_encode($stats));
    }
}

// Exécution du superviseur
$supervisor = new TaskSupervisor();
$supervisor->supervise();