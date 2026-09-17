<?php

declare(strict_types=1);

// Serve one local HTTP response without contacting S3 or requiring credentials.
$payload = stream_get_contents(STDIN);
$status = (int) $argv[1];
$server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
if ($server === false) {
    throw new RuntimeException($errorMessage, $errorCode);
}

$client = false;
try {
    echo stream_socket_get_name($server, false) . "\n";
    fflush(STDOUT);
    $client = stream_socket_accept($server, 5);
    if ($client === false) {
        throw new RuntimeException('No HTTP request received.');
    }
    stream_set_timeout($client, 5);
    do {
        $line = fgets($client);
        if ($line === false) {
            throw new RuntimeException('Incomplete HTTP request.');
        }
    } while ($line !== "\r\n");

    $response = 'HTTP/1.1 ' . $status . ($status === 200 ? ' OK' : ' Forbidden') . "\r\n"
        . "Content-Type: application/octet-stream\r\n"
        . 'Content-Length: ' . strlen($payload) . "\r\n"
        . "Connection: close\r\n\r\n" . $payload;
    for ($offset = 0, $length = strlen($response); $offset < $length; $offset += $written) {
        $written = fwrite($client, substr($response, $offset));
        if ($written === false || $written === 0) {
            throw new RuntimeException('Could not send the HTTP response.');
        }
    }
} finally {
    if (is_resource($client)) {
        fclose($client);
    }
    fclose($server);
}
