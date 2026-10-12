<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * No previous password may be kept in users.last_pw (GHSA-4462-cjq3-pcxv).
 *
 * changePassword() copied the submitted current password into users.last_pw verbatim, and the
 * sign-in loaded it into the session. The column is no longer written (except to empty it) or
 * read, and the 3.2.3 upgrade empties the values already stored.
 */
class LastPasswordNotStoredTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    /**
     * Every shipped PHP file, vendor directories excluded.
     *
     * @return array<string, string> relative path => source
     */
    private static function shippedSources(): array
    {
        $sources = [];
        foreach (['app', 'public'] as $base) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(self::ROOT . $base, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                $path = str_replace('\\', '/', $file->getPathname());
                if ($file->getExtension() !== 'php' || str_contains($path, '/vendor/')) {
                    continue;
                }
                $sources[substr($path, strlen(self::ROOT))] = (string) file_get_contents($path);
            }
        }

        return $sources;
    }

    public function testNoCodeWritesAValueIntoLastPw(): void
    {
        foreach (self::shippedSources() as $path => $source) {
            // Only the empty literal is allowed (installer and upgrade defaults).
            self::assertDoesNotMatchRegularExpression(
                '/[\'"]last_pw[\'"]\s*=>\s*+(?![\'"][\'"])/',
                $source,
                "$path writes a value into users.last_pw"
            );
        }
    }

    public function testNoCodeKeepsLastPwInTheSession(): void
    {
        foreach (self::shippedSources() as $path => $source) {
            self::assertDoesNotMatchRegularExpression(
                '/[\'"]user-last_pw[\'"]/',
                $source,
                "$path stores or reads users.last_pw through the session"
            );
        }
    }

    public function testUpgradeEmptiesTheStoredValues(): void
    {
        $upgrade = (string) file_get_contents(self::ROOT . 'public/install/upgrade_run_3.2.3.php');

        self::assertMatchesRegularExpression(
            '/UPDATE `" \. \$pre \. "users` SET `last_pw` = \'\'/',
            $upgrade,
            'upgrade_run_3.2.3.php must empty users.last_pw'
        );
    }
}
