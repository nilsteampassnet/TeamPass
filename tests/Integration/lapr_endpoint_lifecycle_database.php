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
 * LAPR endpoint lifecycle checks against a disposable MySQL/MariaDB database:
 * deleting an endpoint releases its managed accounts, maintenance releases
 * the accounts earlier deletions left paused, and an endpoint whose SSH
 * credential is gone no longer holds its host:port.
 *
 * @file      lapr_endpoint_lifecycle_database.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

require_once __DIR__ . '/../../app/vendor/autoload.php';
require_once __DIR__ . '/../../app/vendor/sergeytsalkov/meekrodb/db.class.php';

$database = (string) getenv('TEAMPASS_TEST_DB');
if (!preg_match('/^teampass_test_[a-z0-9_]+$/', $database)) {
    throw new RuntimeException('A disposable teampass_test_* database is required.');
}
DB::$host = getenv('TEAMPASS_TEST_HOST') ?: '127.0.0.1';
DB::$port = (int) (getenv('TEAMPASS_TEST_PORT') ?: 3306);
DB::$user = getenv('TEAMPASS_TEST_USER') ?: 'root';
DB::$password = (string) getenv('TEAMPASS_TEST_PASSWORD');
DB::$dbName = $database;
DB::$encoding = 'utf8mb4';

$laprTestPrefix = 'lapr_life_' . bin2hex(random_bytes(4)) . '_';

/** Resolve a logical table name against the disposable prefix. */
function prefixTable(string $table): string
{
    return $GLOBALS['laprTestPrefix'] . $table;
}

require_once __DIR__ . '/../../app/sources/lapr.functions.php';

/** Fail explicitly even when PHP's assertions are disabled. */
function laprLifeExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** A non-admin operator session reading the given folders. */
function laprLifeSession(array $folders, int $admin = 0): Session
{
    $session = new Session(new MockArraySessionStorage());
    $session->set('user-admin', $admin);
    $session->set('user-accessible_folders', $folders);

    return $session;
}

/** Insert an item and return its id. */
function laprLifeItem(int $folderId, int $perso = 0, int $inactive = 0, ?string $deletedAt = null): int
{
    DB::insert(prefixTable('items'), [
        'label' => 'credential',
        'login' => 'root',
        'id_tree' => (string) $folderId,
        'perso' => $perso,
        'inactif' => $inactive,
        'deleted_at' => $deletedAt,
    ]);

    return (int) DB::insertId();
}

/** Insert an endpoint and return its id. */
function laprLifeEndpoint(string $hostname, int $port, int $credentialItemId, string $status = 'active'): int
{
    DB::insert(prefixTable('lapr_endpoints'), [
        'label' => $hostname,
        'hostname' => $hostname,
        'port' => $port,
        'ssh_username' => 'root',
        'ssh_credential_source' => $credentialItemId,
        'status' => $status,
        'created_by' => 1,
    ]);

    return (int) DB::insertId();
}

/** Insert a managed account and return its id. */
function laprLifeAccount(int $endpointId, int $itemId, string $status): int
{
    DB::insert(prefixTable('lapr_accounts'), [
        'endpoint_id' => $endpointId,
        'item_id' => $itemId,
        'username_cache' => 'user' . $itemId,
        'status' => $status,
        'created_by' => 1,
    ]);

    return (int) DB::insertId();
}

/** Current status of a row. */
function laprLifeStatus(string $table, int $id): string
{
    return (string) DB::queryFirstField('SELECT status FROM %b WHERE id = %i', prefixTable($table), $id);
}

