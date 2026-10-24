<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery_http.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

require dirname(__DIR__, 2) . '/app/vendor/autoload.php';
$root = dirname(__DIR__, 2);
if (!extension_loaded('curl') || is_file($root . '/app/config/settings.php')) {
    fwrite(STDERR, "Requires cURL and a fresh disposable checkout.\n");
    exit(2);
}
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
$address = stream_socket_get_name($socket, false);
fclose($socket);
$server = new \Symfony\Component\Process\Process([PHP_BINARY, '-S', $address, '-t', 'public'], $root);
$server->start();
$enable = new \Symfony\Component\Process\Process([PHP_BINARY, 'app/scripts/disaster-recovery.php', '--enable'], $root);
$handle = curl_init();
curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 10]);
$request = static function (array $post = [], string $language = 'french') use ($handle, $address): array {
    curl_setopt($handle, CURLOPT_URL, 'http://' . $address . '/install/recovery.php?lang=' . $language);
    curl_setopt($handle, CURLOPT_POST, $post !== []);
    if ($post !== []) {
        curl_setopt($handle, CURLOPT_POSTFIELDS, $post);
    }
    $body = curl_exec($handle);
    if (!is_string($body)) {
        throw new RuntimeException('Recovery HTTP request failed');
    }
    return [(int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $body];
};
$exitCode = 0;
try {
    for ($retry = 0; $retry < 100; $retry++) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if (is_resource($probe)) {
            fclose($probe);
            break;
        }
        usleep(20000);
    }
    $enable->run();
    if (!$enable->isSuccessful() || preg_match('/([a-f0-9]{64})\s*$/', $enable->getOutput(), $match) !== 1) {
        throw new RuntimeException('Could not enable recovery form');
    }
    $operator = $match[1];
    // Emulate entry through install.php, which uses a plain PHPSESSID session.
    curl_setopt($handle, CURLOPT_COOKIELIST, "127.0.0.1\tFALSE\t/\tFALSE\t0\tPHPSESSID\tplain-installer-session");
    [$status, $html] = $request();
    $cookies = curl_getinfo($handle, CURLINFO_COOKIELIST);
    if (!is_array($cookies) || array_filter($cookies, static fn (string $cookie): bool => str_contains($cookie, "\tPHPSESSID\t"))) {
        throw new RuntimeException('A fresh recovery must retire the plain installer cookie before encrypted application sign-in');
    }
    if ($status !== 200 || !str_contains($html, 'Restore a TeamPass instance') || !str_contains($html, '<html lang="en">')
        || str_contains($html, 'href="?lang=')) {
        throw new RuntimeException('Recovery must remain in English without a language selector, including with a legacy lang=french URL');
    }
    if (PHP_OS_FAMILY !== 'Windows' && (!str_contains($html, 'sudo -u') || !str_contains($html, 'Common Linux service accounts'))) {
        throw new RuntimeException('The service account command is missing from the recovery form');
    }
    if (PHP_OS_FAMILY === 'Windows' && !str_contains($html, 'Run this PowerShell command')) {
        throw new RuntimeException('The Windows service identity instructions are missing');
    }
    if (preg_match('/src="recovery\.js\?v=([a-f0-9]{16})"/', $html, $assetMatch) !== 1) {
        throw new RuntimeException('The recovery script must have a content version to replace cached commands');
    }
    $originalVersion = $assetMatch[1];
    $scriptPath = $root . '/public/install/recovery.js';
    $originalScript = file_get_contents($scriptPath);
    $originalTime = filemtime($scriptPath);
    try {
        // Release archives can preserve timestamps: content changes must still
        // produce a different URL, even when Last-Modified does not change.
        file_put_contents($scriptPath, $originalScript . "\n/* asset cache regression fixture */\n");
        touch($scriptPath, $originalTime);
        [$assetStatus, $assetHtml] = $request();
        if ($assetStatus !== 200 || preg_match('/src="recovery\.js\?v=([a-f0-9]{16})"/', $assetHtml, $changedAsset) !== 1
            || $changedAsset[1] === $originalVersion) {
            throw new RuntimeException('Changed commands retained the old asset URL');
        }
    } finally {
        file_put_contents($scriptPath, $originalScript);
        touch($scriptPath, $originalTime);
    }
    preg_match('/<script id="recovery-data" type="application\/json">(.*?)<\/script>/s', $html, $match);
    $csrf = json_decode($match[1], true)['csrf'];
    [$status, $body] = $request(['action' => 'unlock', 'operator' => $operator]);
    if ($status !== 400 || json_decode($body, true)['error'] !== 'CSRF_INVALID') {
        throw new RuntimeException('Missing CSRF was accepted');
    }
    [$status, $body] = $request(['action' => 'prepare', 'csrf' => $csrf]);
    if ($status !== 400 || json_decode($body, true)['error'] !== 'RECOVERY_REQUEST_REJECTED') {
        throw new RuntimeException('Preparation without operator authorization was accepted');
    }
    [$status, $body] = $request(['action' => 'unlock', 'operator' => 'incorrect', 'csrf' => $csrf]);
    if ($status !== 400 || json_decode($body, true)['error'] !== 'OPERATOR_CODE_INVALID') {
        throw new RuntimeException('Invalid operator code was accepted');
    }
    [$status, $body] = $request(['action' => 'unlock', 'operator' => "  " . $operator . "\n", 'csrf' => $csrf]);
    if ($status !== 200 || empty(json_decode($body, true)['result']['unlocked'])) {
        throw new RuntimeException('Valid operator code was rejected');
    }
    [$status, $html] = $request();
    if ($status !== 200 || !str_contains($html, 'id="recovery-prepare"')) {
        throw new RuntimeException('Authorized recovery form did not render');
    }
    $cookies = curl_getinfo($handle, CURLINFO_COOKIELIST);
    if (!is_array($cookies) || !array_filter($cookies, static fn (string $cookie): bool => str_contains($cookie, "\tTEAMPASS_RECOVERY\t"))) {
        throw new RuntimeException('Recovery must use its own session cookie');
    }
    // Application sign-in rotates PHPSESSID and uses an encrypted session handler.
    // Its cookie must not replace the operator's session-bound recovery progress.
    curl_setopt($handle, CURLOPT_COOKIELIST, "127.0.0.1\tFALSE\t/\tFALSE\t0\tPHPSESSID\tindependent-application-session");
    [$status, $html] = $request();
    if ($status !== 200 || !str_contains($html, 'id="recovery-prepare"')) {
        throw new RuntimeException('Application sign-in cookie changes lost the recovery session');
    }
    if (getenv('TEAMPASS_RECOVERY_RENDER_PREVIEW') === '1') {
        // A rendered-form artifact for visual QA contains no usable authorization token.
        $preview = str_replace($csrf, 'nonfunctional-visual-preview', $html);
        file_put_contents($root . '/public/install/.drp-preview.html', $preview);
    }
    [$status, $body] = $request(['action' => 'prepare', 'csrf' => $csrf, 'kit_server' => '../outside.tprecovery']);
    if ($status !== 400 || json_decode($body, true)['error'] !== 'ARCHIVE_INPUT_INVALID') {
        throw new RuntimeException('Server-side archive path traversal was accepted');
    }
    [$status, $html] = $request([], 'english');
    if ($status !== 200 || !str_contains($html, 'Restore a TeamPass instance')) {
        throw new RuntimeException('English recovery form did not render');
    }
    if (isset($argv[1])) {
        // Optional real-package scenario used by the end-to-end database fixture.
        $input = json_decode((string) file_get_contents($argv[1]), true, 32, JSON_THROW_ON_ERROR);
        $connection = $input['connection'];
        [$status, $body] = $request([
            'action' => 'prepare', 'csrf' => $csrf,
            'kit' => new CURLFile($input['kit_path'], 'application/octet-stream', 'fixture.tprecovery'),
            'backup' => new CURLFile($input['backup_path'], 'application/octet-stream', 'fixture.tpbackup'),
            'kit_password' => $input['kit_password'], 'backup_password' => $input['backup_password'],
            'host' => $connection['host'], 'port' => (string) $connection['port'],
            'database' => $connection['database'], 'user' => $connection['user'], 'password' => $connection['password'],
            'url' => $input['url'],
        ]);
        $response = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        if ($status !== 200 || empty($response['success'])) {
            throw new RuntimeException('HTTP package preparation failed: ' . ($response['error'] ?? 'unknown'));
        }
        $id = $response['result']['id'];
        [$status, $html] = $request();
        if ($status !== 200 || str_contains($html, 'id="recovery-prepare"') || !str_contains($html, $id)) {
            throw new RuntimeException('Reload did not resume the session-bound recovery job');
        }
        echo 'PREPARED_JOB:' . $id . "\n";
    }
    echo "OK: real HTTP form rendering, isolated session, asset cache invalidation, operator authorization, CSRF and path traversal checks\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    $exitCode = 1;
} finally {
    $server->stop();
    $operatorPath = $root . '/storage/recovery/operator.json';
    if (is_file($operatorPath)) {
        unlink($operatorPath);
    }
}
exit($exitCode);
