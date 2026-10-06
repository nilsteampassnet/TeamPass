<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * @file      DockerBackupDocumentationTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class DockerBackupDocumentationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'teampass-docker-doc-test-' . bin2hex(random_bytes(8));
        foreach (['secrets', 'storage/config', 'storage/files', 'storage/upload', 'storage/sk', 'source-upload'] as $path) {
            self::assertTrue(mkdir($this->root . '/' . $path, 0700, true));
        }
        $resolvedRoot = realpath($this->root);
        self::assertNotFalse($resolvedRoot);
        $this->root = $resolvedRoot;
    }

    protected function tearDown(): void
    {
        $root = realpath($this->root);
        $temporaryRoot = realpath(sys_get_temp_dir());
        if ($root === false || $temporaryRoot === false
            || str_starts_with($root, $temporaryRoot . DIRECTORY_SEPARATOR . 'teampass-docker-doc-test-') === false) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && $entry->isLink() === false) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($root);
    }

    /**
     * Return every shipped guide containing the default Docker state backup.
     *
     * @return array<string,array{0:string}>
     */
    public static function backupGuides(): array
    {
        return [
            'installation guide' => ['docs/install/docker.md'],
            'full Docker guide' => ['docs/DOCKER.md'],
            'Docker Hub README' => ['DOCKER-HUB-README.md'],
        ];
    }

    #[DataProvider('backupGuides')]
    public function testDocumentedArchiveContainsAttachmentsAndEveryDeclaredStateVolume(string $guide): void
    {
        $files = [
            'secrets/master.key', 'storage/config/settings.php', 'storage/files/import.tmp',
            'storage/upload/attachment.bin', 'storage/sk/legacy.key',
        ];
        foreach ($files as $file) {
            self::assertNotFalse(file_put_contents($this->root . '/' . $file, 'synthetic fixture, not a real secret'));
        }
        $source = preg_replace('/\\\\\n\s*/', ' ', $this->documentation($guide));
        self::assertSame(1, preg_match(
            '/(docker (?:exec teampass-app|compose exec -T teampass) tar -C \/var\/www\/html -czf - [^\n`]+ > teampass-state-[^\n`]+)/',
            (string) $source,
            $match
        ));
        // Substitute only Docker transport; run the documented tar arguments.
        $script = <<<'BASH'
set -eu
docker() {
    if [ "$1" = compose ]; then
        [ "$2" = exec ] && [ "$3" = -T ] && [ "$4" = teampass ] && [ "$5" = tar ] \
            && [ "$6" = -C ] && [ "$7" = /var/www/html ] || return 1
        shift 7
    else
        [ "$1" = exec ] && [ "$2" = teampass-app ] && [ "$3" = tar ] \
            && [ "$4" = -C ] && [ "$5" = /var/www/html ] || return 1
        shift 5
    fi
    tar -C "$TEAMPASS_DOC_TEST_ROOT" "$@"
}
BASH;
        $output = $this->runBash($script . "\n" . $match[1] . "\ntar -tzf teampass-state-*.tar.gz\n");
        $entries = preg_split('/\R/', trim($output)) ?: [];
        $dockerfile = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../Dockerfile'));
        self::assertSame(1, preg_match('/^VOLUME (\[.+\])$/m', $dockerfile, $volumeMatch));
        $volumes = json_decode($volumeMatch[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($volumes);
        foreach ($volumes as $volume) {
            self::assertStringStartsWith('/var/www/html/', $volume);
            self::assertContains(substr($volume, strlen('/var/www/html/')) . '/', $entries);
        }
        foreach ($files as $file) {
            self::assertContains($file, $entries);
        }
    }

    public function testDocumentedRecoveryRestoresAttachmentsToTheApplicationUploadPath(): void
    {
        $content = 'synthetic encrypted attachment';
        self::assertNotFalse(file_put_contents($this->root . '/source-upload/attachment.bin', $content));
        self::assertTrue(mkdir($this->root . '/recovered/storage/files', 0700, true));
        self::assertTrue(mkdir($this->root . '/recovered/storage/upload', 0700));
        $source = $this->documentation();
        self::assertSame(1, preg_match(
            '/(docker run --rm -v <attachments-volume>:[^\n]+\n\s*\| docker exec -i teampass-app tar -C [^\n]+)/',
            $source,
            $match
        ));
        $script = <<<'BASH'
set -euo pipefail
docker() {
    if [ "$1" = run ]; then
        [ "$2" = --rm ] && [ "$3" = -v ] && [ "$4" = fixture-attachments:/v:ro ] \
            && [ "$5" = alpine ] && [ "$6" = tar ] && [ "$7" = -C ] && [ "$8" = /v ] || return 1
        shift 8
        tar -C "$TEAMPASS_DOC_TEST_ROOT/source-upload" "$@"
    else
        [ "$1" = exec ] && [ "$2" = -i ] && [ "$3" = teampass-app ] \
            && [ "$4" = tar ] && [ "$5" = -C ] || return 1
        case "$6" in
            /var/www/html/storage/files|/var/www/html/storage/upload) ;;
            *) return 1 ;;
        esac
        target="$TEAMPASS_DOC_TEST_ROOT/recovered/${6#/var/www/html/}"
        shift 6
        tar -C "$target" "$@"
    fi
}
BASH;
        $this->runBash($script . "\n" . str_replace('<attachments-volume>', 'fixture-attachments', $match[1]) . "\n");
        self::assertFileExists($this->root . '/recovered/storage/upload/attachment.bin');
        self::assertSame($content, file_get_contents($this->root . '/recovered/storage/upload/attachment.bin'));
        self::assertFileDoesNotExist($this->root . '/recovered/storage/files/attachment.bin');
    }

    /**
     * Read the shipped Docker guide, normalizing Windows checkout line endings.
     */
    private function documentation(string $guide = 'docs/install/docker.md'): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../' . $guide));
    }

    /**
     * Run a fixture-only script with Docker replaced by local tar transport.
     */
    private function runBash(string $script): string
    {
        $bash = PHP_OS_FAMILY === 'Windows' ? 'C:/Program Files/Git/bin/bash.exe' : '/bin/bash';
        if (is_file($bash) === false || function_exists('proc_open') === false) {
            self::markTestSkipped('Bash is required for the documented tar commands.');
        }
        $environment = getenv();
        $environment['TEAMPASS_DOC_TEST_ROOT'] = str_replace('\\', '/', $this->root);
        $process = proc_open([$bash, '--noprofile', '--norc', '-s'], [
            0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
        ], $pipes, $this->root, $environment);
        self::assertIsResource($process);
        fwrite($pipes[0], $script);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $error);

        return (string) $output;
    }
}
