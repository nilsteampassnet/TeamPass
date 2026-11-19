<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

define('LOG_TASKS', false);
$source = file_get_contents(__DIR__ . '/../../app/scripts/background_tasks___worker.php');
$start = strpos($source, '        // Store a tiny summary');
$end = strpos($source, '    /**', $start);
if ($start === false || $end === false) {
    throw new RuntimeException('Backup completion handler not found.');
}
$body = substr($source, $start, $end - $start);
// Execute the shipped post-capture control flow, replacing only its external services.
eval('final class BackupRetentionDeliveryFixture {
    public array $taskData = [];
    public array $calls = [];
    /** Observe delivery and simulate an undeletable old archive. */
    public function __call(string $name, array $args): mixed {
        $this->calls[] = $name;
        if ($name === "purgeOldScheduledBackups") { throw new RuntimeException("retention refused"); }
        if ($name === "getMiscSetting") { return $args[1]; }
        return true;
    }
    /** Run the actual completion sequence for a successfully captured archive. */
    public function run(): void {
        $res = ["filename" => "scheduled-fixture.sql", "size_bytes" => 1, "encrypted" => true];
        $backupFormat = "sql";
        $includeDocuments = false;
        $targetDir = sys_get_temp_dir();
        $taskData = ["source" => "scheduler"];
' . $body . '}');
$fixture = new BackupRetentionDeliveryFixture();
try {
    $fixture->run();
    throw new RuntimeException('Expected retention failure was swallowed.');
} catch (RuntimeException $exception) {
    echo json_encode(['calls' => $fixture->calls, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR);
}
