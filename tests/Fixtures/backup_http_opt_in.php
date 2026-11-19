<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

$storedOptIn = 0;
/** Supply only the requested transport setting, with all other settings at defaults. */
function tpGetSettingsValue(string $name, string $default): string
{
    return $name === 'bck_externalized_allow_http' ? (string) $GLOBALS['storedOptIn'] : $default;
}
/** Never load installed credentials in this isolated round-trip test. */
function tpExternalizedDecryptSecretSetting(string $name, array $settings): string
{
    return 'fictitious-secret';
}
$source = file_get_contents(__DIR__ . '/../../app/sources/backups.queries.php');
$start = strpos($source, '    function tpExternalizedGetWebdavConfig(');
$end = strpos($source, '    function tpExternalizedGetDestinationConfig(', $start);
if ($start === false || $end === false) {
    throw new RuntimeException('Transport settings helpers missing.');
}
eval(substr($source, $start, $end - $start));
$results = [];
foreach ([0, 1, 0] as $optIn) {
    $webdav = tpExternalizedGetWebdavConfigFromRequest(['allow_http' => $optIn], []);
    $s3 = tpExternalizedGetS3ConfigFromRequest(['allow_http' => $optIn], []);
    $storedOptIn = $optIn;
    $results[] = [$webdav['allow_http'], $s3['allow_http'], tpExternalizedGetWebdavPublicSettings([]), tpExternalizedGetS3PublicSettings([])];
}
echo json_encode($results, JSON_THROW_ON_ERROR);
