<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel test: the two OAuth2Controller copies must stay byte-identical.
 *
 * OAuth2Controller exists in two locations (project rule — edit both):
 *   - app/vendor/teampassclasses/oauth2controller/src/OAuth2Controller.php       (Composer / autoloaded)
 *   - app/includes/libraries/teampassclasses/oauth2controller/src/OAuth2Controller.php
 *
 * PHPStan excludes both directories, so a call to a method that exists in only one copy
 * reaches production unnoticed: PR #5445 shipped such a typo in the autoloaded copy, which
 * turned every OAuth2 callback (Entra included) into an uncaught Error.
 */
class OAuth2ControllerCopiesInSyncTest extends TestCase
{
    public function testOAuth2ControllerCopiesAreByteIdentical(): void
    {
        $root     = dirname(__DIR__, 2);
        $vendor   = $root . '/app/vendor/teampassclasses/oauth2controller/src/OAuth2Controller.php';
        $includes = $root . '/app/includes/libraries/teampassclasses/oauth2controller/src/OAuth2Controller.php';

        $this->assertFileExists($vendor, 'Composer copy of OAuth2Controller is missing');
        $this->assertFileExists($includes, 'includes/libraries copy of OAuth2Controller is missing');

        $this->assertSame(
            hash_file('sha256', $vendor),
            hash_file('sha256', $includes),
            'The two OAuth2Controller copies have diverged — edit both identically (see CLAUDE.md).'
        );
    }
}
