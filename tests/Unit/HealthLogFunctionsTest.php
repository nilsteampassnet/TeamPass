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
