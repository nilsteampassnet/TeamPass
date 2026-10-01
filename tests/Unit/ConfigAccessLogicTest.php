<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/config_access_logic.php';

/**
 * An unreadable app/config/ must never pass for a missing installation (issue #5380).
 */
class ConfigAccessLogicTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        $this->configDir = sys_get_temp_dir() . '/tp_config_access_' . bin2hex(random_bytes(6));
        mkdir($this->configDir, 0750);
        file_put_contents($this->configDir . '/include.php', '<?php');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->configDir) === false) {
            return;
        }
        chmod($this->configDir, 0750);
        foreach (['settings.php', 'include.php'] as $file) {
            if (file_exists($this->configDir . '/' . $file) === true) {
                chmod($this->configDir . '/' . $file, 0640);
                unlink($this->configDir . '/' . $file);
            }
        }
        rmdir($this->configDir);
    }

    /**
     * Permission bits do not restrict root, so the unreadable cases cannot be reproduced as root.
     */
    private function skipWhenPermissionsAreNotEnforced(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('POSIX permissions are not available on Windows.');
        }
        if (function_exists('posix_geteuid') === true && posix_geteuid() === 0) {
            self::markTestSkipped('Permission bits do not apply to root.');
        }
    }

    /**
     * Reads a repository file.
     */
    private function source(string $path): string
    {
        $content = file_get_contents(__DIR__ . '/../../' . $path);
        self::assertIsString($content, $path . ' must be readable');

        return $content;
    }

    public function testReadableSettingsMeanInstalled(): void
    {
        file_put_contents($this->configDir . '/settings.php', '<?php');

        self::assertSame('installed', teampassConfigState($this->configDir));
    }

    public function testMissingSettingsWithReadableCodeMeansNotInstalled(): void
    {
        self::assertSame('not_installed', teampassConfigState($this->configDir));
    }

    public function testUntraversableConfigDirectoryIsNotAMissingInstallation(): void
    {
        $this->skipWhenPermissionsAreNotEnforced();
        file_put_contents($this->configDir . '/settings.php', '<?php');
        // What rsync -a run as root leaves behind: 0750 kept, owner changed. For a
        // non-owner, that is the same as the owner losing every bit.
        chmod($this->configDir, 0000);

        self::assertSame('unreadable', teampassConfigState($this->configDir));
    }

    public function testExistingButUnreadableSettingsIsNotInstalledEither(): void
    {
        $this->skipWhenPermissionsAreNotEnforced();
        file_put_contents($this->configDir . '/settings.php', '<?php');
        chmod($this->configDir . '/settings.php', 0000);

        self::assertSame('unreadable', teampassConfigState($this->configDir));
    }

    public function testMissingShippedIncludeIsNotTakenForAFreshServer(): void
    {
        unlink($this->configDir . '/include.php');

        self::assertSame('unreadable', teampassConfigState($this->configDir));
    }

    public function testErrorPageWarnsAgainstTheInstallerWithoutDisclosingServerPaths(): void
    {
        $page = teampassConfigAccessErrorPage();

        self::assertStringContainsString('Do not run the installer', $page);
        self::assertStringContainsString('chown', $page);
        // The shell continuation must survive the PHP string: one backslash, then a newline.
        self::assertStringContainsString("secrets \\\n", $page);
        self::assertStringNotContainsString(dirname(__DIR__, 2), $page);
        self::assertStringNotContainsString('/var/www', $page);
    }

    public function testMissingSettingsPageKeepsTheAdministratorAwayFromTheInstaller(): void
    {
        $page = teampassMissingSettingsPage();

        self::assertStringContainsString('<title>TeamPass cannot find its configuration</title>', $page);
        self::assertStringContainsString('do not run the installer', $page);
        self::assertStringContainsString('migrate_3.2.x.php', $page);
        self::assertStringContainsString('docker volume prune', $page);
        self::assertStringContainsString('#/install/docker?id=recovering-a-lost-configuration', $page);
        self::assertStringNotContainsString(dirname(__DIR__, 2), $page);
    }

    public function testInstallerAcceptsADatabaseWithoutTeampassSchema(): void
    {
        self::assertFalse(teampassInstallerMustRefuseDatabase(null));
    }

    /**
     * An installation that failed at its last step leaves the whole schema behind; the
     * administrator must be able to start again without dropping tables by hand.
     */
    public function testInstallerLetsAnInterruptedInstallationBeRetried(): void
    {
        self::assertFalse(teampassInstallerMustRefuseDatabase(['signed_in_users' => 0, 'items' => 0]));
    }

    public function testInstallerRefusesAnInstanceInUse(): void
    {
        self::assertTrue(teampassInstallerMustRefuseDatabase(['signed_in_users' => 1, 'items' => 0]));
        self::assertTrue(teampassInstallerMustRefuseDatabase(['signed_in_users' => 0, 'items' => 12]));
    }

    public function testRefusalMessageEscapesWhatTheAdministratorTyped(): void
    {
        $message = teampassInstallerRefusalMessage('3.2.1<b>', 'tp_<script>');

        self::assertStringContainsString('a TeamPass 3.2.1&lt;b&gt; instance', $message);
        self::assertStringContainsString('<code>tp_&lt;script&gt;</code>', $message);
        self::assertStringNotContainsString('<script>', $message);
        self::assertStringContainsString('new encryption key', $message);
        self::assertStringContainsString('another table prefix', $message);
        self::assertStringContainsString('a TeamPass instance in use', teampassInstallerRefusalMessage('', 'teampass_'));
    }

    /**
     * Without settings.php, loadClasses() is fatal: the wizard must answer before it.
     */
    public function testUpgradeWizardExplainsAMissingSettingsFileBeforeLoadingClasses(): void
    {
        $source = $this->source('public/install/upgrade.php');
        $explain = strpos($source, 'teampassSendMissingSettingsError(');
        $load = strpos($source, 'loadClasses();');

        self::assertNotFalse($explain, 'upgrade.php must explain a missing settings.php');
        self::assertNotFalse($load, 'upgrade.php no longer calls loadClasses(); update this test');
        self::assertLessThan($load, $explain);
    }

    /**
     * The probe must run before step 3 writes anything, and on the database the
     * administrator chose.
     */
    public function testInstallerProbesTheDatabaseBeforeWritingToIt(): void
    {
        $source = $this->source('public/install/install-steps/run.step3.php');
        $useDb = strpos($source, "DB::useDB(\$inputData['dbName']);");
        $guard = strpos($source, 'teampassInstallerMustRefuseDatabase(');
        $firstWrite = strpos($source, 'CREATE TABLE IF NOT EXISTS `_install`');

        self::assertNotFalse($useDb);
        self::assertNotFalse($guard, 'run.step3.php must refuse a database in use');
        self::assertNotFalse($firstWrite, 'run.step3.php no longer creates _install; update this test');
        self::assertLessThan($guard, $useDb);
        self::assertLessThan($firstWrite, $guard);
    }

    /**
     * The upgrade wizard page and the installer refusal both send administrators to
     * this section; a renamed heading would silently break the link.
     */
    public function testRecoveryDocumentationLinkedFromTheCodeExists(): void
    {
        self::assertMatchesRegularExpression(
            '/^## Recovering a lost configuration$/m',
            $this->source('docs/install/docker.md')
        );
    }

    /**
     * Administrators paste the documented recovery script as is: it must parse, and it
     * must keep its safety checks (one key file, key verified against the database,
     * existing files never overwritten).
     */
    public function testDocumentedRecoveryScriptParsesAndKeepsItsSafetyChecks(): void
    {
        $docs = $this->source('docs/install/docker.md');
        $section = substr($docs, (int) strpos($docs, '## Recovering a lost configuration'));
        self::assertSame(1, preg_match('/```php\n(.*?)```/s', $section, $match), 'recovery script not found');
        $script = $match[1];

        token_get_all($script, TOKEN_PARSE);
        self::assertStringContainsString('count($keyFiles) !== 1', $script);
        self::assertStringContainsString('Defuse\Crypto\Crypto::decrypt($row[0], $key)', $script);
        self::assertStringContainsString("if (file_exists(\$config . '/settings.php') === false)", $script);
        self::assertStringContainsString("if (file_exists(\$config . '/csrfp.config.php') === false)", $script);
    }

    /**
     * Each entry point must decide before loading anything from app/config/: past that
     * point, an unreadable directory is a fatal error or a redirect to the installer.
     */
    public function testEntryPointsCheckConfigAccessBeforeUsingAppConfig(): void
    {
        $entryPoints = [
            'public/index.php' => "header('Location: /install/install.php')",
            'public/install/upgrade.php' => "require_once __DIR__.'/../../app/config/include.php'",
            'public/install/install.php' => "include TEAMPASS_ROOT . '/app/config/include.php'",
        ];
        foreach ($entryPoints as $path => $firstUse) {
            $source = $this->source($path);
            $check = strpos($source, 'teampassConfigState(');
            $use = strpos($source, $firstUse);

            self::assertNotFalse($check, $path . ' must call teampassConfigState()');
            self::assertNotFalse($use, $path . ' no longer contains ' . $firstUse . '; update this test');
            self::assertLessThan($use, $check, $path . ' must check config access before ' . $firstUse);
            self::assertStringContainsString('teampassSendConfigAccessError(', $source, $path);
        }
    }

    /**
     * install.php lives in public/install/: one "../" lands in public/, where no app/ exists,
     * so the "Existing installation detected" warning could never show.
     */
    public function testInstallerExistingInstallationGuardTargetsTheRealConfigDirectory(): void
    {
        $source = $this->source('public/install/install.php');

        self::assertStringNotContainsString("__DIR__ . '/../app/config/settings.php'", $source);
        self::assertStringContainsString("file_exists(TEAMPASS_ROOT . '/app/config/settings.php')", $source);
    }

    /**
     * Run as root, rsync -a re-owns every directory shipped in the archive to root.
     */
    public function testDocumentedRsyncUpgradeKeepsDirectoryOwners(): void
    {
        $lines = preg_grep('/^\s*rsync\s+-a/', explode("\n", $this->source('docs/install/upgrade.md')));
        self::assertNotEmpty($lines, 'docs/install/upgrade.md no longer documents rsync; update this test');

        foreach ($lines as $line) {
            self::assertStringContainsString('--no-owner', $line);
            self::assertStringContainsString('--no-group', $line);
        }
    }
}
