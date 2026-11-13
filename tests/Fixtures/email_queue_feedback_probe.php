<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace TeamPass\Tests\EmailQueueFeedback;

use Symfony\Component\HttpFoundation\Request;
use function TeamPass\Tests\SourceStructure\functionSource;
use function TeamPass\Tests\SourceStructure\variableOffset;

require dirname(__DIR__, 2) . '/app/vendor/autoload.php';
require __DIR__ . '/php_source_structure.php';

$mode = $argv[1];
$queued = str_ends_with($mode, '-ok');
$state = ['queued_calls' => 0, 'reset' => false, 'revoked' => false, 'consumed' => false, 'audit' => []];
$SETTINGS = ['default_language' => 'english', 'ga_website_name' => 'Synthetic vault'];
$GLOBALS['SETTINGS'] = $SETTINGS;

final class Language
{
    private array $strings;
    /** Load shipped strings without an installation or template customization database. */
    public function __construct(string $language = 'english') { $this->strings = require dirname(__DIR__, 2) . '/app/includes/language/' . $language . '.php'; }
    /** Return the actual shipped string for the production response. */
    public function get(string $key): string { return $this->strings[$key] ?? $key; }
}
final class SessionManager
{
    /** Supply only the session language used by the mail response. */
    public static function getSession(): object { return new class { public function get(string $key): string { return 'english'; } }; }
}
final class DB
{
    private static int $count = 0;
    /** Return a synthetic user or an unused MFA token. */
    public static function queryFirstRow(string $sql, mixed ...$values): ?array
    {
        self::$count = str_contains($sql, 'fixture_users') ? 1 : 0;
        return self::$count === 1 ? testUser() : null;
    }
    /** Preserve the production user/token branch decisions. */
    public static function count(): int { return self::$count; }
    /** No real token is written. */
    public static function insert(mixed ...$arguments): void {}
    /** Supply a synthetic token id. */
    public static function insertId(): int { return 1; }
    /** No real user/MFA setting is changed. */
    public static function update(mixed ...$arguments): void {}
    /** No real authentication record is deleted. */
    public static function delete(mixed ...$arguments): void {}
}
/** Synthetic account used by both recovery and MFA flows. */
function testUser(): array { return ['id' => 7, 'login' => 'fixture-user', 'email' => 'recipient@example.test', 'name' => 'Fixture', 'lastname' => 'User', 'user_language' => 'english', 'ga' => '', 'ga_temporary_code' => 'pending']; }
/** Stand in only for the tested enqueue boundary; the actual mail wrapper and callers run below. */
function prepareSendingEmail(mixed ...$arguments): bool { global $queued, $state; ++$state['queued_calls']; return $queued; }
/** Keep the response adapter independent of the live session key. */
function prepareExchangedData(mixed $data, string $operation): mixed { return $operation === 'encode' ? json_encode($data, JSON_THROW_ON_ERROR) : json_decode($data, true, 512, JSON_THROW_ON_ERROR); }
/** Keep all SQL names synthetic. */
function prefixTable(string $table): string { return 'fixture_' . $table; }
/** Supply deterministic temporary credentials that must never appear in an error page. */
function GenerateCryptKey(mixed ...$arguments): string { return 'private-temporary-credential'; }
/** Model the already covered settings predicate. */
function isKeyExistingAndEqual(string $key, mixed $expected, array $settings): bool { return isset($settings[$key]) && $settings[$key] === $expected; }
/** Model only the id-presence branch in generateQRCode(). */
function isValueSetNullEmpty(mixed $value): bool { return $value === '' || $value === null; }
/** Record the audit action without retaining any mail content. */
function logEvents(array $settings, string $type, string $action, mixed ...$arguments): void { global $state; $state['audit'][] = $action; }
/** Preserve the success response's address-obfuscation boundary. */
function obfuscateEmail(string $email): string { return 'r***@example.test'; }
/** Provide a valid token without consuming a real recovery credential. */
function getForgotLocalPasswordTokenRow(string $token): array { return ['id' => 1, 'user_id' => 7]; }
/** No production user lookup is performed. */
function getUserCompleteData(mixed ...$arguments): array { return testUser(); }
/** Eligibility/security rules have their own production regressions. */
function getForgotLocalPasswordContext(array $user, array $settings): array { return ['available' => true]; }
/** Record token consumption in the real reset flow. */
function deleteForgotLocalPasswordToken(int $id): void { global $state; $state['consumed'] = true; }
/** Model a completed password reset before email enqueue. */
function handleUserKeys(mixed ...$arguments): string { global $state; $state['reset'] = true; return '{"error":false}'; }
/** Preserve the session-revocation call after the reset. */
function tpRevokeUserSessions(int $id): void { global $state; $state['revoked'] = true; }
/** No real instance key or plaintext credential is used. */
function cryption(mixed ...$arguments): array { return ['string' => 'def50200synthetic']; }

$main = (string) file_get_contents(dirname(__DIR__, 2) . '/app/sources/main.functions.php');
eval('namespace ' . __NAMESPACE__ . '; use TeampassClasses\\EmailService\\EmailSettings; use TeampassClasses\\EmailService\\EmailService; use voku\\helper\\AntiXSS;' . functionSource($main, 'sendMailToUser'));

if (str_starts_with($mode, 'reset-')) {
    define('TP_TOOL_NAME', 'Synthetic vault');
    define('TP_VERSION', 'test');
    define('TP_VERSION_MINOR', '0');
    define('NUMBER_ITEMS_IN_BATCH', 10);
    $request = Request::create('/reset-password.php', 'POST', ['token' => 'synthetic-token']);
    $lang = new Language();
    $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/sources/reset-password.php');
    eval('namespace ' . __NAMESPACE__ . ';' . functionSource($source, 'resetPasswordRenderPage') . functionSource($source, 'resetPasswordInvalidBlock'));
    register_shutdown_function(static function () use (&$state): void { echo "\n<!-- state:" . json_encode($state) . ' -->'; });
    eval('namespace ' . __NAMESPACE__ . ';' . substr($source, variableOffset($source, '$token')));
} elseif (str_starts_with($mode, 'ga-')) {
    $queries = (string) file_get_contents(dirname(__DIR__, 2) . '/app/sources/main.queries.php');
    eval('namespace ' . __NAMESPACE__ . '; use RobThree\\Auth\\TwoFactorAuth;' . functionSource($queries, 'generateQRCode'));
    echo generateQRCode(7, true, '1', '', '', 'synthetic-token', $SETTINGS);
} else {
    echo json_encode(['response' => sendMailToUser('recipient@example.test', 'Synthetic body', 'Synthetic subject', []), 'state' => $state], JSON_THROW_ON_ERROR);
}
