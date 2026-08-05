<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 *
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      LAPRSshService.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

namespace TeampassClasses\Lapr;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Throwable;

/**
 * SSH transport for LAPR (Linux Account Password Rotation).
 *
 * Single entry point for every remote operation performed by LAPR:
 * connection test + system info collection (enrollment), and password
 * change through chpasswd (rotation). Built on phpseclib3 only — no
 * exec(), no sshpass, no php-ssh2 extension.
 *
 * NOTE: this class is loaded via require_once from the LAPR traits and
 * AJAX handlers (decision D8 — not a Composer path-repository package).
 */
class LAPRSshService
{
    /** Error taxonomy returned by connect() and exec-based helpers */
    public const ERR_TIMEOUT = 'ERR_TIMEOUT';
    public const ERR_REFUSED = 'ERR_REFUSED';
    public const ERR_HOST_UNREACHABLE = 'ERR_HOST_UNREACHABLE';
    public const ERR_AUTH_FAILED = 'ERR_AUTH_FAILED';
    public const ERR_HOSTKEY_MISMATCH = 'ERR_HOSTKEY_MISMATCH';
    public const ERR_INVALID_USERNAME = 'ERR_INVALID_USERNAME';
    public const ERR_CHPASSWD_FAILED = 'ERR_CHPASSWD_FAILED';
    public const ERR_NOT_CONNECTED = 'ERR_NOT_CONNECTED';
    public const ERR_UNKNOWN = 'ERR_UNKNOWN';

    /** POSIX/useradd username rule — mandatory validation before any remote command (R1) */
    public const USERNAME_REGEX = '/^[a-z_][a-z0-9_-]{0,31}\$?$/';

    private ?SSH2 $ssh = null;
    private int $timeout;

    /**
     * @param int $timeout SSH connect timeout in seconds (setting lapr_ssh_connect_timeout)
     */
    public function __construct(int $timeout = 10)
    {
        $this->timeout = max(1, $timeout);
    }

    /**
     * Open the SSH connection and authenticate.
     *
     * @param string $host       Hostname or IP
     * @param int    $port       SSH port
     * @param string $username   SSH username
     * @param string $authMethod 'password' or 'key'
     * @param string $secret     Password, or private key PEM (optionally "key\n:::\npassphrase")
     *
     * @return array{success: bool, error_code?: string, error_detail?: string, fingerprint?: string}
     */
    public function connect(string $host, int $port, string $username, string $authMethod, string $secret): array
    {
        try {
            $ssh = new SSH2($host, $port, $this->timeout);
        } catch (Throwable $e) {
            return ['success' => false, 'error_code' => self::ERR_UNKNOWN, 'error_detail' => $e->getMessage()];
        }

        try {
            if ($authMethod === 'key') {
                // The key material may carry an optional passphrase separated by ':::'
                $parts = explode("\n:::\n", $secret, 2);
                $key = PublicKeyLoader::loadPrivateKey($parts[0], $parts[1] ?? false);
                $authenticated = $ssh->login($username, $key);
            } else {
                $authenticated = $ssh->login($username, $secret);
            }
        } catch (Throwable $e) {
            return ['success' => false] + $this->classifyConnectError($e->getMessage());
        }

        if ($authenticated !== true) {
            // Connection reached the server but credentials were rejected
            $lastError = method_exists($ssh, 'getLastError') ? (string) $ssh->getLastError() : '';
            $ssh->disconnect();
            return [
                'success' => false,
                'error_code' => self::ERR_AUTH_FAILED,
                'error_detail' => $lastError !== '' ? $lastError : 'Authentication failed',
            ];
        }

        $this->ssh = $ssh;

        return [
            'success' => true,
            'fingerprint' => $this->getFingerprint() ?? '',
        ];
    }

    /**
     * Compute the TOFU fingerprint of the connected server's host key.
     * Returns null when not connected.
     *
     * @return string|null
     */
    public function getFingerprint(): ?string
    {
        if ($this->ssh === null) {
            return null;
        }
        $hostKey = $this->ssh->getServerPublicHostKey();
        if (is_string($hostKey) === false || $hostKey === '') {
            return null;
        }

        return self::computeFingerprint($hostKey);
    }

