<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * @file      RuntimeFilesTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

require_once __DIR__ . '/../../app/sources/runtime_files.functions.php';
require_once __DIR__ . '/../../app/sources/file_integrity.functions.php';

class RuntimeFilesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'teampass-runtime-files-test-' . bin2hex(random_bytes(8));
        foreach (['storage/logs', 'storage/files', 'app/includes/libraries/csrfp/log'] as $directory) {
            self::assertTrue(mkdir($this->root . '/' . $directory, 0700, true));
        }
    }

    protected function tearDown(): void
    {
        $root = realpath($this->root);
        $temporaryRoot = realpath(sys_get_temp_dir());
        if (
            $root === false
            || $temporaryRoot === false
            || str_starts_with($root, $temporaryRoot . DIRECTORY_SEPARATOR . 'teampass-runtime-files-test-') === false
        ) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isLink() === false && $entry->isDir()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($root);
    }

    /** @return array<string,array{int,int}> */
    public static function creationMasks(): array
    {
        return [
            'no mask' => [0000, 0640],
            'group-writable default' => [0002, 0640],
            'usual web default' => [0022, 0640],
            'restricted default' => [0027, 0640],
            'private default' => [0077, 0600],
        ];
    }

    /** Verify recreated locks and signals stay private under common deployment umasks. */
    #[DataProvider('creationMasks')]
    public function testCreationAndRecreationRestrictPermissionsWithoutChangingUmask(int $mask, int $expectedMode): void
    {
        $this->requirePosix();
        $previousMask = umask($mask);
        try {
            $path = $this->root . '/storage/logs/teampass_background_tasks.lock';
            for ($run = 0; $run < 2; $run++) {
                $handle = tpOpenRuntimeFile($path);
                self::assertIsResource($handle);
                try {
                    self::assertSame($expectedMode, fstat($handle)['mode'] & 0777);
                    self::assertSame($mask, umask());
                    self::assertSame('', stream_get_contents($handle));
                } finally {
                    fclose($handle);
                }
                self::assertTrue(unlink($path));
            }

            $signal = $this->root . '/storage/logs/teampass_background_tasks.trigger';
            for ($run = 0; $run < 2; $run++) {
                self::assertTrue(tpWriteRuntimeFile($signal, '1788806401'));
                clearstatcache(true, $signal);
                self::assertSame($expectedMode, fileperms($signal) & 0777);
                self::assertSame($mask, umask());
                self::assertTrue(unlink($signal));
            }
        } finally {
            umask($previousMask);
        }
    }

    /** @return array<string,array{int,int}> */
    public static function existingModes(): array
    {
        return [
            'old lock' => [0644, 0640],
            'world-writable lock' => [0666, 0640],
            'already restricted' => [0640, 0640],
            'already private' => [0600, 0600],
        ];
    }

    /** Restrict old files in place without broadening group/other access or damaging a PID. */
    #[DataProvider('existingModes')]
    public function testExistingFilesAreRestrictedWithoutTruncatingOrReplacingThem(int $mode, int $expectedMode): void
    {
        $this->requirePosix();
        $path = $this->root . '/storage/logs/existing.lock';
        self::assertSame(5, file_put_contents($path, '12345'));
        self::assertTrue(chmod($path, $mode));
        clearstatcache(true, $path);
        $inode = fileinode($path);

        $handle = tpOpenRuntimeFile($path);
        self::assertIsResource($handle);
        try {
            self::assertSame($expectedMode, fstat($handle)['mode'] & 0777);
            self::assertSame($inode, fstat($handle)['ino']);
            self::assertSame('12345', stream_get_contents($handle));
        } finally {
            fclose($handle);
        }
    }

    /** Check complete signal replacement and lock release after a successful write. */
    public function testSignalWriterTruncatesOldContentsAndReleasesItsLock(): void
    {
        $path = $this->root . '/storage/logs/teampass_background_tasks.trigger';
        self::assertTrue(tpWriteRuntimeFile($path, 'a previous longer timestamp'));
        self::assertTrue(tpWriteRuntimeFile($path, '12345'));
        self::assertSame('12345', file_get_contents($path));
        $handle = tpOpenRuntimeFile($path);
        self::assertIsResource($handle);
        try {
            self::assertTrue(flock($handle, LOCK_EX | LOCK_NB));
        } finally {
            fclose($handle);
        }
    }

    /** A chmod-only failure must not stop a writable lock or signal. */
    public function testPermissionRepairFailureIsLoggedWithoutStoppingWrites(): void
    {
        $this->requirePosix();
        $path = $this->root . '/storage/logs/teampass_background_tasks.lock';
        self::assertSame(5, file_put_contents($path, '12345'));
        self::assertTrue(chmod($path, 0664));
        $inode = fileinode($path);

        // PHP 8 permits redefining a disabled built-in. Isolate the simulated
        // chmod denial in a child; do not require sudo or change any account.
        $probe = $this->runRuntimeProbe($path, <<<'PHP'
if (function_exists('chmod')) {
    throw new RuntimeException('The chmod denial fixture was not enabled.');
} else {
    function chmod(string $filename, int $permissions): bool { return false; }
}
$handle = tpOpenRuntimeFile($path);
$opened = is_resource($handle);
$contents = $opened ? stream_get_contents($handle) : null;
if ($opened) { fclose($handle); }
$written = tpWriteRuntimeFile($path, 'signal');
echo json_encode(['opened' => $opened, 'contents' => $contents, 'written' => $written]);
PHP, ['-d', 'disable_functions=chmod']);

        self::assertSame(['opened' => true, 'contents' => '12345', 'written' => true], json_decode($probe->getOutput(), true));
        self::assertStringContainsString('Continuing with existing access', $probe->getErrorOutput());
        clearstatcache(true, $path);
        self::assertSame(0664, fileperms($path) & 0777);
        self::assertSame($inode, fileinode($path));
        self::assertSame('signal', file_get_contents($path));

        $report = tpFilePermissionsScan($this->root);
        $warnings = array_values(array_filter(
            $report['issues'],
            static fn (array $issue): bool => $issue['reason'] === 'runtime_world_accessible'
        ));
        self::assertCount(1, $warnings);
        self::assertSame('storage/logs/teampass_background_tasks.lock', $warnings[0]['path']);
    }

    /** A path replacement is not a recoverable chmod-only failure. */
    public function testTargetReplacedDuringPermissionRepairIsRejected(): void
    {
        $this->requirePosix();
        $path = $this->root . '/storage/logs/replaced.lock';
        self::assertSame(5, file_put_contents($path, '12345'));
        self::assertTrue(chmod($path, 0644));
        $probe = $this->runRuntimeProbe($path, <<<'PHP'
if (function_exists('chmod')) {
    throw new RuntimeException('The replacement fixture was not enabled.');
} else {
    function chmod(string $filename, int $permissions): bool {
        rename($filename, $filename . '.original');
        file_put_contents($filename, 'replacement');
        return false;
    }
}
echo json_encode(['rejected' => tpWriteRuntimeFile($path, 'must not be written') === false]);
PHP, ['-d', 'disable_functions=chmod']);
        self::assertSame(['rejected' => true], json_decode($probe->getOutput(), true));
        self::assertSame('12345', file_get_contents($path . '.original'));
        self::assertSame('replacement', file_get_contents($path));
    }

    /** Match descriptor identity, not merely the existence of a regular path. */
    public function testPathIdentityRejectsReplacementsAndMissingPaths(): void
    {
        $this->requirePosix();
        $path = $this->root . '/storage/logs/identity.lock';
        $handle = tpOpenRuntimeFile($path);
        self::assertIsResource($handle);
        try {
            $stat = fstat($handle);
            self::assertIsArray($stat);
            self::assertTrue(tpRuntimeFileMatchesPath($path, $stat));
            self::assertTrue(rename($path, $path . '.original'));
            self::assertFalse(tpRuntimeFileMatchesPath($path, $stat));
            self::assertSame(11, file_put_contents($path, 'replacement'));
            self::assertFalse(tpRuntimeFileMatchesPath($path, $stat));
            self::assertTrue(unlink($path));
            self::assertTrue(mkdir($path));
            self::assertFalse(tpRuntimeFileMatchesPath($path, $stat));
        } finally {
            fclose($handle);
        }
    }

    /** Bound the child runtime so a blocking-lock regression cannot hang the suite. */
    public function testContendingSignalWriterDoesNotWaitOrTruncate(): void
    {
        $this->requirePosix();
        $path = $this->root . '/storage/logs/busy.trigger';
        $holder = tpOpenRuntimeFile($path);
        self::assertIsResource($holder);
        try {
            self::assertTrue(flock($holder, LOCK_EX | LOCK_NB));
            self::assertSame(5, fwrite($holder, '12345'));
            self::assertTrue(fflush($holder));
            $probe = $this->runRuntimeProbe($path, <<<'PHP'
$wouldBlock = false;
$written = tpWriteRuntimeFile($path, 'replacement', $wouldBlock);
echo json_encode(['written' => $written, 'would_block' => $wouldBlock]);
PHP);
            self::assertSame(['written' => false, 'would_block' => true], json_decode($probe->getOutput(), true));
            self::assertTrue(rewind($holder));
            self::assertSame('12345', stream_get_contents($holder));
        } finally {
            fclose($holder);
        }
        $wouldBlock = true;
        self::assertTrue(tpWriteRuntimeFile($path, 'next', $wouldBlock));
        self::assertFalse($wouldBlock);
    }

    /** The status probe needs read access only and never repairs permissions. */
    public function testStatusProbeCanReadAnUnwritableLock(): void
    {
        $this->requirePosix();
        $path = tpFileIntegrityLockPath($this->root);
        $holder = tpOpenRuntimeFile($path);
        self::assertIsResource($holder);
        try {
            self::assertTrue(flock($holder, LOCK_EX | LOCK_NB));
            self::assertSame(5, fwrite($holder, '12345'));
            self::assertTrue(fflush($holder));
            self::assertTrue(chmod($path, 0400));
            self::assertTrue(tpFileIntegrityIsRunning($this->root));
            clearstatcache(true, $path);
            self::assertSame(0400, fileperms($path) & 0777);
        } finally {
            fclose($holder);
        }
        self::assertFalse(tpFileIntegrityIsRunning($this->root));
        self::assertSame('12345', file_get_contents($path));
        clearstatcache(true, $path);
        self::assertSame(0400, fileperms($path) & 0777);
    }

    /** Reading the status of an installation without a scan never creates a lock. */
    public function testStatusOfMissingScanLockDoesNotCreateIt(): void
    {
        $path = tpFileIntegrityLockPath($this->root);
        $probeFailed = true;
        self::assertFalse(tpFileIntegrityIsRunning($this->root, $probeFailed));
        self::assertFalse($probeFailed);
        self::assertFileDoesNotExist($path);
    }

    /** A completed scan on NFS must not look busy merely because its lock file remains. */
    public function testCompletedScanIsIdleWithNfsLockSemantics(): void
    {
        $path = tpFileIntegrityLockPath($this->root);
        $probe = $this->runRuntimeProbe($path, <<<'PHP'
if (function_exists('flock')) {
    throw new RuntimeException('The NFS locking fixture was not enabled.');
} else {
    function flock($stream, int $operation, &$wouldBlock = null): bool {
        $wouldBlock = 0;
        $mode = stream_get_meta_data($stream)['mode'];
        // Linux emulates flock over NFS with fcntl: exclusive locks need write access.
        if (($operation & ~LOCK_NB) === LOCK_EX && strpbrk($mode, '+wac') === false) {
            return false;
        }
        return true;
    }
}
$root = dirname(dirname(dirname($path)));
$reference = $root . '/app/files_reference.txt';
file_put_contents($reference, 'app/known.txt ' . md5('known') . "\n");
file_put_contents($root . '/app/known.txt', 'known');
$report = tpFileIntegrityScan($root, $reference, true, false);
tpFileIntegritySaveReport($root, $report);
$before = file_get_contents($path);
$summary = tpFileIntegrityLoadSummary($root);
echo json_encode([
    'has_result' => $summary['has_result'],
    'running' => $summary['running'],
    'status' => $summary['status'],
    'lock_probe_failed' => $summary['lock_probe_failed'] ?? null,
    'lock_present' => is_file($path),
    'lock_unchanged' => $before !== '' && file_get_contents($path) === $before,
]);
PHP, ['-d', 'disable_functions=flock']);
        self::assertSame([
            'has_result' => true,
            'running' => false,
            'status' => 'success',
            'lock_probe_failed' => false,
            'lock_present' => true,
            'lock_unchanged' => true,
        ], json_decode($probe->getOutput(), true));
        self::assertSame('', $probe->getErrorOutput());
    }

    /** A failed lock operation is an error, not evidence of an active scanner. */
    public function testStatusReportsLockFailureWithoutPretendingAScanIsRunning(): void
    {
        $path = tpFileIntegrityLockPath($this->root);
        self::assertSame(5, file_put_contents($path, '12345'));
        $probe = $this->runRuntimeProbe($path, <<<'PHP'
if (function_exists('flock')) {
    throw new RuntimeException('The lock failure fixture was not enabled.');
} else {
    function flock($stream, int $operation, &$wouldBlock = null): bool {
        $wouldBlock = 0;
        // Fail only the scan-lock probe; the persisted summary remains readable.
        return stream_get_meta_data($stream)['uri'] !== $GLOBALS['path'];
    }
}
$probeFailed = false;
$root = dirname(dirname(dirname($path)));
$reference = $root . '/app/files_reference.txt';
file_put_contents($reference, '');
$report = tpFileIntegrityDefaultReport();
$report['has_result'] = true;
$report['status'] = 'success';
$report['reference_hash'] = hash_file('sha256', $reference);
file_put_contents(tpFileIntegritySummaryPath($root), json_encode(tpFileIntegritySummary($report)));
$running = tpFileIntegrityIsRunning($root, $probeFailed);
$summary = tpFileIntegrityLoadSummary($root);
echo json_encode([
    'running' => $running,
    'probe_failed' => $probeFailed,
    'summary_running' => $summary['running'],
    'has_result' => $summary['has_result'],
    'status' => $summary['status'],
    'lock_probe_failed' => $summary['lock_probe_failed'] ?? null,
    'last_error' => $summary['last_error'] ?? '',
]);
PHP, ['-d', 'disable_functions=flock']);
        self::assertSame([
            'running' => false,
            'probe_failed' => true,
            'summary_running' => false,
            'has_result' => true,
            'status' => 'error',
            'lock_probe_failed' => true,
            'last_error' => '',
        ], json_decode($probe->getOutput(), true));
        self::assertSame('12345', file_get_contents($path));
    }

    /** Runtime flags must be recomputed without replacing saved scanner diagnostics. */
    public function testRuntimeStateClearsPreviousLockProbeFailure(): void
    {
        self::assertSame(0, file_put_contents($this->root . '/app/files_reference.txt', ''));
        $payload = tpFileIntegrityDefaultReport();
        $payload['lock_probe_failed'] = true;
        $payload['status'] = 'error';
        $payload['last_error'] = 'Saved scanner diagnostic';

        $summary = tpFileIntegrityApplyRuntimeState($this->root, $payload);

        self::assertFalse($summary['lock_probe_failed']);
        self::assertFalse($summary['running']);
        self::assertSame('error', $summary['status']);
        self::assertSame('Saved scanner diagnostic', $summary['last_error']);
    }

    /** Check real contention across processes without waiting or changing the worker's PID. */
    public function testStatusDetectsAnotherProcessHoldingTheScanLock(): void
    {
        $path = tpFileIntegrityLockPath($this->root);
        $holder = tpOpenRuntimeFile($path);
        self::assertIsResource($holder);
        try {
            self::assertTrue(flock($holder, LOCK_EX | LOCK_NB));
            self::assertSame(5, fwrite($holder, '12345'));
            self::assertTrue(fflush($holder));
            $probe = $this->runRuntimeProbe($path, <<<'PHP'
$probeFailed = true;
$running = tpFileIntegrityIsRunning(dirname(dirname(dirname($path))), $probeFailed);
echo json_encode(['running' => $running, 'probe_failed' => $probeFailed]);
PHP);
            self::assertSame(['running' => true, 'probe_failed' => false], json_decode($probe->getOutput(), true));
            rewind($holder);
            self::assertSame('12345', stream_get_contents($holder));
        } finally {
            fclose($holder);
        }
        $probeFailed = true;
        self::assertFalse(tpFileIntegrityIsRunning($this->root, $probeFailed));
        self::assertFalse($probeFailed);
        self::assertFileExists($path);
    }

    /** Simultaneous status readers are compatible and must not look like scanners. */
    public function testStatusReadersDoNotReportEachOtherAsRunningScans(): void
    {
        $path = tpFileIntegrityLockPath($this->root);
        self::assertSame(5, file_put_contents($path, '12345'));
        $reader = fopen($path, 'rb');
        self::assertIsResource($reader);
        try {
            self::assertTrue(flock($reader, LOCK_SH | LOCK_NB));
            $probeFailed = true;
            self::assertFalse(tpFileIntegrityIsRunning($this->root, $probeFailed));
            self::assertFalse($probeFailed);
            $writer = tpOpenRuntimeFile($path);
            self::assertIsResource($writer);
            try {
                // Closing a probe must leave the original reader's lock intact.
                self::assertFalse(flock($writer, LOCK_EX | LOCK_NB));
            } finally {
                fclose($writer);
            }
        } finally {
            fclose($reader);
        }
        self::assertSame('12345', file_get_contents($path));
    }

    /** The log appender follows the same policy and never rewrites history. */
    public function testAppendPreservesStrictModesAndExistingContents(): void
    {
        $this->requirePosix();
        $path = $this->root . '/storage/logs/teampass_tasks.log';
        self::assertSame(9, file_put_contents($path, "line one\n"));
        self::assertTrue(chmod($path, 0600));
        $inode = fileinode($path);

        $previousMask = umask(0022);
        try {
            self::assertTrue(tpAppendRuntimeFile($path, "line two\n"));
        } finally {
            umask($previousMask);
        }

        clearstatcache(true, $path);
        self::assertSame(0600, fileperms($path) & 0777, 'A stricter deployment mode must survive.');
        self::assertSame($inode, fileinode($path));
        self::assertSame("line one\nline two\n", file_get_contents($path));
    }

    /** The appender inherits every target rejection from the opener. */
    public function testAppendRejectsInvalidTargets(): void
    {
        self::assertFalse(tpAppendRuntimeFile($this->root . '/missing/teampass_tasks.log', 'x'));
        self::assertFalse(tpAppendRuntimeFile($this->root . '/storage/logs', 'x'));
        self::assertFalse(tpAppendRuntimeFile('php://memory', 'x'));
    }

    /** Reject unsupported targets without leaving files or a changed process umask. */
    public function testInvalidTargetsFailWithoutChangingUmaskOrDirectoryContents(): void
    {
        $mask = umask();
        self::assertFalse(tpOpenRuntimeFile($this->root . '/missing/scan.lock'));
        self::assertFalse(tpWriteRuntimeFile($this->root . '/missing/task.trigger', '123'));
        self::assertFalse(tpOpenRuntimeFile($this->root . '/storage/logs'));
        self::assertFalse(tpWriteRuntimeFile('php://memory', '123'));
        $wouldBlock = true;
        self::assertFalse(tpWriteRuntimeFile($this->root . '/missing/task.trigger', '123', $wouldBlock));
        self::assertFalse($wouldBlock);
        self::assertSame($mask, umask());
        self::assertSame(['.', '..'], scandir($this->root . '/storage/logs'));
    }

    /** Refuse to follow a lock path that points at another file. */
    public function testSymbolicLinkTargetIsNotWrittenOrChmodded(): void
    {
        $target = $this->root . '/storage/logs/target.txt';
        $link = $this->root . '/storage/logs/linked.lock';
        self::assertSame(9, file_put_contents($target, 'untouched'));
        if (@symlink($target, $link) === false) {
            self::markTestSkipped('Symbolic links are not available in this environment.');
        }
        $mode = fileperms($target);
        self::assertFalse(tpOpenRuntimeFile($link));
        self::assertFalse(tpWriteRuntimeFile($link, '123'));
        self::assertTrue(symlink($target, tpFileIntegrityLockPath($this->root)));
        self::assertFalse(tpFileIntegrityIsRunning($this->root));
        self::assertSame('untouched', file_get_contents($target));
        clearstatcache(true, $target);
        self::assertSame($mode, fileperms($target));
    }

    /** Keep the active scan's PID intact when another scan competes for its lock. */
    public function testAnotherScanCannotAcquireOrTruncateAnActiveLock(): void
    {
        $path = tpFileIntegrityLockPath($this->root);
        $holder = tpOpenRuntimeFile($path);
        self::assertIsResource($holder);
        try {
            self::assertTrue(flock($holder, LOCK_EX | LOCK_NB));
            self::assertSame(5, fwrite($holder, '12345'));
            self::assertTrue(fflush($holder));
            self::assertTrue(tpFileIntegrityIsRunning($this->root));
            try {
                tpFileIntegrityScan($this->root, $this->root . '/app/files_reference.txt', true, false);
                self::fail('A competing scan must not acquire the active lock.');
            } catch (RuntimeException $exception) {
                self::assertSame('A file integrity scan is already running.', $exception->getMessage());
            }
            rewind($holder);
            self::assertSame('12345', stream_get_contents($holder));
        } finally {
            fclose($holder);
        }
        self::assertFalse(tpFileIntegrityIsRunning($this->root));
    }

    /** Exercise the real scanner and its non-creating status probe. */
    public function testStatusDoesNotCreateMissingLockAndScanUsesRestrictedPermissions(): void
    {
        $this->requirePosix();
        $path = tpFileIntegrityLockPath($this->root);
        self::assertFalse(tpFileIntegrityIsRunning($this->root));
        self::assertFileDoesNotExist($path);
        self::assertSame(5, file_put_contents($this->root . '/app/known.txt', 'known'));
        $reference = $this->root . '/app/files_reference.txt';
        self::assertNotFalse(file_put_contents($reference, 'app/known.txt ' . md5('known') . "\n"));

        $mask = umask(0022);
        try {
            $report = tpFileIntegrityScan($this->root, $reference, true, false);
            self::assertSame('success', $report['status']);
            self::assertSame(0, $report['counts']['unknown']);
            clearstatcache(true, $path);
            self::assertSame(0640, fileperms($path) & 0777);
            self::assertFalse(tpFileIntegrityIsRunning($this->root));
            self::assertSame(0022, umask());
        } finally {
            umask($mask);
        }
    }

    /** Preserve permission auditing for unmanaged, unsafe runtime files. */
    public function testPermissionScanStillReportsUnsafeRuntimeFiles(): void
    {
        $this->requirePosix();
        $paths = [
            $this->root . '/storage/logs/teampass_background_tasks.lock',
            $this->root . '/storage/logs/teampass_background_tasks.trigger',
            tpFileIntegrityLockPath($this->root),
            tpFileIntegrityEnqueueLockPath($this->root),
        ];
        foreach ($paths as $path) {
            self::assertNotFalse(file_put_contents($path, '12345'));
            self::assertTrue(chmod($path, 0644));
            $handle = tpOpenRuntimeFile($path);
            self::assertIsResource($handle);
            fclose($handle);
        }
        $unsafe = $this->root . '/storage/logs/unsafe.lock';
        self::assertNotFalse(file_put_contents($unsafe, 'not managed by the helper'));
        self::assertTrue(chmod($unsafe, 0666));
        clearstatcache();

        $platform = tpFilePermissionsDetectPlatform("ID=ubuntu\n", 'Linux');
        $report = tpFilePermissionsScan($this->root, $platform, [
            'web_user' => 'test-web', 'web_group' => 'test-web', 'source' => 'test',
            'uid' => posix_geteuid(), 'gids' => [posix_getegid()],
        ]);
        $worldWritable = array_values(array_filter(
            $report['issues'],
            static fn (array $issue): bool => $issue['reason'] === 'world_writable'
        ));
        $worldReadable = array_values(array_filter(
            $report['issues'],
            static fn (array $issue): bool => $issue['reason'] === 'runtime_world_accessible'
        ));
        self::assertCount(1, $worldWritable);
        self::assertSame('storage/logs/unsafe.lock', $worldWritable[0]['path']);
        self::assertSame([], $worldReadable);
    }

    /** @param list<string> $phpArguments */
    private function runRuntimeProbe(string $path, string $code, array $phpArguments = []): Process
    {
        $bootstrap = 'require $argv[1]; $path = $argv[2];' . "\n";
        $process = new Process(array_merge(
            [PHP_BINARY],
            $phpArguments,
            ['-r', $bootstrap . $code, '--', __DIR__ . '/../../app/sources/file_integrity.functions.php', $path]
        ));
        $process->setTimeout(5);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        return $process;
    }

    private function requirePosix(): void
    {
        if (PHP_OS_FAMILY !== 'Linux' || function_exists('posix_geteuid') === false) {
            self::markTestSkipped('Linux/POSIX permission semantics are required.');
        }
    }
}
