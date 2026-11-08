<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file setup_permissions_http.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

require dirname(__DIR__, 2) . '/app/vendor/autoload.php';
if (!extension_loaded('curl')) { exit(2); }
$source = dirname(__DIR__, 2);
$root = sys_get_temp_dir() . '/tp-setup-http-' . bin2hex(random_bytes(8));
$files = ['public/install/permissions.php', 'public/install/cleanup_install.php',
    'public/install/install-steps/install.functions.php', 'app/sources/setup_permissions.php',
    'app/sources/file_permissions.functions.php', 'app/sources/file_scope.functions.php',
    'app/sources/runtime_files.functions.php', 'app/sources/install_security_logic.php',
    'app/sources/config_access_logic.php'];
foreach ($files as $file) {
    if (!is_dir(dirname($root . '/' . $file))) { mkdir(dirname($root . '/' . $file), 0700, true); }
    copy($source . '/' . $file, $root . '/' . $file);
}
mkdir($root . '/app/config', 0700, true);
mkdir($root . '/storage/logs', 0700, true);
mkdir($root . '/storage/files', 0700, true);
mkdir($root . '/app/includes/libraries/csrfp/log', 0700, true);
mkdir($root . '/sessions', 0700);
file_put_contents($root . '/app/config/settings.php', '<?php // synthetic, never executed');
$settingsHash = hash_file('sha256', $root . '/app/config/settings.php');
$sessionId = 'permissions' . bin2hex(random_bytes(8));
$seed = static function (array $session) use ($root, $sessionId): void {
    $contents = '';
    foreach ($session as $name => $value) { $contents .= $name . '|' . serialize($value); }
    file_put_contents($root . '/sessions/sess_' . $sessionId, $contents);
};
$grant = ['setup_permissions_ready' => 'install', 'setup_permissions_csrf' => 'fixture-token',
    'install_settings_sha256' => $settingsHash];
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
$address = stream_socket_get_name($socket, false);
fclose($socket);
$server = new \Symfony\Component\Process\Process([PHP_BINARY, '-d', 'session.save_path="' . str_replace('\\', '/', $root) . '/sessions"',
    '-S', $address, '-t', $root . '/public']);
$server->start();
$request = static function (array $post, string $token = 'fixture-token', string $route = 'permissions.php') use ($address, $sessionId): array {
    $curl = curl_init('http://' . $address . '/install/' . $route);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post), CURLOPT_COOKIE => 'PHPSESSID=' . $sessionId,
        CURLOPT_HTTPHEADER => ['X-CSRF-Token: ' . $token]]);
    $body = curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return [$code, json_decode((string) $body, true, 32, JSON_THROW_ON_ERROR)];
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
};
$exit = 0;
try {
    for ($i = 0; $i < 100; $i++) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if (is_resource($probe)) { fclose($probe); break; }
        usleep(20000);
    }
    $post = ['flow' => 'install', 'action' => 'audit'];
    $seed([]);
    [$code] = $request($post);
    $assert($code === 403, 'An unbound session was accepted');
    $seed($grant);
    [$code] = $request($post, 'wrong-token');
    $assert($code === 403, 'An invalid CSRF token was accepted');
    $seed($grant + ['user_granted' => '1']);
    [$code] = $request(['flow' => 'install', 'action' => 'finalize'], 'wrong-token', 'cleanup_install.php');
    $assert($code === 403 && is_dir($root . '/public/install'), 'Legacy cleanup bypassed the review');
    [$code] = $request(['flow' => 'install', 'action' => 'cleanup'], 'fixture-token', 'cleanup_install.php');
    $assert($code === 400 && is_dir($root . '/public/install'), 'Legacy cleanup still deleted protected installer files');
    $seed(array_replace($grant, ['install_settings_sha256' => str_repeat('0', 64)]));
    [$code] = $request($post);
    $assert($code === 403, 'A session belonging to old settings was accepted');
    $seed($grant);
    [$code, $result] = $request($post);
    $assert($code === 200 && $result['success'], 'A completed setup session could not audit');
    $status = $result['review']['status'];
    $lock = fopen($root . '/storage/recovery/operation.lock', 'c+b');
    flock($lock, LOCK_EX);
    try {
        [$code, $blocked] = $request($post);
        $assert($code === 409 && $blocked['error'] === 'SETUP_OPERATION_BUSY', 'A concurrent setup operation was accepted');
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    $lock = fopen($root . '/storage/recovery/setup-finalization.lock', 'c+b');
    flock($lock, LOCK_EX);
    try {
        [$code, $blocked] = $request(['flow' => 'install', 'action' => 'finalize', 'acknowledge_manual' => '1']);
        $assert($code === 409 && $blocked['error'] === 'SETUP_OPERATION_BUSY', 'A concurrent finalization was accepted');
        $assert(!is_file($root . '/storage/logs/setup-permissions.jsonl'), 'A busy finalization wrote a journal entry');
        [$code, $audited] = $request($post);
        $assert($code === 200 && $audited['success'], 'A finalization lock unnecessarily blocked a read-only audit');
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    [$code, $result] = $request(['flow' => 'install', 'action' => 'finalize', 'review' => 'passed']);
    $assert(($result['error'] ?? '') !== 'SETUP_OPERATION_BUSY', 'A released finalization lock remained busy');
    $assert($status === 'passed' || $code === 409, 'A client-supplied pass bypassed server verification');
    if ($status === 'manual') {
        $assert($result['error'] === 'PERMISSIONS_MANUAL_ACK_REQUIRED', 'Missing manual acknowledgement was accepted');
        [$code, $result] = $request(['flow' => 'install', 'action' => 'finalize', 'acknowledge_manual' => '1']);
        $assert($code === 200 && $result['finalized'] && $result['review']['status'] === 'manual', 'Explicit manual approval failed');
        $entry = json_decode(trim((string) file_get_contents($root . '/storage/logs/setup-permissions.jsonl')), true);
        $assert(isset($entry['manual_acknowledged_at']), 'Manual acknowledgement was not recorded');
        [$code] = $request($post);
        $assert($code === 403, 'A completed finalization grant was reusable');
    } elseif ($status === 'issues') {
        [$code, $result] = $request(['flow' => 'install', 'action' => 'finalize', 'acknowledge_manual' => '1']);
        $assert($code === 409 && $result['error'] === 'PERMISSIONS_REVIEW_REQUIRED', 'Known findings could be acknowledged away');
    }
    $assert(is_dir($root . '/public/install'), 'The endpoint removed installer code');
    echo "OK: setup session binding, CSRF, legacy cleanup, exclusive finalization, current audit and finalization policy ($status)\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n" . $server->getErrorOutput());
    $exit = 1;
} finally {
    $server->stop();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) { rmdir($entry->getPathname()); } else { unlink($entry->getPathname()); }
    }
    rmdir($root);
}
exit($exit);
