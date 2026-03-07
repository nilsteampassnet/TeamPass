<?php
declare(strict_types=1);

namespace TeampassWebSocket;

/**
 * Simple file-based logger for WebSocket server
 *
 * Provides basic logging functionality with configurable log levels.
 * All logs are written to a single file with timestamps and level indicators.
 */
class Logger
{
    private string $logFile;
    private string $minLevel;

    /**
     * Log level priorities (lower = more verbose)
     */
    private const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
    ];

    /**
     * @param string $logFile Path to the log file
     * @param string $minLevel Minimum level to log (debug, info, warning, error)
     */
    public function __construct(string $logFile, string $minLevel = 'info')
    {
        $this->logFile = $logFile;
        $this->minLevel = $minLevel;

        // Ensure log directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * Log a debug message
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Log an info message
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log a warning message
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Log an error message
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Write a log entry
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private function log(string $level, string $message, array $context): void
    {
        // Check if this level should be logged
        if (!isset(self::LEVELS[$level]) || !isset(self::LEVELS[$this->minLevel])) {
            return;
        }

        if (self::LEVELS[$level] < self::LEVELS[$this->minLevel]) {
            return;
        }

        // Format the log entry
        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';

        $line = "[$timestamp] [$levelUpper] $message$contextStr" . PHP_EOL;

        // Write to file with locking to prevent race conditions
        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get current log level
     */
    public function getLevel(): string
    {
        return $this->minLevel;
    }

    /**
     * Set log level at runtime
     */
    public function setLevel(string $level): void
    {
        if (isset(self::LEVELS[$level])) {
            $this->minLevel = $level;
        }
    }

    /**
     * Get the log file path
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }
}
