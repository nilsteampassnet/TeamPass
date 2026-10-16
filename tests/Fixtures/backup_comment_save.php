<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * @file      backup_comment_save.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

require_once __DIR__ . '/../../app/sources/backup.functions.php';

$directory = $argv[1];
$scenario = $argv[2];
$backup = $directory . '/fixture.sql';
file_put_contents($backup, 'encrypted fixture');
if ($scenario === 'refused') {
    mkdir(tpGetBackupMetadataPath($backup), 0700);
}
$post_key = $scenario === 'bad_key' ? 'wrong' : 'fixture-key';
$post_data = json_encode(['file' => basename($backup), 'comment' => 'Updated comment'], JSON_THROW_ON_ERROR);
$session = new class($scenario) {
    public function __construct(private string $scenario) {}

    /** Return only the session fields used by the extracted handler. */
    public function get(string $key): mixed
    {
        return $key === 'key' ? 'fixture-key' : ($this->scenario === 'non_admin' ? 0 : 1);
    }
};
$lang = new class() {
    /** Keep language keys visible in the returned JSON for assertions. */
    public function get(string $key): string { return $key; }
};

/** Preserve the handler's JSON exchange contract without session encryption. */
function prepareExchangedData(string|array $data, string $mode): string|array
{
    return $mode === 'decode' ? json_decode($data, true, 512, JSON_THROW_ON_ERROR) : json_encode($data, JSON_THROW_ON_ERROR);
}

/** Resolve only the isolated fixture backup directory. */
function tpGetOntheflyBackupDir(bool $create): string
{
    global $directory;
    return $directory;
}

// Run the real controller case, including its permission/key checks and response.
$source = file_get_contents(__DIR__ . '/../../app/sources/backups.queries.php');
$start = strpos($source, "case 'onthefly_update_comment':");
$end = strpos($source, "case 'onthefly_check_upload_filename':", $start);
if ($start === false || $end === false) {
    throw new RuntimeException('Cannot locate the backup comment handler.');
}
eval("switch ('onthefly_update_comment') {" . substr($source, $start, $end - $start) . '}');
