<?php

class TaskLogger {
    private $settings;
    private $logFile;

    public function __construct(array $settings, string $logFile = '') {
        $this->settings = $settings;
        $this->logFile = $logFile;
    }

    public function log(string $message, string $level = 'INFO') {
        if (!empty($this->settings['enable_tasks_log'])) {
            $formattedMessage = date($this->settings['date_format'] . ' ' . $this->settings['time_format']) . 
                                " - [$level] $message" . PHP_EOL;

            if (!empty($this->logFile)) {
                // Écrire dans un fichier spécifique
                file_put_contents($this->logFile, $formattedMessage, FILE_APPEND | LOCK_EX);
            } else {
                // Utiliser error_log par défaut
                error_log($formattedMessage);
            }
        }
    }
}