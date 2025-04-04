<?php

trait ItemManagementTrait {


    private function handleNewItem($arguments) {
        // Récupération des sous-tâches liées à cette tâche
        $subtasks = DB::query(
            'SELECT * FROM ' . prefixTable('background_subtasks') . ' WHERE task_id = %i AND is_in_progress = 0',
            $this->taskId
        );
    
        if (empty($subtasks)) {
            error_log("Aucune sous-tâche trouvée pour la tâche {$this->taskId}");
            return;
        }
    
        foreach ($subtasks as $subtask) {
            $this->processSubTask($subtask, $arguments);
        }
    
        // Vérification : toutes les sous-tâches sont-elles complétées ?
        $remainingSubtasks = DB::queryFirstField(
            'SELECT COUNT(*) FROM ' . prefixTable('background_subtasks') . ' WHERE task_id = %i AND is_in_progress = 0',
            $this->taskId
        );
    
        if ($remainingSubtasks == 0) {
            $this->completeTask();
        }
    }

    

    private function handleUpdateItem($arguments) {
        // Logique de mise à jour d'élément
    }


    

    private function handleItemCopy($arguments) {
        // Logique spécifique à la copie d'éléments
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

    
    

    private function handleItemUpdateCreateKeys($arguments) {
        // Création de clés pour mise à jour d'élément
        foreach ($arguments['files_keys'] ?? [] as $file) {
            storeUsersShareKey(
                prefixTable('sharekeys_items'),
                0,
                $file['object_id'],
                $file['object_key'],
                false,
                false,
                [],
                $arguments['all_users_except_id'] ?? -1
            );
        }
    }
}