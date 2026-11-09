<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See the LICENSE file for details.
 */

namespace TeampassClasses\EmailService {
    /** Stand in for the external mail boundary in an isolated process. */
    final class EmailService
    {
        public static array $messages = [];

        /** Record the message and return the existing delivery result contract. */
        public function sendMail($subject, $body, $receivers, $settings, $alternative, $silent, $cron)
        {
            self::$messages[] = ['body' => $body, 'silent' => $silent, 'cron' => $cron];
            if ($GLOBALS['argv'][1] === 'unexpected') {
                throw new \RuntimeException('private-provider-response');
            }
            if ($GLOBALS['argv'][1] === 'spoofed') {
                throw new \RuntimeException('Email credential could not be decrypted. private-provider-response');
            }

            return in_array($GLOBALS['argv'][1], ['rejected', 'throttled', 'exhausted', 'indeterminate'], true)
                ? '{"error":true,"message":"private-provider-response"}'
                : '';
        }

        /** Supply the production structured result without making a provider request. */
        public function getLastResult(): EmailSendResult
        {
            return match ($GLOBALS['argv'][1]) {
                'throttled', 'exhausted' => EmailSendResult::failure('throttled', true, 172800),
                'indeterminate' => EmailSendResult::indeterminate('timeout'),
                default => EmailSendResult::failure('rejected'),
            };
        }
    }

    final class EmailSettings
    {
        /** No configuration or database is needed by this boundary stub. */
        public function __construct(array $settings)
        {
        }
    }
}

namespace TeampassClasses\ConfigManager {
    final class ConfigManager
    {
        /** Keep the production worker constructor independent of an installation. */
        public function getAllSettings(): array
        {
            return [];
        }
    }
}

namespace {
    require dirname(__DIR__, 2) . '/app/vendor/autoload.php';
    require dirname(__DIR__, 2) . '/app/sources/secret_settings_logic.php';
    require dirname(__DIR__, 2) . '/app/scripts/traits/EmailTrait.php';

    /** Supply deterministic decryption outcomes without an instance secret file. */
    function cryption($value, $key, $operation, $settings = []): array
    {
        $GLOBALS['decrypt_calls']++;
        if ($value === 'def50200cafe') {
            return ['string' => json_encode($GLOBALS['email_probe_plain_message']), 'error' => false];
        }
        return $value === 'invalid-ciphertext'
            ? ['string' => '', 'error' => 'wrong_key_or_modified_ciphertext']
            : ['string' => 'temporary-password', 'error' => false];
    }

    final class EmailTaskProbe
    {
        use EmailTrait;

        private array $settings = [];

        /** Execute the production email task method with isolated dependencies. */
        public function run(array $arguments): void
        {
            $this->sendEmail($arguments);
        }
    }

    /** In-memory persistence boundary for the real worker's status transitions. */
    final class DB
    {
        public static array $updates = [];

        /** Observe the attempt count after the worker increments it. */
        public static function queryFirstField(string $sql, mixed ...$arguments): int
        {
            return $GLOBALS['argv'][1] === 'exhausted' ? 3 : 1;
        }

        /** The real worker increments its attempt before submitting the message. */
        public static function query(string $sql, mixed ...$arguments): array { return []; }

        /** Unknown recipients fall back to their email in the real Tasks log renderer. */
        public static function queryFirstRow(string $sql, mixed ...$arguments): ?array { return null; }

        /** This fixture has no matching application user. */
        public static function count(): int { return 0; }

        /** Record writes, including the task's actual final status and error. */
        public static function update($table, $values, $where, $id): void
        {
            self::$updates[] = $values;
        }
    }

    final class TaskLogger
    {
        public static array $entries = [];

        /** Capture log calls without writing a runtime journal. */
        public function __construct(array $settings, string $path)
        {
        }

        /** Capture exactly what the worker gives its logger. */
        public function log(string $message, string $level): void
        {
            self::$entries[] = $message;
        }
    }

    /** Supply infrastructure helpers that email tasks do not otherwise exercise. */
    function resetItemRevisionMemo(): void
    {
    }

