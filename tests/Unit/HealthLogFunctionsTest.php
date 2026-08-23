<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * @file      HealthLogFunctionsTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/health.logs.functions.php';

class HealthLogFunctionsTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tp_health_logs_'
            . bin2hex(random_bytes(6));
        $this->assertTrue(mkdir($this->temporaryDirectory));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory . DIRECTORY_SEPARATOR . '*') ?: array() as $path) {
            if (is_file($path) === true) {
                unlink($path);
            }
        }
        if (is_dir($this->temporaryDirectory) === true) {
            rmdir($this->temporaryDirectory);
        }
    }

    public function testCurrentFileTailIsExactAndDoesNotReadRotation(): void
    {
        $path = $this->logPath();
        $this->writeLines($path, $this->numberedLines('current', 200));
        $this->writeLines($path . '.1', $this->numberedLines('rotation', 20));

        $result = tpHealthReadLogicalLog($path, 50);

        $this->assertSame($this->numberedLines('current', 50, 151), $result['lines']);
        $this->assertSame(array($path), $result['source_files']);
        $this->assertSame('lines_reached', $result['status']);
    }

    public function testEmptyCurrentFileFallsBackToFirstRotation(): void
    {
        $path = $this->logPath();
        file_put_contents($path, '');
        $this->writeLines($path . '.1', $this->numberedLines('rotation', 100));

        $result = tpHealthReadLogicalLog($path, 50);

        $this->assertSame($this->numberedLines('rotation', 50, 51), $result['lines']);
        $this->assertSame(array($path . '.1'), $result['source_files']);
        $this->assertSame('lines_reached', $result['status']);
    }

    public function testCurrentAndFirstRotationAreMergedChronologically(): void
    {
        $path = $this->logPath();
        $current = $this->numberedLines('current', 8);
        $rotation = $this->numberedLines('rotation', 100);
        $this->writeLines($path, $current);
        $this->writeLines($path . '.1', $rotation);

        $result = tpHealthReadLogicalLog($path, 50);

        $this->assertSame(array_merge(array_slice($rotation, -42), $current), $result['lines']);
        $this->assertCount(50, $result['lines']);
        $this->assertSame(array($path . '.1', $path), $result['source_files']);
    }

    public function testSeveralRotationsAreMergedInLogicalOrder(): void
    {
        $path = $this->logPath();
        $current = $this->numberedLines('current', 5);
        $rotationOne = $this->numberedLines('rotation-1', 10);
        $rotationTwo = $this->numberedLines('rotation-2', 100);
        $this->writeLines($path, $current);
        $this->writeLines($path . '.1', $rotationOne);
        $this->writeLines($path . '.2', $rotationTwo);

        $result = tpHealthReadLogicalLog($path, 50);

        $this->assertSame(
            array_merge(array_slice($rotationTwo, -35), $rotationOne, $current),
            $result['lines']
        );
        $this->assertSame(array($path . '.2', $path . '.1', $path), $result['source_files']);
    }

    public function testAllAvailableLinesAreReturnedWithoutAddingBoundaryLines(): void
    {
        $path = $this->logPath();
        $this->writeLines($path, array('current-a', 'current-b'), false);
        $this->writeLines($path . '.1', array('rotation-a', 'rotation-b'), false);

        $result = tpHealthReadLogicalLog($path, 20);

        $this->assertSame(
            array('rotation-a', 'rotation-b', 'current-a', 'current-b'),
            $result['lines']
        );
        $this->assertSame("rotation-a\nrotation-b\ncurrent-a\ncurrent-b", $result['content']);
        $this->assertSame('exhausted', $result['status']);
    }

    public function testMissingCurrentFileCanStillUseReadableRotation(): void
    {
        $path = $this->logPath();
        $this->writeLines($path . '.1', array('older', 'newer'), false);

        $result = tpHealthReadLogicalLog($path, 10);

        $this->assertSame(array('older', 'newer'), $result['lines']);
        $this->assertSame(array($path . '.1'), $result['source_files']);
        $this->assertSame('exhausted', $result['status']);
    }

    public function testOnlyExactNumericRotationNamesAreRead(): void
    {
        $path = $this->logPath();
        $this->writeLines($path, array('current'));
        $this->writeLines($path . '.1.backup', array('must-not-be-read'));

        $result = tpHealthReadLogicalLog($path, 10);

        $this->assertSame(array('current'), $result['lines']);
        $this->assertSame(array($path), $result['source_files']);
    }

    public function testGlobalReadLimitStopsBeforeOlderRotation(): void
    {
        $path = $this->logPath();
        file_put_contents($path, "old-current\n" . str_repeat('x', 64) . "\ncurrent-tail\n");
        $this->writeLines($path . '.1', array('rotation-must-not-be-mixed'));

        $result = tpHealthReadLogicalLog($path, 2, 20);

        $this->assertSame(array('current-tail'), $result['lines']);
        $this->assertSame(array($path), $result['source_files']);
        $this->assertSame('limit_reached', $result['status']);
        $this->assertSame(20, $result['bytes_read']);
    }

    public function testGzipRotationIsStreamedAndMerged(): void
    {
        if (function_exists('gzopen') === false || function_exists('gzwrite') === false) {
            self::markTestSkipped('The zlib extension is not available.');
        }

        $path = $this->logPath();
        $current = $this->numberedLines('current', 5);
        $rotationOne = $this->numberedLines('rotation-1', 10);
        $rotationTwo = $this->numberedLines('rotation-2', 100);
        $this->writeLines($path, $current);
        $this->writeLines($path . '.1', $rotationOne);
        $this->writeGzipLines($path . '.2.gz', $rotationTwo);

        $result = tpHealthReadLogicalLog($path, 50);

        $this->assertSame(
            array_merge(array_slice($rotationTwo, -35), $rotationOne, $current),
            $result['lines']
        );
        $this->assertSame(array($path . '.2.gz', $path . '.1', $path), $result['source_files']);
    }

    public function testUnreadableOrInvalidGzipFailsSafely(): void
    {
        $path = $this->logPath() . '.gz';
        file_put_contents($path, "\x1f\x8b\x08\x00truncated");

        $result = tpHealthReadLogFileTail($path, 10, 1024);

        $this->assertContains(
            $result['status'],
            array('read_error', 'gzip_unavailable', 'exhausted')
        );
        $this->assertLessThanOrEqual(1024, $result['bytes_read']);
    }

    public function testApacheCustomLogExtractionSeparatesFormatAndResolvesVariable(): void
    {
        $content = <<<'APACHE'
<VirtualHost *:443>
    CustomLog ${APACHE_LOG_DIR}/example-access.log combined
    CustomLog "${APACHE_LOG_DIR}/quoted access.log" vhost_combined
    CustomLog "|/usr/bin/rotatelogs /var/log/apache2/piped.%Y%m%d 86400" combined
</VirtualHost>
APACHE;

        $paths = tpHealthExtractApacheLogPathsFromContent(
            $content,
            'CustomLog',
            '/etc/apache2/sites-enabled/example.conf',
            array('/var/log/apache2')
        );

        $this->assertSame(
            array('/var/log/apache2/example-access.log', '/var/log/apache2/quoted access.log'),
            $paths
        );
    }

    public function testDebianApacheLogDirectoryResolvesTheDefaultEmptySuffix(): void
    {
        $envvars = <<<'ENVVARS'
if [ "${APACHE_CONFDIR##/etc/apache2-}" != "${APACHE_CONFDIR}" ] ; then
    SUFFIX="-${APACHE_CONFDIR##/etc/apache2-}"
else
    SUFFIX=
fi
export APACHE_LOG_DIR=/var/log/apache2$SUFFIX
ENVVARS;

        $this->assertSame(
            '/var/log/apache2',
            tpHealthResolveApacheLogDirFromEnvvars($envvars, '/etc/apache2')
        );
    }

    public function testDebianApacheLogDirectoryResolvesNamedInstanceSuffix(): void
    {
        $envvars = 'export APACHE_LOG_DIR=/var/log/apache2$SUFFIX';

        $this->assertSame(
            '/var/log/apache2-customer',
            tpHealthResolveApacheLogDirFromEnvvars($envvars, '/etc/apache2-customer')
        );
    }

    public function testApacheLogDirectoryRejectsUnknownShellExpressions(): void
    {
        $envvars = 'export APACHE_LOG_DIR=/srv/$CUSTOM_LOG_ROOT/apache2';

        $this->assertSame('', tpHealthResolveApacheLogDirFromEnvvars($envvars, '/etc/apache2'));
    }

    public function testApacheEnvvarsDiscoveryStartsWithTheDefaultInstance(): void
    {
        // Named instances declare their own APACHE_LOG_DIR; reading only the
        // default one resolves every instance to /var/log/apache2.
        $paths = tpHealthGetApacheEnvvarsPaths();

        $this->assertNotEmpty($paths);
        $this->assertSame('/etc/apache2/envvars', $paths[0]);
        $this->assertSame(array_values(array_unique($paths)), $paths);

        foreach ($paths as $path) {
            $this->assertStringStartsWith('/etc/apache2', $path);
            $this->assertStringEndsWith('/envvars', $path);
        }
    }

    public function testApacheDirectiveSkipsAnUnresolvedDirectoryCandidate(): void
    {
        $content = 'CustomLog ${APACHE_LOG_DIR}/teampass_access.log combined';

        $paths = tpHealthExtractApacheLogPathsFromContent(
            $content,
            'CustomLog',
            '/etc/apache2/sites-enabled/teampass.conf',
            array('/var/log/apache2$SUFFIX', '/var/log/apache2')
        );

        $this->assertSame(array('/var/log/apache2/teampass_access.log'), $paths);
    }

    public function testNginxAccessLogExtractionRejectsOffAndSyslogDestinations(): void
    {
        $content = <<<'NGINX'
server {
    access_log /var/log/nginx/example.access.log combined buffer=32k;
    access_log off;
    access_log syslog:server=unix:/dev/log combined;
}
NGINX;

        $paths = tpHealthExtractNginxLogPathsFromContent(
            $content,
            'access_log',
            '/etc/nginx/sites-enabled/example.conf'
        );

        $this->assertSame(array('/var/log/nginx/example.access.log'), $paths);
    }

    public function testSecureSendLinkSecretsAreRedactedFromAccessLogLines(): void
    {
        $line = '203.0.113.7 - - [17/Aug/2026:10:11:12 +0000] "GET /index.php?otv=1&code=abcdef0123456789&key=fedcba9876543210&stamp=1755422400 HTTP/1.1" 200 5120';

        $redacted = tpHealthRedactLogLine($line);

        $this->assertStringNotContainsString('abcdef0123456789', $redacted);
        $this->assertStringNotContainsString('fedcba9876543210', $redacted);
        $this->assertStringContainsString('code=[REDACTED]', $redacted);
        $this->assertStringContainsString('key=[REDACTED]', $redacted);
        // Everything useful for diagnostics survives.
        $this->assertStringContainsString('203.0.113.7', $redacted);
        $this->assertStringContainsString('/index.php?otv=1', $redacted);
        $this->assertStringContainsString('stamp=1755422400', $redacted);
        $this->assertStringContainsString('200 5120', $redacted);
    }

    public function testDownloadKeysAreRedactedIncludingInsideARefererField(): void
    {
        $line = '"GET /sources/downloadFile.php?name=a.pdf&key=1111aaaa&key_tmp=2222bbbb&fileid=42 HTTP/1.1" 200 12 "https://tp.example.com/index.php?page=items&key=3333cccc"';

        $redacted = tpHealthRedactLogLine($line);

        $this->assertStringNotContainsString('1111aaaa', $redacted);
        $this->assertStringNotContainsString('2222bbbb', $redacted);
        $this->assertStringNotContainsString('3333cccc', $redacted);
        $this->assertStringContainsString('key_tmp=[REDACTED]', $redacted);
        $this->assertStringContainsString('fileid=42', $redacted);
        $this->assertSame(2, substr_count($redacted, 'key=[REDACTED]'));
    }

    public function testRedactionLeavesOrdinaryLogLinesUntouched(): void
    {
        $line = '[Mon Aug 17 10:11:12 2026] [error] [client 203.0.113.7] PHP Warning: something failed in /var/www/index.php on line 12';

        $this->assertSame($line, tpHealthRedactLogLine($line));
        $this->assertSame('', tpHealthRedactLogLine(''));
    }

    public function testRedactionAppliesToEveryLineOfAnExcerpt(): void
    {
        $content = "GET /index.php?code=aaaa HTTP/1.1\nGET /index.php?key=bbbb HTTP/1.1\nplain line";

        $redacted = tpHealthRedactLogContent($content);

        $this->assertSame(
            "GET /index.php?code=[REDACTED] HTTP/1.1\nGET /index.php?key=[REDACTED] HTTP/1.1\nplain line",
            $redacted
        );
        $this->assertSame('', tpHealthRedactLogContent(''));
    }

    public function testGzipRotationLargerThanTheBudgetReportsLimitReachedInsteadOfEmpty(): void
    {
        // Reproduces a post-logrotate "compress" layout: the current file is
        // empty and the only data sits in an archive too large for the budget.
        $path = $this->logPath();
        file_put_contents($path, '');
        $this->writeGzipLines($path . '.1.gz', $this->numberedLines('rotation', 4000));

        $result = tpHealthReadLogicalLog($path, 50, 4096);

        $this->assertSame(array(), $result['lines']);
        $this->assertSame('limit_reached', $result['status']);
        // The caller must be able to tell this apart from a genuinely empty log.
        $this->assertNotSame('exhausted', $result['status']);
    }

    private function logPath(): string
    {
        return $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'application.log';
    }

    /**
     * @param list<string> $lines
     */
    private function writeLines(string $path, array $lines, bool $finalNewline = true): void
    {
        $content = implode("\n", $lines);
        if ($content !== '' && $finalNewline === true) {
            $content .= "\n";
        }
        $this->assertNotFalse(file_put_contents($path, $content));
    }

    /**
     * @param list<string> $lines
     */
    private function writeGzipLines(string $path, array $lines): void
    {
        $handle = gzopen($path, 'wb');
        $this->assertNotFalse($handle);
        $this->assertNotFalse(gzwrite($handle, implode("\n", $lines) . "\n"));
        gzclose($handle);
    }

    /**
     * @return list<string>
     */
    private function numberedLines(string $prefix, int $count, int $start = 1): array
    {
        $lines = array();
        for ($number = $start; $number < $start + $count; $number++) {
            $lines[] = $prefix . '-' . $number;
        }

        return $lines;
    }
}
