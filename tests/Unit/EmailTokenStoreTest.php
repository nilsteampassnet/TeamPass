<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use TeampassClasses\EmailService\DatabaseEmailTokenStore;
use TeampassClasses\EmailService\EmailTransportException;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EmailTokenStoreTest extends TestCase
{
    private const REVISION = '11111111111111111111111111111111';
    private const OWNER_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const OWNER_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private string $keyFile = '';
    private int $expiresAt;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('The SQLite extension used in CI is required.');
        }
        require __DIR__ . '/../Fixtures/email_token_store_db.php';
        $this->expiresAt = time() + 3600;
        $this->keyFile = tempnam(sys_get_temp_dir(), 'teampass-email-key-');
        file_put_contents($this->keyFile, Key::createNewRandomKey()->saveToAsciiSafeString());
        define('TEAMPASS_FUNCTIONS_ONLY', true);
        define('TEAMPASS_SECRETS', dirname($this->keyFile));
        define('SECUREFILE', basename($this->keyFile));
        define('DB_PREFIX', 'fixture_token_');
        require_once dirname(__DIR__, 2) . '/app/sources/main.functions.php';
        DB::$connection = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        DB::$connection->exec('CREATE TABLE fixture_token_misc (type TEXT, intitule TEXT, valeur TEXT)');
        DB::$connection->exec('CREATE TABLE fixture_token_email_oauth_state (
            cache_key TEXT PRIMARY KEY, config_revision TEXT, access_token TEXT, refresh_token TEXT,
            access_token_hash TEXT, expires_at INTEGER, lease_owner TEXT, lease_until INTEGER, updated_at INTEGER)');
        DB::query('INSERT INTO fixture_token_misc VALUES (%s, %s, %s)', 'admin', 'email_config_revision', self::REVISION);
    }

    protected function tearDown(): void
    {
        if ($this->keyFile !== '') {
            unlink($this->keyFile);
        }
    }

    /** Both secrets are ciphertext before any SQL call and round-trip through the instance key. */
    public function testTokensAreEncryptedBeforePersistence(): void
    {
        $store = new DatabaseEmailTokenStore(self::REVISION);
        self::assertTrue($store->claim('cache', self::OWNER_A, 60));
        self::assertTrue($store->save('cache', self::OWNER_A, $this->token()));
        $row = DB::$connection->query('SELECT * FROM fixture_token_email_oauth_state')->fetch(PDO::FETCH_ASSOC);
        self::assertTrue(tpIsInstanceKeyCiphertext($row['access_token']));
        self::assertTrue(tpIsInstanceKeyCiphertext($row['refresh_token']));
        $sqlArguments = json_encode(DB::$arguments);
        self::assertStringNotContainsString('private-access-token', $sqlArguments);
        self::assertStringNotContainsString('private-refresh-token', $sqlArguments);
        self::assertSame($this->token(), $store->load('cache'));
    }

    /** An expired lease cannot overwrite or release a newer worker's lease. */
    public function testReplacedOwnershipCannotSaveOrRelease(): void
    {
        $store = new DatabaseEmailTokenStore(self::REVISION);
        self::assertTrue($store->claim('cache', self::OWNER_A, 60));
        self::assertFalse($store->claim('cache', self::OWNER_B, 60));
        DB::$connection->exec('UPDATE fixture_token_email_oauth_state SET lease_until = 0');
        self::assertTrue($store->claim('cache', self::OWNER_B, 60));
        self::assertFalse($store->save('cache', self::OWNER_A, $this->token()));
        $store->release('cache', self::OWNER_A);
        self::assertFalse($store->claim('cache', self::OWNER_A, 60));
        self::assertTrue($store->save('cache', self::OWNER_B, $this->token()));
    }

    /** A configuration change during token acquisition cannot revive old credentials. */
    public function testConfigurationChangeRefusesAnInFlightSaveAndSubsequentReads(): void
    {
        $store = new DatabaseEmailTokenStore(self::REVISION);
        self::assertTrue($store->claim('cache', self::OWNER_A, 60));
        DB::query('UPDATE fixture_token_misc SET valeur = %s', str_repeat('2', 32));
        self::assertFalse($store->save('cache', self::OWNER_A, $this->token()));
        $this->expectException(EmailTransportException::class);
        $store->load('cache');
    }

    /** An old 401 cannot delete a newly renewed access token or its refresh token. */
    public function testInvalidationTargetsOnlyTheObservedAccessToken(): void
    {
        $store = new DatabaseEmailTokenStore(self::REVISION);
        self::assertTrue($store->claim('cache', self::OWNER_A, 60));
        self::assertTrue($store->save('cache', self::OWNER_A, $this->token()));
        $store->invalidateAccess('cache', 'stale-access-token');
        self::assertSame('private-access-token', $store->load('cache')['access_token']);
        $store->invalidateAccess('cache', 'private-access-token');
        $state = $store->load('cache');
        self::assertSame('', $state['access_token']);
        self::assertSame('private-refresh-token', $state['refresh_token']);
    }

    /** New OAuth storage must never inherit the legacy SMTP plaintext tolerance. */
    public function testPlaintextTokenRowsAreRefused(): void
    {
        $store = new DatabaseEmailTokenStore(self::REVISION);
        $store->claim('cache', self::OWNER_A, 60);
        DB::$connection->exec("UPDATE fixture_token_email_oauth_state SET access_token = 'plaintext'");
        $this->expectException(EmailTransportException::class);
        $store->load('cache');
    }

    /** @return array{access_token:string, refresh_token:string, expires_at:int} */
    private function token(): array
    {
        return ['access_token' => 'private-access-token', 'refresh_token' => 'private-refresh-token', 'expires_at' => $this->expiresAt];
    }
}