$tables = ['items', 'lapr_endpoints', 'lapr_accounts', 'lapr_audit_log'];
try {
    // Build the tables from the real fresh-install DDL, without booting the installer.
    // eval() only evaluates this repository's own installer string literal.
    $installer = (string) file_get_contents(__DIR__ . '/../../public/install/install-steps/run.step5.php');
    $fixture = new class ($laprTestPrefix) {
        public array $inputData;
        /** Supply only the table-prefix input used by the real installer expressions. */
        public function __construct(string $prefix) { $this->inputData = ['tablePrefix' => $prefix]; }
    };
    foreach ($tables as $table) {
        laprLifeExpect(
            preg_match('/private function ' . $table . '\(\)[\s\S]*?DB::query\(\s*("[\s\S]*?")\s*\);/', $installer, $match) === 1,
            'Missing fresh-install schema for ' . $table
        );
        $sql = (function () use ($match): string { return eval('return ' . $match[1] . ';'); })->call($fixture);
        DB::query($sql);
    }

    // --- Deleting an endpoint releases every account it still holds ----------------
    $liveCredential = laprLifeItem(5);
    $deleted = laprLifeEndpoint('deleted.example', 22, $liveCredential, 'deleted');
    $active = laprLifeAccount($deleted, 101, 'active');
    $paused = laprLifeAccount($deleted, 102, 'paused');
    $error = laprLifeAccount($deleted, 103, 'error');
    $alreadyRemoved = laprLifeAccount($deleted, 104, 'deleted');

    laprLifeExpect(laprReleaseEndpointAccounts($deleted, 7, 'endpoint_deleted') === 3,
        'Every non-deleted account of a deleted endpoint must be released');
    foreach ([$active, $paused, $error, $alreadyRemoved] as $accountId) {
        laprLifeExpect(laprLifeStatus('lapr_accounts', $accountId) === 'deleted', 'Account ' . $accountId . ' still held');
    }
    $audit = DB::query('SELECT account_id, user_id, action_details FROM %b ORDER BY id', prefixTable('lapr_audit_log'));
    laprLifeExpect(array_map('intval', array_column($audit, 'account_id')) === [$active, $paused, $error],
        'Each released account must be audited once, the already removed one never');
    laprLifeExpect((int) $audit[0]['user_id'] === 7
        && json_decode((string) $audit[0]['action_details'], true) === ['deleted' => true, 'reason' => 'endpoint_deleted'],
        'The release audit must name the actor and the reason');
    laprLifeExpect(laprReleaseEndpointAccounts($deleted, 7, 'endpoint_deleted') === 0, 'A second release must be a no-op');

    // --- Maintenance releases what earlier deletions left paused -------------------
    $legacyDeleted = laprLifeEndpoint('legacy.example', 22, $liveCredential, 'deleted');
    $legacyPaused = laprLifeAccount($legacyDeleted, 201, 'paused');
    $stillEnrolled = laprLifeEndpoint('kept.example', 22, $liveCredential);
    $keptActive = laprLifeAccount($stillEnrolled, 202, 'active');
    $keptPaused = laprLifeAccount($stillEnrolled, 203, 'paused');

    laprLifeExpect(laprReleaseAccountsOfDeletedEndpoints(9999997) === 1, 'Only the orphan of the deleted endpoint is released');
    laprLifeExpect(laprLifeStatus('lapr_accounts', $legacyPaused) === 'deleted', 'The orphan account is still held');
    laprLifeExpect(laprLifeStatus('lapr_accounts', $keptActive) === 'active'
        && laprLifeStatus('lapr_accounts', $keptPaused) === 'paused',
        'Accounts of an enrolled endpoint must be left untouched');
    laprLifeExpect(laprReleaseAccountsOfDeletedEndpoints(9999997) === 0, 'Maintenance must be idempotent');

    // --- Target classification -----------------------------------------------------
    $deletedCredential = laprLifeItem(5, 0, 1, (string) time());
    $dead = laprLifeEndpoint('Debian.Example.', 22, $deletedCredential);
    $otherPort = laprLifeEndpoint('debian.example', 2222, $liveCredential);
    laprLifeEndpoint('debian.example', 22, $liveCredential, 'deleted');
    $operator = laprLifeSession([5]);

    $targets = laprFindEndpointTargets('debian.example', 22, $operator);
    laprLifeExpect(array_column($targets, 'id') === [$dead], 'Only the non-deleted endpoint on this port is a target');
    laprLifeExpect($targets[0]['credential_usable'] === false && $targets[0]['credential_readable'] === false,
        'A deleted credential is neither usable nor readable');
    laprLifeExpect(laprResolveEndpointTargetState($targets) === 'replaceable', 'A dead endpoint must not block enrollment');
    laprLifeExpect(laprReplaceableEndpointIds($targets) === [$dead], 'The dead endpoint is the one to replace');

    // Its managed account would make the managed item an invalid credential, unless
    // the enrollment replaces that endpoint and releases the account.
    $managedItem = laprLifeItem(5);
    laprLifeAccount($dead, $managedItem, 'paused');
    laprLifeExpect(laprEndpointCredentialConflict($managedItem, 'password') === 'managed_item',
        'Without replacement the managed item stays refused');
    laprLifeExpect(laprEndpointCredentialConflict($managedItem, 'password', [$dead]) === '',
        'The account of a replaced endpoint must not block the new credential');

    // --- A dead endpoint stays visible and deletable for its credential's readers --
    laprLifeExpect(laprGetUserEndpointIdsWithUnusableCredential($operator) === [$dead],
        'The operator who can read the credential folder sees the dead endpoint');
    $operational = laprGetUserAccessibleEndpointIds($operator);
    sort($operational);
    laprLifeExpect($operational === [$stillEnrolled, $otherPort], 'The dead endpoint must stay out of the operational scope');
    laprLifeExpect(laprUserCanDeleteEndpoint($dead, $operator) === true, 'The operator may delete the dead endpoint');
    laprLifeExpect(laprUserCanUseEndpoint($dead, $operator) === false, 'The operator may not operate the dead endpoint');
    $outsider = laprLifeSession([6]);
    laprLifeExpect(laprGetUserEndpointIdsWithUnusableCredential($outsider) === []
        && laprUserCanDeleteEndpoint($dead, $outsider) === false,
        'An operator who cannot read the credential folder must not see the dead endpoint');
    laprLifeExpect(laprGetUserEndpointIdsWithUnusableCredential(laprLifeSession([5], 1)) === [],
        'Administrators never reach operational endpoints');

    // --- A usable endpoint keeps its target ----------------------------------------
    $healthy = laprLifeEndpoint('debian.example', 22, $liveCredential);
    laprLifeExpect(laprResolveEndpointTargetState(laprFindEndpointTargets('debian.example', 22, $operator)) === 'enrolled',
        'An endpoint the operator can see keeps refusing the enrollment');
    laprLifeExpect(laprResolveEndpointTargetState(laprFindEndpointTargets('debian.example', 22, $outsider)) === 'enrolled_inaccessible',
        'An operator who cannot see the holder is told so');

    // --- Replacement retires only the endpoints whose credential is gone -----------
    $orphanEndpoint = laprLifeEndpoint('debian.example', 22, 999999);
    $personalEndpoint = laprLifeEndpoint('debian.example', 22, laprLifeItem(77, 1));
    $retired = laprRetireUnusableEndpointTargets('debian.example', 22, 7);
    sort($retired);
    laprLifeExpect($retired === [$dead, $orphanEndpoint, $personalEndpoint],
        'Deleted, missing and personal credentials are all replaceable');
    laprLifeExpect(laprLifeStatus('lapr_endpoints', $healthy) === 'active', 'A usable endpoint is never retired');
    laprLifeExpect((int) DB::queryFirstField('SELECT updated_by FROM %b WHERE id = %i', prefixTable('lapr_endpoints'), $dead) === 7,
        'The retirement records its actor');
    laprLifeExpect(laprRetireUnusableEndpointTargets('debian.example', 22, 7) === [], 'Retirement must be idempotent');

    // The write-time re-check leaves an endpoint whose credential came back
    // between the lookup and the write.
    $revived = laprLifeEndpoint('revived.example', 22, $deletedCredential);
    laprLifeExpect(laprResolveEndpointTargetState(laprFindEndpointTargets('revived.example', 22)) === 'replaceable',
        'The endpoint is replaceable when looked up');
    DB::update(prefixTable('items'), ['inactif' => 0, 'deleted_at' => null], 'id = %i', $deletedCredential);
    laprLifeExpect(laprRetireEndpointWithUnusableCredential($revived, 7) === false
        && laprLifeStatus('lapr_endpoints', $revived) === 'active',
        'An endpoint whose credential is usable again at write time must not be retired');
    laprLifeExpect(laprRetireUnusableEndpointTargets('revived.example', 22, 7) === [],
        'A usable endpoint is skipped before the write');

    echo "LAPR endpoint lifecycle checks passed.\n";
} finally {
    foreach ($tables as $table) {
        DB::query('DROP TABLE IF EXISTS %b', prefixTable($table));
    }
}
