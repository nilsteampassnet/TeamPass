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
        $commands = $this->tarCommands($this->documentation($guide), 'c');
        self::assertNotEmpty($commands, 'No state backup command found in ' . $guide);
        $dockerfile = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../Dockerfile'));
        self::assertSame(1, preg_match('/^VOLUME (\[.+\])$/m', $dockerfile, $volumeMatch));
        $volumes = json_decode($volumeMatch[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($volumes);
        foreach ($commands as $arguments) {
            self::assertContains('-C', $arguments);
            self::assertSame('/var/www/html', $arguments[array_search('-C', $arguments, true) + 1] ?? null);
            foreach ($volumes as $volume) {
                self::assertStringStartsWith('/var/www/html/', $volume);
                self::assertContains(substr($volume, strlen('/var/www/html/')), $arguments, $guide);
            }
        }
    }

    public function testDocumentedRecoveryRestoresAttachmentsToTheApplicationUploadPath(): void
    {
        $destinations = [];
        foreach ($this->tarCommands($this->documentation(), 'x') as $arguments) {
            $offset = array_search('-C', $arguments, true);
            if ($offset !== false) {
                $destinations[] = $arguments[$offset + 1] ?? '';
            }
        }
        self::assertContains('/var/www/html/storage/upload', $destinations);
        self::assertNotContains('/var/www/html/storage/files', $destinations);
    }

    public function testStaticChecksDoNotDependOnContainerNamesOrLineWrapping(): void
    {
        $source = "docker compose exec -T renamed-service tar -C '/var/www/html' -czf - \\\n"
            . "  secrets storage/config storage/files storage/upload storage/sk > arbitrary-name.tgz\n"
            . "docker run --rm -v volume:/v:ro alpine tar -C /v -cf - . | docker exec -i renamed-app "
            . "tar -C /var/www/html/storage/upload -xf -\n";
        $backups = $this->tarCommands($source, 'c');
        self::assertCount(1, $backups);
        self::assertContains('storage/upload', $backups[0]);
        $restores = $this->tarCommands($source, 'x');
        self::assertCount(1, $restores);
        self::assertContains('/var/www/html/storage/upload', $restores[0]);
    }

    /**
     * Read the shipped Docker guide, normalizing Windows checkout line endings.
     */
    private function documentation(string $guide = 'docs/install/docker.md'): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../' . $guide));
    }

    /**
     * Read tar arguments, independent of prose, transport/container names,
     * archive filenames and Markdown line wrapping. Never execute Markdown.
     *
     * @return array<int,array<int,string>>
     */
    private function tarCommands(string $source, string $operation): array
    {
        $source = preg_replace('/\\\\\n\s*/', ' ', $source);
        preg_match_all('/\btar\s+([^`\n|<>]+)([`|<>]|$)/m', (string) $source, $matches, PREG_SET_ORDER);
        $commands = [];
        foreach ($matches as $match) {
            // A tar stream piped into a restore is recovery transport, not a backup.
            if ($operation === 'c' && $match[2] === '|') {
                continue;
            }
            $arguments = array_map(static fn (string $argument): string => trim($argument, "\"'"),
                preg_split('/\s+/', trim($match[1])) ?: []);
            foreach ($arguments as $argument) {
                if (preg_match('/^-[a-zA-Z]*' . $operation . '[a-zA-Z]*$/', $argument) === 1) {
                    $commands[] = $arguments;
                    break;
                }
            }
        }

        return $commands;
    }
}