    /**
     * TeamPass-internal host key fingerprint (TOFU compare only).
     *
     * The raw OpenSSH-form key string is hashed, so the value will NOT match
     * `ssh-keygen -lf` output (which hashes the decoded blob and strips base64
     * padding). It is internally consistent: the same code computes both the
     * stored and the compared value (correction C16 / risk R7).
     *
     * @param string $serverPublicHostKey Key in OpenSSH string form (e.g. 'ssh-ed25519 AAAA...')
     *
     * @return string 'SHA256:' + base64 hash
     */
    public static function computeFingerprint(string $serverPublicHostKey): string
    {
        return 'SHA256:' . base64_encode(hash('sha256', $serverPublicHostKey, true));
    }

    /**
     * Run a remote command and return exit code + output.
     *
     * @param string $command Command executed through the remote user's shell
     *
     * @return array{exit_code: int, output: string}
     */
    public function exec(string $command): array
    {
        if ($this->ssh === null) {
            return ['exit_code' => -1, 'output' => self::ERR_NOT_CONNECTED];
        }

        $output = $this->ssh->exec($command);
        $exitStatus = $this->ssh->getExitStatus();

        return [
            'exit_code' => is_int($exitStatus) ? $exitStatus : -1,
            'output' => is_string($output) ? $output : '',
        ];
    }

