<?php

declare(strict_types=1);

/**
 * TeamPass Composer development-dependency cleanup CLI.
 *
 * This deterministic offline cleanup uses composer.lock and the same shared
 * logic as install/upgrade. It does not require Composer, network access, or a
 * writable application tree for the PHP/web account when invoked with sudo.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root = realpath(__DIR__ . '/../..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve the TeamPass root.\n");
    exit(1);
}

require_once $root . '/app/scripts/dev_dependencies_cleanup_logic.php';

$options = getopt('', ['help']);
if (isset($options['help'])) {
    echo "Usage:\n";
    echo "  sudo php app/scripts/cleanup_dev_dependencies.php\n";
    exit(0);
}

try {
    $result = devDependenciesCleanupRun($root);
    echo 'Development packages removed: ' . strval($result['packages_removed']) . PHP_EOL;
    echo 'Development launchers removed: ' . strval($result['binaries_removed']) . PHP_EOL;
    echo 'Paths skipped: ' . strval($result['skipped']) . PHP_EOL;
    exit($result['skipped'] === 0 ? 0 : 2);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Development dependency cleanup failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
