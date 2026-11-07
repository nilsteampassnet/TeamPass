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

            return $GLOBALS['argv'][1] === 'rejected'
                ? '{"error":true,"message":"private-provider-response"}'
                : '';
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

namespace {
    require dirname(__DIR__, 2) . '/app/scripts/traits/EmailTrait.php';

    /** Supply deterministic decryption outcomes without an instance secret file. */
    function cryption($value, $key, $operation, $settings): array
    {
        $GLOBALS['decrypt_calls']++;
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

    $GLOBALS['decrypt_calls'] = 0;
    $arguments = [
        'subject' => 'Task test',
        'body' => '<p>Task body</p>',
        'receivers' => 'recipient@example.com',
        'encryptedUserPassword' => '',
    ];
    if (in_array($argv[1], ['encrypted', 'invalid'], true)) {
        $arguments['body'] = '<p>#password#</p>';
        $arguments['encryptedUserPassword'] = $argv[1] === 'invalid' ? 'invalid-ciphertext' : 'valid-ciphertext';
    }

    $failure = null;
    try {
        (new EmailTaskProbe())->run($arguments);
    } catch (RuntimeException $e) {
        $failure = $e->getMessage();
    }

    echo json_encode([
        'failure' => $failure,
        'decrypt_calls' => $GLOBALS['decrypt_calls'],
        'messages' => \TeampassClasses\EmailService\EmailService::$messages,
    ], JSON_THROW_ON_ERROR);
}
