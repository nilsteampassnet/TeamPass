<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

/** Keep the failure-state write isolated from any database. */
final class DB { public static function update(...$arguments): void {} }
/** Use a fictitious table namespace. */
function prefixTable(string $table): string { return 'fixture_' . $table; }
/** Notification transport is outside this retention regression. */
function tpNotifyBackupFailure(...$arguments): void {}
$source = file_get_contents(__DIR__ . '/../../app/scripts/background_tasks___worker.php');
$start = strpos($source, '    private function handleTaskFailure(');
$end = strpos($source, '    /**', $start);
if ($start === false || $end === false) { throw new RuntimeException('Failure handler missing.'); }
$method = substr($source, $start, $end - $start);
eval('class BackupFailedRunFixture {
    public int $taskId = 1;
    public string $processType;
    public array $taskData;
    public object $logger;
    public array $calls = [];
    /** Set only the worker state used by the real failure handler. */
    public function __construct(string $type) {
        $this->processType = $type;
        $this->taskData = ["source" => "scheduler", "output_dir" => sys_get_temp_dir()];
        $this->logger = new class { public function log(...$arguments): void {} };
    }
    /** Observe side effects that would otherwise purge or report through the worker. */
    public function __call(string $name, array $arguments): mixed { $this->calls[] = $name; return 1; }
    /** Exercise the shipped failure handler. */
    public function run(): array { $this->handleTaskFailure(new RuntimeException("Fictitious failure")); return $this->calls; }
' . $method . '}');
$fixture = new BackupFailedRunFixture($argv[1]);
echo json_encode($fixture->run(), JSON_THROW_ON_ERROR);
