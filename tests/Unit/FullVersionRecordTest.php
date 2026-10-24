<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: every path that records teampass_version also records the complete release.
 *
 * teampass_version holds TP_VERSION only ("3.2.2"), so a database recorded by 3.2.2.3 and one
 * recorded by 3.2.2.8 looked identical — which hid a skipped migration on a Docker upgrade.
 * teampass_version_full keeps "3.2.2.3"; the installer, the web wizard and the Docker helper
 * must write it together with teampass_version, or it silently goes stale.
 */
final class FullVersionRecordTest extends TestCase
{
    private static function read(string $path): string
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertNotSame('', $source, $path . ' not found');

        return $source;
    }

    public function testInstallerSeedsTheCompleteRelease(): void
    {
        $this->assertStringContainsString(
            "array('admin', 'teampass_version_full', TP_VERSION . '.' . TP_VERSION_MINOR),",
            self::read('public/install/install-steps/run.step5.php')
        );
    }

    public function testWizardRecordsTheCompleteReleaseWithTheVersion(): void
    {
        $wizard = self::read('public/install/upgrade_ajax.php');
        $version = strpos($wizard, "WHERE intitule = 'teampass_version' AND type = 'admin';");
        $full = strpos($wizard, "'teampass_version_full', '\".TP_VERSION.\".\".TP_VERSION_MINOR.\"'");
        $cacheFlush = strpos($wizard, 'ConfigManager::invalidateCache();', (int) $version);

        $this->assertIsInt($version);
        $this->assertIsInt($full, 'the wizard must insert teampass_version_full');
        $this->assertStringContainsString(
            "SET `valeur` = '\".TP_VERSION.\".\".TP_VERSION_MINOR.\"'\n                    WHERE intitule = 'teampass_version_full'",
            $wizard,
            'the wizard must update an existing teampass_version_full'
        );
        // Written after the version and before the settings cache is flushed.
        $this->assertGreaterThan($version, $full);
        $this->assertLessThan((int) $cacheFlush, $full);
    }

    public function testDockerHelperRecordsTheCompleteReleaseOnceTheChainReachesTheFiles(): void
    {
        $writer = self::read('app/scripts/write-db-version.php');

        $this->assertStringContainsString(
            "if (\$ok === true && \$version === TP_VERSION && defined('TP_VERSION_MINOR') === true) {",
            $writer
        );
        $this->assertStringContainsString("TP_VERSION . '.' . TP_VERSION_MINOR", $writer);
        // Never an upsert: the misc unique key only exists once the wizard deduplicated the table.
        $this->assertStringNotContainsString('ON DUPLICATE KEY', $writer);
    }

    public function testDockerHelperReadsTheCompleteReleaseOnRequest(): void
    {
        $reader = self::read('app/scripts/read-db-version.php');

        $this->assertStringContainsString("in_array('--full', \$argv ?? [], true)", $reader);
        $this->assertStringContainsString("\$settingKeys = ['teampass_version_full'];", $reader);
    }
}
