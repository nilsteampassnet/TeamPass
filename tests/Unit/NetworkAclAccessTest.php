<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * This file is part of TeamPass, distributed under the GNU GPL version 3 or later.
 * Copyright 2009-2026 Teampass.net. See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class NetworkAclAccessTest extends TestCase
{
    private string $log;
    private string $previousLog;

    protected function setUp(): void
    {
        define('TEAMPASS_FUNCTIONS_ONLY', true);
        define('DB_PREFIX', 'acl_fixture_');
        require_once __DIR__ . '/../Fixtures/network_acl_db.php';
        require_once __DIR__ . '/../../app/sources/main.functions.php';
        $_SERVER['REMOTE_ADDR'] = '192.168.7.66';
        $_SERVER['SERVER_ADDR'] = '192.0.2.100';
        $this->log = tempnam(sys_get_temp_dir(), 'tp-acl-test-');
        $this->previousLog = (string) ini_set('error_log', $this->log);
    }

    protected function tearDown(): void
    {
        $log = (string) file_get_contents($this->log);
        ini_set('error_log', $this->previousLog);
        unlink($this->log);
        self::assertStringNotContainsString('database-credential-canary', $log);
    }

    /** Disabled policies do not query missing or broken ACL storage. */
    public function testDisabledPoliciesNeedNoAclTable(): void
    {
        DB::$failTableCheck = true;
        $decision = teampassGetNetworkAccessDecision([]);
        self::assertTrue($decision['allowed']);
        self::assertFalse($decision['checked']);
        self::assertNull($decision['context']);
        self::assertTrue(teampassEvaluateNetworkAclAccess([], null, [])['allowed']);
        self::assertSame(0, DB::$queries);
    }

    /** A required load performs one metadata check and one rules read, without a second check. */
    public function testEnabledPolicyChecksStorageOnlyOnce(): void
    {
        DB::$rules = [['type' => 'whitelist', 'rule_definition' => '192.168.7.0/24', 'enabled' => 1]];
        $decision = teampassGetNetworkAccessDecision(['network_whitelist_enabled' => 1]);
        self::assertTrue($decision['allowed']);
        self::assertSame('192.168.7.66', $decision['context']['detected_ip']);
        self::assertSame(2, DB::$queries);
    }

    /** @return iterable<string, array{array, string}> */
    public static function unavailablePolicies(): iterable
    {
        foreach (['network_whitelist_enabled', 'network_blacklist_enabled'] as $setting) {
            foreach (['tableExists', 'failTableCheck', 'failRules'] as $failure) {
                yield $setting . '-' . $failure => [[$setting => 1], $failure];
            }
        }
    }

    /** A configured policy must not disappear when its storage cannot be read. */
    #[DataProvider('unavailablePolicies')]
    public function testUnavailableEnabledPoliciesDenyAccess(array $settings, string $failure): void
    {
        DB::${$failure} = $failure !== 'tableExists';
        $decision = teampassGetNetworkAccessDecision($settings);
        self::assertTrue($decision['checked']);
        self::assertFalse($decision['allowed']);
        self::assertSame('acl_unavailable', $decision['reason']);
        self::assertNull($decision['matched_rule']);
        self::assertSame('192.168.7.66', $decision['context']['detected_ip']);
    }

    /** Direct callers of the evaluator also fail closed for a missing table. */
    public function testEvaluatorRefusesMissingStorage(): void
    {
        DB::$tableExists = false;
        $decision = teampassEvaluateNetworkAclAccess(['network_blacklist_enabled' => 1], '192.168.7.66', []);
        self::assertTrue($decision['checked']);
        self::assertFalse($decision['allowed']);
        self::assertSame('acl_unavailable', $decision['reason']);
    }

    /** Preserve blacklist priority and enabled-rule semantics. */
    public function testHealthyPoliciesKeepTheirDecisionOrder(): void
    {
        DB::$rules = [
            ['type' => 'whitelist', 'rule_definition' => '192.168.6.0/24', 'enabled' => 1],
            ['type' => 'blacklist', 'rule_definition' => '192.168.6.66', 'enabled' => 1],
        ];
        $settings = ['network_whitelist_enabled' => 1, 'network_blacklist_enabled' => 1];
        self::assertSame('whitelist_no_match', teampassGetNetworkAccessDecision($settings)['reason']);
        $_SERVER['REMOTE_ADDR'] = '192.168.6.66';
        self::assertSame('blacklist_match', teampassGetNetworkAccessDecision($settings)['reason']);
        DB::$rules[1]['enabled'] = 0;
        self::assertTrue(teampassGetNetworkAccessDecision($settings)['allowed']);
        DB::$rules[0]['enabled'] = 0;
        self::assertSame('whitelist_no_match', teampassGetNetworkAccessDecision($settings)['reason']);
        self::assertTrue(teampassGetNetworkAccessDecision(['network_blacklist_enabled' => 1])['allowed']);
    }

    /** An enabled policy rejects non-IPv4 clients rather than silently skipping them. */
    public function testUnsupportedClientAddressesAreDenied(): void
    {
        foreach (['2001:db8::1', '', 'invalid'] as $address) {
            $_SERVER['REMOTE_ADDR'] = $address;
            self::assertSame('invalid_client_ip', teampassGetNetworkAccessDecision(['network_blacklist_enabled' => 1])['reason']);
        }
    }
}
