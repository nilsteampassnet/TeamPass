<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

// Test-only loopback relay. All credentials and messages are synthetic; no TLS bypass exists in production.
$mode = $argv[1] ?? '';
if (!in_array($mode, ['none', 'password', 'oauth'], true)) { exit(2); }
$server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
if ($server === false) { throw new RuntimeException('Unable to bind test SMTP relay.'); }
$port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
echo json_encode(['port' => $port]) . "\n";
flush();
$client = stream_socket_accept($server, 10);
if ($client === false) { throw new RuntimeException('Test SMTP connection timeout.'); }
stream_set_timeout($client, 10);
$authenticated = $mode === 'none';
$authStage = '';
$dataMode = false;
$data = '';
$recipients = 0;
fwrite($client, "220 localhost ESMTP test\r\n");
while (($line = fgets($client)) !== false) {
    $command = rtrim($line, "\r\n");
    if ($dataMode) {
        if ($command === '.') {
            $dataMode = false;
            fwrite($client, "250 2.0.0 accepted\r\n");
        } else {
            $data .= str_starts_with($line, '..') ? substr($line, 1) : $line;
        }
        continue;
    }
    if ($authStage === 'username') {
        if (base64_decode($command, true) !== 'sender@example.test') { throw new RuntimeException('Test SMTP identity mismatch.'); }
        $authStage = 'password';
        fwrite($client, "334 UGFzc3dvcmQ6\r\n");
    } elseif ($authStage === 'password') {
        if (base64_decode($command, true) !== 'synthetic-password') { throw new RuntimeException('Test SMTP password mismatch.'); }
        $authenticated = true;
        $authStage = '';
        fwrite($client, "235 2.7.0 authenticated\r\n");
    } elseif (str_starts_with($command, 'EHLO ') || str_starts_with($command, 'HELO ')) {
        fwrite($client, "250-localhost\r\n250-AUTH LOGIN XOAUTH2\r\n250 8BITMIME\r\n");
    } elseif ($command === 'AUTH LOGIN' && $mode === 'password') {
        $authStage = 'username';
        fwrite($client, "334 VXNlcm5hbWU6\r\n");
    } elseif (str_starts_with($command, 'AUTH XOAUTH2 ') && $mode === 'oauth') {
        $sasl = base64_decode(substr($command, strlen('AUTH XOAUTH2 ')), true);
        if ($sasl !== "user=sender@example.test\x01auth=Bearer synthetic-access-token\x01\x01") { throw new RuntimeException('Test SMTP OAuth mismatch.'); }
        $authenticated = true;
        fwrite($client, "235 2.7.0 authenticated\r\n");
    } elseif (str_starts_with($command, 'MAIL FROM:') && $authenticated) {
        fwrite($client, "250 2.1.0 sender accepted\r\n");
    } elseif (str_starts_with($command, 'RCPT TO:') && $authenticated) {
        ++$recipients;
        fwrite($client, "250 2.1.5 recipient accepted\r\n");
    } elseif ($command === 'DATA' && $authenticated && $recipients > 0) {
        $dataMode = true;
        fwrite($client, "354 send message\r\n");
    } elseif ($command === 'QUIT') {
        fwrite($client, "221 bye\r\n");
        break;
    } else {
        throw new RuntimeException('Unexpected test SMTP command.');
    }
}
fclose($client);
fclose($server);
if ($data === '') { throw new RuntimeException('Test SMTP message is missing.'); }
echo json_encode(['authenticated' => $authenticated, 'recipients' => $recipients, 'mime_hash' => hash('sha256', rtrim($data, "\r\n"))]) . "\n";
