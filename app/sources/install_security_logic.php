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
 * DB-free security logic shared by the installer steps.
 *
 * It is required by both:
 *   - public/install/install-steps/install.functions.php  (the HTTP/DB entrypoints)
 *   - tests/Unit/InstallSecurityLogicTest.php              (unit tests)
 *
 * Everything here is pure: no DB, no session, no filesystem, no superglobals. The session and
 * header glue (installStartSession / installAssertStepAuthorized) lives in install.functions.php.
 *
 * Context: GHSA-8xp7-fh3p-5pcr — the installer wrote app/config/settings.php by raw
 * concatenation of attacker-controlled POST input, turning a value such as
 * tablePrefix=pwn_${...} into PHP code executed the next time settings.php is included.
 *
 * @file      install_security_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Escape a value so it can be embedded inside a double-quoted PHP string literal written to
 * settings.php without changing the code that is generated.
 *
 * The installer sanitizer is HTML/JS-oriented and lets '$', '{' and '}' through, so a value
 * reaching the define() block unescaped could re-open the string ('"'), extend an escape
 * sequence ('\') or — the actual RCE — introduce variable interpolation ('${...}', '{${...}}').
 * Escaping the backslash first, then the double quote, then the dollar sign neutralises all
 * three: in a double-quoted literal "\$" is a literal dollar, so no "${...}" / "{${...}}" can
 * form, and '{' / '}' without a preceding '$' are inert.
 *
 * @param string $value Raw value to embed.
 *
 * @return string Value safe to concatenate inside a double-quoted PHP string literal.
 */
function installSanitizeSettingsValue(string $value): string
{
    // Order matters: backslash must be doubled before the other escapes are introduced,
    // otherwise the backslash they add would itself be doubled.
    $value = str_replace('\\', '\\\\', $value);
    $value = str_replace('"', '\\"', $value);
    $value = str_replace('$', '\\$', $value);

    return $value;
}

/**
 * Tell whether a database table prefix is acceptable.
 *
 * The prefix is both written into settings.php and used to build table identifiers
 * (prefix.'users', prefix.'misc', ...). Restricting it to word characters keeps it out of the
 * code-injection sink and out of SQL identifiers. An empty prefix is allowed (TeamPass supports
 * an empty DB_PREFIX); the upper bound mirrors the settings.php column width.
 *
 * @param string $prefix Candidate table prefix.
 *
 * @return bool True when the prefix is safe to use.
 */
function installIsValidTablePrefix(string $prefix): bool
{
    return preg_match('/^[A-Za-z0-9_]{0,64}$/', $prefix) === 1;
}

/**
 * Actions the dynamic dispatcher of a given installer step is allowed to invoke.
 *
 * Replaces the previous "any method_exists() method" dispatch, which let an attacker pick
 * settingsFile (the RCE sink) directly. Unknown steps return an empty list, so nothing runs.
 *
 * @param string $step Step identifier, e.g. 'step5' or 'step6'.
 *
 * @return string[] The allow-listed action names for that step.
 */
function installAllowedActions(string $step): array
{
    $map = [
        // Database schema creation (run.step5.php).
        'step5' => [
            'utf8',
            'migrateToUtf8mb4',
            'checkAndSetInnoDBEngine',
            'api',
            'automatic_del',
            'cache',
            'cache_tree',
            'categories',
            'categories_folders',
            'categories_items',
            'defuse_passwords',
            'emails',
            'export',
            'files',
            'items',
            'items_change',
            'items_edition',
            'items_otp',
            'kb',
            'kb_categories',
            'kb_items',
            'kb_comments',
            'kb_edition',
            'ldap_groups_roles',
            'languages',
            'log_items',
            'log_system',
            'misc',
            'nested_tree',
            'notification',
            'otv',
            'background_tasks',
            'background_subtasks',
            'background_tasks_logs',
            'restriction_to_roles',
            'rights',
            'roles_title',
            'roles_values',
            'sharekeys_fields',
            'sharekeys_files',
            'sharekeys_items',
            'sharekeys_logs',
            'sharekeys_suggestions',
            'suggestion',
            'tags',
            'templates',
            'tokens',
            'users',
            'auth_failures',
            'items_importations',
            'user_private_keys',
            'users_groups',
            'users_groups_forbidden',
            'users_roles',
            'users_favorites',
            'users_latest_items',
            'users_options_favorites',
            'encryption_migration_stats',
            'websocket_events',
            'websocket_connections',
            'websocket_tokens',
            'network_acl',
            'items_corruption',
            'api_tokens',
            'api_sessions',
            'api_rate_limit',
            'import_tracking',
            'item_health',
            'user_nudges',
            'rotation_flags',
            'access_reviews',
            'access_review_items',
            'data_classification',
            'user_notifications',
            'emails_templates',
            'lapr_endpoints',
            'lapr_accounts',
            'lapr_policies',
            'lapr_audit_log',
            'lapr_rate_limit',
            'items_revisions',
            'api_idempotency',
            'webauthn_credentials',
            'sharekeys_webauthn',
            'user_webauthn_credentials',
            'secure_send_audit',
        ],
        // Finalisation: settings file, chmod, csrf, cron, cleanup (run.step6.php).
        'step6' => [
            'secureFile',
            'chmod',
            'settingsFile',
            'csrf',
            'cronJob',
            'cleanInstall',
        ],
    ];

    return $map[$step] ?? [];
}

/** Hold a shared setup lock for a write step so disaster recovery cannot run concurrently. */
function installAcquireWriteLock(): bool
{
    static $handle = null;
    if (is_resource($handle)) {
        return true;
    }
    $storage = TEAMPASS_ROOT . '/storage';
    $directory = $storage . '/recovery';
    if (is_link($storage) || is_link($directory)
        || (!is_dir($directory) && !@mkdir($directory, 0700))) {
        return false;
    }
    $path = $directory . '/operation.lock';
    if (is_link($path)) {
        return false;
    }
    $candidate = @fopen($path, 'c+b');
    if (!is_resource($candidate) || !@chmod($path, 0600) || !flock($candidate, LOCK_SH | LOCK_NB)) {
        if (is_resource($candidate)) {
            fclose($candidate);
        }
        return false;
    }
    if (is_file($directory . '/pending.json')) {
        flock($candidate, LOCK_UN);
        fclose($candidate);
        return false;
    }
    $handle = $candidate;
    return true;
}

/**
 * Tell whether an action is allow-listed for a given installer step.
 *
 * @param string $step   Step identifier.
 * @param string $action Action requested by the client.
 *
 * @return bool True when the action may be dispatched.
 */
function installIsActionAllowed(string $step, string $action): bool
{
    return in_array($action, installAllowedActions($step), true);
}

/**
 * Constant-time comparison of the install CSRF token.
 *
 * Both sides must be non-empty strings; a missing session token or a missing client token is
 * never a match. Prevents an unauthenticated request (which carries neither) from being treated
 * as a legitimate installer step.
 *
 * @param string|null $expected Token held in the install session.
 * @param string|null $provided Token sent by the client (X-CSRF-Token header).
 *
 * @return bool True when both are present and equal.
 */
function installCsrfTokenMatches(?string $expected, ?string $provided): bool
{
    if (!is_string($expected) || $expected === '' || !is_string($provided) || $provided === '') {
        return false;
    }

    return hash_equals($expected, $provided);
}
