<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * @file      backup_file_permissions.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

// Isolate the fake database from the production DB class and other unit tests.
require_once __DIR__ . '/../../app/vendor/autoload.php';
require_once __DIR__ . '/../../app/sources/backup.functions.php';

/** Test database with one small table; no connection or installation settings. */
class DB
{
    public static string $outputDir;
    public static string $failure;
    public static array $plaintextModes = [];

    /** Inspect the dump before the first SQL bytes are written. */
    public static function query(string $query): array
    {
        if ($query === 'SHOW TABLES') {
            self::inspectPlaintextTarget();
        }
        return $query === 'SHOW TABLES' ? [['table' => 'fixture_records']] : [];
    }

    /** Return the fixture table definition or maintenance-mode state. */
    public static function queryFirstRow(string $query, ...$arguments): array
    {
        return str_starts_with($query, 'SHOW CREATE TABLE')
            ? ['Create Table' => 'CREATE TABLE `fixture_records` (`value` TEXT)']
            : ['valeur' => '0'];
    }

    /** Ignore maintenance-mode writes without connecting to a database. */
    public static function update(...$arguments): void
    {
    }

    /** Return an unbuffered-style row walker with one fixture row. */
    public static function queryWalk(string $query): object
    {
        return new class() {
            private bool $done = false;

            /** Return the single fixture row. */
            public function next(): ?array
            {
                if ($this->done) {
                    return null;
                }
                $this->done = true;
                return ['value' => 'backup fixture content'];
            }

            /** No database result needs releasing in this fixture. */
            public function free(): void
            {
            }
        };
    }

    private static function inspectPlaintextTarget(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            self::$outputDir,
            FilesystemIterator::SKIP_DOTS
        ));
        foreach ($iterator as $entry) {
            if (str_ends_with($entry->getFilename(), '.sql.part')) {
                self::$plaintextModes[] = fileperms($entry->getPathname()) & 0777;
                if (self::$failure === 'protection') {
                    $blockedPath = str_starts_with(basename($entry->getPath()), 'tpbackup_tmp_')
                        ? $entry->getPath() . '/payload.encrypted'
                        : substr($entry->getPathname(), 0, -5) . '.encrypted.part';
                    file_put_contents($blockedPath, 'existing target');
                }
            }
        }

    }
}

/** Return the fixture's table prefix. */
function prefixTable(string $table): string
{
    return 'fixture_' . $table;
}

/** Generate a unique filename token without loading application configuration. */
function GenerateCryptKey(...$arguments): string
{
    return bin2hex(random_bytes(10));
}

$encryptedModes = [];

/** Use the same Defuse file writer as production and observe its input protection. */
function prepareFileWithDefuse(string $mode, string $source, string $destination, string $password): bool|string
{
    global $encryptedModes;
    clearstatcache(true, $destination);
    $encryptedModes[] = is_file($destination) ? (fileperms($destination) & 0777) : null;
    if (DB::$failure === 'encrypt') {
        throw new RuntimeException('Fixture encryption failure');
    }
    \Defuse\Crypto\File::encryptFileWithPassword($source, $destination, $password);
    return true;
}

DB::$outputDir = $argv[1];
DB::$failure = $argv[4] ?? '';
$format = $argv[2];
$mask = octdec($argv[3]);
umask($mask);
if ($format === 'metadata') {
    $backup = DB::$outputDir . '/existing.sql';
    $meta = tpGetBackupMetadataPath($backup);
    file_put_contents($meta, '{"comment":"keep"}');
    $write = tpWriteBackupMetadata($backup, 'test', '123');
    $update = tpUpsertBackupMetadata($backup, ['comment' => 'replace']);
    $new = tpWriteBackupMetadata(DB::$outputDir . '/new.sql', 'test', '123');
    echo json_encode([
        'write' => $write,
        'update' => $update,
        'new' => $new,
        'contents' => file_get_contents($meta),
        'new_exists' => file_exists($new['meta_path']),
    ], JSON_THROW_ON_ERROR);
    exit;
}
$results = [];
foreach (['', 'scheduled-', 'externalized-'] as $prefix) {
    $options = ['output_dir' => DB::$outputDir, 'filename_prefix' => $prefix];
    $result = $format === 'tpbackup'
        ? tpCreateBackupPackage([], 'test-only-backup-password', $options)
        : tpCreateDatabaseBackup([], $format === 'plain' ? '' : 'test-only-backup-password', $options);

    if ($result['success']) {
        if ($format !== 'tpbackup') {
            $metadata = tpWriteBackupMetadata($result['filepath'], 'test-version', '123', ['comment' => 'initial']);
            if (!$metadata['success']) {
                throw new RuntimeException($metadata['message']);
            }
        }
        $updated = tpUpsertBackupMetadata($result['filepath'], ['comment' => 'updated']);
        if (!$updated['success']) {
            throw new RuntimeException($updated['message']);
        }

        clearstatcache();
        $result['mode'] = fileperms($result['filepath']) & 0777;
        $result['metadata_mode'] = fileperms(tpGetBackupMetadataPath($result['filepath'])) & 0777;
        $result['metadata'] = tpReadBackupMetadata($result['filepath']);

        $cleartextPath = DB::$outputDir . '/verification.clear';
        if ($format === 'plain') {
            $sql = file_get_contents($result['filepath']);
        } else {
            \Defuse\Crypto\File::decryptFileWithPassword($result['filepath'], $cleartextPath, 'test-only-backup-password');
            if ($format === 'tpbackup') {
                $zip = new ZipArchive();
                if ($zip->open($cleartextPath) !== true) {
                    throw new RuntimeException('Cannot read the decrypted package.');
                }
                $sql = $zip->getFromName('database/dump.sql');
                $zip->close();
            } else {
                $sql = file_get_contents($cleartextPath);
            }
            unlink($cleartextPath);
        }
        $result['roundtrip'] = is_string($sql) && str_contains($sql, 'backup fixture content');
    }
    $results[] = $result;
}

echo json_encode([
    'results' => $results,
    'plaintext_modes' => DB::$plaintextModes,
    'encrypted_modes' => $encryptedModes,
    'umask' => umask(),
], JSON_THROW_ON_ERROR);