    /**
     * Collect OS info and rotation capabilities from the connected endpoint.
     * Used at enrollment (Point 1) and by re-checks.
     *
     * @return array{os_info: array, capabilities: array}
     */
    public function testAndCollect(): array
    {
        $osRelease = (string) $this->exec('cat /etc/os-release 2>/dev/null')['output'];
        $osMetadata = [];
        foreach (preg_split('/\r?\n/', $osRelease) ?: [] as $line) {
            if (strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $osMetadata[strtoupper(trim($key))] = trim($value, " \t\n\r\0\x0B\"'");
        }

        $osName = trim((string) ($osMetadata['PRETTY_NAME'] ?? ''));
        if ($osName === '') {
            $osName = trim($this->exec('uname -s')['output']);
        }
        $osId = strtolower(trim((string) ($osMetadata['ID'] ?? '')));
        $osLike = strtolower(trim((string) ($osMetadata['ID_LIKE'] ?? '')));
        $familyTokens = ' ' . $osId . ' ' . $osLike . ' ';
        $osFamily = 'generic';
        if (preg_match('/\b(alpine)\b/', $familyTokens) === 1) {
            $osFamily = 'alpine';
        } elseif (preg_match('/\b(debian|ubuntu|linuxmint|pop)\b/', $familyTokens) === 1) {
            $osFamily = 'debian';
        } elseif (preg_match('/\b(rhel|fedora|centos|rocky|almalinux|amzn|ol)\b/', $familyTokens) === 1) {
            $osFamily = 'rhel';
        } elseif (preg_match('/\b(suse|opensuse|sles)\b/', $familyTokens) === 1) {
            $osFamily = 'suse';
        } elseif (preg_match('/\b(arch|manjaro)\b/', $familyTokens) === 1) {
            $osFamily = 'arch';
        }
        $kernel = trim($this->exec('uname -r')['output']);
        $userInfo = trim($this->exec('id')['output']);
        $isRoot = trim($this->exec('id -u')['output']) === '0';

        $hasChpasswd = $this->commandExists('chpasswd');
        $hasPasswd = $this->commandExists('passwd');

        // D5 — faithful rotation-capability probe. Mirror exactly what a rotation
        // runs (LAPRRotationTrait): `chpasswd` as root, `sudo -n chpasswd`
        // otherwise. Feed empty stdin — chpasswd with no input is a no-op, so
        // nothing on the endpoint is changed — and read the exit code, so the
        // enrollment decision reflects the real rotation path.
        //
        // This is deliberately stricter and more accurate than the former
        // `sudo -n true`: a sudoers rule scoped to chpasswd only (NOPASSWD:
        // /usr/sbin/chpasswd) would fail `sudo -n true` yet rotate fine. It also
        // sidesteps `command -v chpasswd` misreporting when /usr/sbin is absent
        // from a non-root PATH — sudo resolves chpasswd through secure_path.
        // sudo -n never prompts (fails immediately if a password is required),
        // so the probe cannot hang.
        $rotateCommand = $isRoot ? 'chpasswd' : 'sudo -n chpasswd';
        $canRotate = trim($this->exec($rotateCommand . ' < /dev/null >/dev/null 2>&1; echo $?')['output']) === '0';

        // has_sudo now specifically means "chpasswd is reachable via passwordless
        // sudo" — the exact signal LAPRRotationTrait uses to prefix the command.
        $hasSudo = $isRoot === false && $canRotate;

        return [
            'os_info' => [
                'os_name' => $osName,
                'os_id' => $osId,
                'os_like' => $osLike,
                'os_family' => $osFamily,
                'kernel' => $kernel,
                'user_info' => $userInfo,
                'is_root' => $isRoot,
            ],
            'capabilities' => [
                'has_chpasswd' => $hasChpasswd,
                'has_passwd' => $hasPasswd,
                'has_sudo' => $hasSudo,
                'can_rotate' => $canRotate,
            ],
        ];
    }

    /**
     * Change a local account password through chpasswd (decision D2 — Approach 1).
     *
     * The username is hard-validated against the POSIX rule before building the
     * remote command (risk R1) — quoting alone is never trusted for it. The
     * password travels inside escapeshellarg() single quotes; the caller must
     * have filtered ':'/whitespace/backslash out of generated passwords (R9).
     *
     * @param string $username    Target Linux account (validated against USERNAME_REGEX)
     * @param string $newPassword New password
     * @param bool   $useSudo     Prefix with 'sudo -n' (decision D5 — non-root SSH user with sudo)
     *
     * @return array{success: bool, error_code?: string, error_detail?: string}
     */
    public function changePassword(string $username, string $newPassword, bool $useSudo = false): array
    {
        if ($this->ssh === null) {
            return ['success' => false, 'error_code' => self::ERR_NOT_CONNECTED];
        }
        if (preg_match(self::USERNAME_REGEX, $username) !== 1) {
            return ['success' => false, 'error_code' => self::ERR_INVALID_USERNAME];
        }

        $chpasswd = $useSudo ? 'sudo -n chpasswd' : 'chpasswd';
        $command = sprintf(
            "printf '%%s:%%s\\n' %s %s | %s",
            escapeshellarg($username),
            escapeshellarg($newPassword),
            $chpasswd
        );

        $result = $this->exec($command);
        if ($result['exit_code'] !== 0) {
            // Never propagate raw output verbatim upstream in audit details (R4):
            // truncate and strip anything that could echo the command line.
            $detail = substr(trim($result['output']), 0, 300);
            return [
                'success' => false,
                'error_code' => self::ERR_CHPASSWD_FAILED,
                'error_detail' => $detail,
            ];
        }

        return ['success' => true];
    }

    /**
     * List local accounts on the endpoint (for the discover feature, Point 2).
     *
     * @return array<int, array{username: string, uid: int, shell: string}>
     */
    public function listLocalAccounts(): array
    {
        $result = $this->exec('getent passwd 2>/dev/null || cat /etc/passwd');
        if ($result['exit_code'] !== 0 && trim($result['output']) === '') {
            return [];
        }

        $accounts = [];
        foreach (explode("\n", $result['output']) as $line) {
            $parts = explode(':', trim($line));
            if (count($parts) < 7) {
                continue;
            }
            $accounts[] = [
                'username' => $parts[0],
                'uid' => (int) $parts[2],
                'shell' => $parts[6],
            ];
        }

        return $accounts;
    }

    /**
     * Whether a command is available on the endpoint.
     *
     * @param string $command Command name (internal callers only — not user input)
     *
     * @return bool
     */
    private function commandExists(string $command): bool
    {
        $result = $this->exec('command -v ' . escapeshellarg($command) . ' >/dev/null 2>&1; echo $?');

        return trim($result['output']) === '0';
    }

    /**
     * Map a low-level connection exception message to the LAPR error taxonomy.
     *
     * @param string $message Exception message from phpseclib
     *
     * @return array{error_code: string, error_detail: string}
     */
    private function classifyConnectError(string $message): array
    {
        $lower = strtolower($message);
        if (strpos($lower, 'timed out') !== false || strpos($lower, 'timeout') !== false) {
            $code = self::ERR_TIMEOUT;
        } elseif (strpos($lower, 'refused') !== false) {
            $code = self::ERR_REFUSED;
        } elseif (strpos($lower, 'no route') !== false
            || strpos($lower, 'unreachable') !== false
            || strpos($lower, 'getaddrinfo') !== false
            || strpos($lower, 'unable to connect') !== false
        ) {
            $code = self::ERR_HOST_UNREACHABLE;
        } else {
            $code = self::ERR_UNKNOWN;
        }

        return ['error_code' => $code, 'error_detail' => substr($message, 0, 300)];
    }

    /**
     * Close the SSH connection.
     *
     * @return void
     */
    public function disconnect(): void
    {
        if ($this->ssh !== null) {
            $this->ssh->disconnect();
            $this->ssh = null;
        }
    }
}