    /** Preserve use of a configurable table prefix at the persistence boundary. */
    function prefixTable(string $table): string
    {
        return 'test_' . $table;
    }

    /** Load the actual worker class without running its installation bootstrap or CLI entry point. */
    function loadEmailTaskWorker(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/app/scripts/background_tasks___worker.php');
        $class = '';
        $recording = false;
        $depth = 0;
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && $token[0] === T_CLASS) {
                $recording = true;
            }
            if (!$recording) {
                continue;
            }
            $class .= is_array($token) ? $token[1] : $token;
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
            } elseif ($token === '}' && --$depth === 0) {
                break;
            }
        }
        if ($depth !== 0 || !str_starts_with($class, 'class TaskWorker ')) {
            throw new \RuntimeException('Unable to load the production worker class.');
        }
        // Other task families are outside this probe; EmailTrait is the production trait.
        preg_match_all('/^    use ([A-Za-z0-9]+Trait);/m', $class, $traits);
        foreach ($traits[1] as $trait) {
            if ($trait !== 'EmailTrait') {
                eval('trait ' . $trait . ' {}');
            }
        }
        eval('use TeampassClasses\\ConfigManager\\ConfigManager; use TeampassClasses\\Language\\Language; ' . $class);
    }

    $GLOBALS['decrypt_calls'] = 0;
    $sealedFailure = str_starts_with($argv[1], 'sealed-');
    if ($sealedFailure) {
        $argv[1] = substr($argv[1], strlen('sealed-'));
    }
    $arguments = [
        'subject' => 'Task test',
        'body' => '<p>Task body</p>',
        'receivers' => 'recipient@example.com',
        'receiver_name' => 'Recipient',
        'encryptedUserPassword' => '',
    ];
    if (in_array($argv[1], ['encrypted', 'invalid'], true)) {
        $arguments['body'] = '<p>#password#</p>';
        $arguments['encryptedUserPassword'] = $argv[1] === 'invalid' ? 'invalid-ciphertext' : 'valid-ciphertext';
    }
    if ($argv[1] === 'unnamed') {
        $arguments['receiver_name'] = '';
    }
    if ($argv[1] === 'sealed' || $sealedFailure) {
        $GLOBALS['email_probe_plain_message'] = $arguments;
        $arguments = ['email_payload_version' => 1, 'ciphertext' => 'def50200cafe'];
    }
    if ($argv[1] === 'payload-invalid') {
        $arguments = ['email_payload_version' => 1, 'ciphertext' => 'def50200dead'];
    }

    $failure = null;
    try {
        if (($argv[2] ?? '') === 'worker') {
            define('LOG_TASKS', true);
            define('LOG_TASKS_FILE', 'unused-test-log');
            loadEmailTaskWorker();
            (new TaskWorker(123, 'send_email', $arguments))->execute();
        } else {
            (new EmailTaskProbe())->run($arguments);
        }
    } catch (RuntimeException $e) {
        $failure = $e->getMessage();
    }

    $displayUser = null;
    if (isset(DB::$updates[0]['arguments'])) {
        require_once dirname(__DIR__, 2) . '/app/sources/log_display_logic.php';
        $source = str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/app/sources/logs.datatables.php'));
        if (preg_match('/function resolveBackgroundTaskUserDisplay\(array \$arguments, string \$processType\): string\s*\{.*?\n\}/s', $source, $match) !== 1) {
            throw new RuntimeException('Missing Tasks log recipient resolver.');
        }
        // Run the production display logic without its HTTP/session/database bootstrap.
        eval($match[0]);
        $displayUser = resolveBackgroundTaskUserDisplay(json_decode(DB::$updates[0]['arguments'], true), 'send_email');
    }
    echo json_encode([
        'failure' => $failure,
        'decrypt_calls' => $GLOBALS['decrypt_calls'],
        'messages' => \TeampassClasses\EmailService\EmailService::$messages,
        'task_updates' => DB::$updates,
        'logs' => TaskLogger::$entries,
        'display_user' => $displayUser,
    ], JSON_THROW_ON_ERROR);
}
