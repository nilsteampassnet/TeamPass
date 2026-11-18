<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

namespace phpseclib3\Net {
    /** Observe authentication ordering without opening a network connection. */
    final class SFTP
    {
        public static int $logins = 0;
        /** Accept the production connection arguments. */
        public function __construct(...$arguments) {}
        /** Return a deterministic fictitious host key. */
        public function getServerPublicHostKey(): string { return 'ssh-ed25519 ' . base64_encode('fixture host key'); }
        /** Count any attempt to send authentication material. */
        public function login(...$arguments): bool { self::$logins++; return true; }
        /** The fixture destination exists. */
        public function is_dir(string $path): bool { return true; }
        /** No actual connection needs closing. */
        public function disconnect(): void {}
    }
}
namespace {
    require __DIR__ . '/../../app/sources/backup.functions.php';
    $fingerprint = 'SHA256:' . rtrim(base64_encode(hash('sha256', 'fixture host key', true)), '=');
    $expected = match ($argv[1]) {
        'trusted' => $fingerprint,
        'changed' => 'SHA256:' . str_repeat('A', 43),
        default => '',
    };
    $result = tpBackupOpenExternalizedSftpConnection(['host' => 'backup.example.test', 'port' => 22,
        'username' => 'fixture', 'password' => 'fictitious-password', 'remote_path' => '/backups',
        'host_key_sha256' => $expected]);
    echo json_encode(['success' => $result['success'], 'reason' => $result['reason'],
        'logins' => \phpseclib3\Net\SFTP::$logins], JSON_THROW_ON_ERROR);
}
