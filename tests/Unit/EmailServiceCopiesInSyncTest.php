<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel test: the two EmailService copies must stay byte-identical.
 *
 * EmailService and EmailSettings exist in two locations (project rule — edit both):
 *   - app/vendor/teampassclasses/emailservice/src/                       (Composer / autoloaded)
 *   - app/includes/libraries/teampassclasses/emailservice/src/
 *
 * Only the vendor copy is autoloaded, so editing includes/libraries alone produces a
 * change with zero runtime effect. This test fails the build if they drift.
 */
class EmailServiceCopiesInSyncTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function emailServiceFilesProvider(): array
    {
        return [
            'EmailService.php' => ['EmailService.php'],
            'EmailSettings.php' => ['EmailSettings.php'],
        ];
    }

    /**
     * @dataProvider emailServiceFilesProvider
     */
    public function testEmailServiceCopiesAreByteIdentical(string $fileName): void
    {
        $root     = dirname(__DIR__, 2);
        $vendor   = $root . '/app/vendor/teampassclasses/emailservice/src/' . $fileName;
        $includes = $root . '/app/includes/libraries/teampassclasses/emailservice/src/' . $fileName;

        $this->assertFileExists($vendor, 'Composer copy of ' . $fileName . ' is missing');
        $this->assertFileExists($includes, 'includes/libraries copy of ' . $fileName . ' is missing');

        $this->assertSame(
            hash_file('sha256', $vendor),
            hash_file('sha256', $includes),
            'The two ' . $fileName . ' copies have diverged — edit both identically (see CLAUDE.md).'
        );
    }
}
