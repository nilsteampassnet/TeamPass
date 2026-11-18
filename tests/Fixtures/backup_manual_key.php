<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

$post_key = 'session-key';
$post_data = base64_decode($argv[1], true);
$session = new class {
    /** Return the authenticated fixture administrator. */
    public function get(string $key): string|int { return $key === 'key' ? 'session-key' : 1; }
};
$lang = new class {
    /** Keep the refusal key visible to the test. */
    public function get(string $key): string { return $key; }
};
/** Exercise the real request validation without loading configuration or connecting to SQL. */
function prepareExchangedData(string|array $data, string $mode): string|array
{
    return $mode === 'decode' ? json_decode($data, true, 512, JSON_THROW_ON_ERROR) : json_encode($data, JSON_THROW_ON_ERROR);
}
$source = file_get_contents(__DIR__ . '/../../app/sources/backups.queries.php');
$start = strpos($source, "case 'onthefly_backup':");
$end = strpos($source, '            $includeDocuments =', $start);
if ($start === false || $end === false) {
    throw new RuntimeException('Manual backup validation not found.');
}
eval("switch ('onthefly_backup') {" . substr($source, $start, $end - $start)
    . 'echo json_encode(["error" => false, "key" => $encryptionKey]); break; }');
