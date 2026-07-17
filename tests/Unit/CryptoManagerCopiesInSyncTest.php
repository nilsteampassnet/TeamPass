<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel test: the two CryptoManager copies must stay byte-identical.
 *
 * CryptoManager exists in two locations (project rule — edit both):
 *   - app/vendor/teampassclasses/cryptomanager/src/CryptoManager.php       (Composer / autoloaded)
 *   - app/includes/libraries/teampassclasses/cryptomanager/src/CryptoManager.php
 *
 * This test fails the build if they drift, catching the classic "edited only one copy" mistake.
 */
class CryptoManagerCopiesInSyncTest extends TestCase
{
    public function testCryptoManagerCopiesAreByteIdentical(): void
    {
        $root     = dirname(__DIR__, 2);
        $vendor   = $root . '/app/vendor/teampassclasses/cryptomanager/src/CryptoManager.php';
        $includes = $root . '/app/includes/libraries/teampassclasses/cryptomanager/src/CryptoManager.php';

        $this->assertFileExists($vendor, 'Composer copy of CryptoManager is missing');
        $this->assertFileExists($includes, 'includes/libraries copy of CryptoManager is missing');

        $this->assertSame(
            hash_file('sha256', $vendor),
            hash_file('sha256', $includes),
            'The two CryptoManager copies have diverged — edit both identically (see CLAUDE.md).'
        );
    }
}
