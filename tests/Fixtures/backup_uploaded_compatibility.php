<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

require_once __DIR__ . '/../../app/sources/backup.functions.php';
/** Supply the current fixture release without installation settings. */
function tpExpectedTpFilesVersion(): string { return '3.2.4.3'; }
/** Supply the fixture schema boundary. */
function tpCurrentSchemaLevel(): string { return '100'; }
/** Use a fictitious SQL namespace. */
function prefixTable(string $table): string { return 'fixture_' . $table; }
/** Return only the upload bookkeeping consumed by the real compatibility function. */
final class DB
{
    /** Resolve the fictitious upload to the temporary test archive. */
    public static function queryFirstRow(...$args): array { return ['valeur' => 'upload-sl100.sql']; }
}
$source = file_get_contents(__DIR__ . '/../../app/sources/backups.queries.php');
$start = strpos($source, 'function tpCheckRestoreCompatibility(');
$end = strpos($source, '    switch ($post_type)', $start);
if ($start === false || $end === false) {
    throw new RuntimeException('Compatibility handler missing.');
}
eval(substr($source, $start, $end - $start));
echo json_encode(tpCheckRestoreCompatibility(['path_to_files_folder' => $argv[1]], '', '', 1), JSON_THROW_ON_ERROR);
