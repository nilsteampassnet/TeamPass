<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file RecoveryPublicationTest.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/recovery.functions.php';

final class RecoveryPublicationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/tp-publication-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
        foreach (['app/config', 'app/includes/libraries/csrfp/libs', 'storage/config'] as $directory) {
            tpRecoveryDestinationDirectory($this->root, $directory, true);
        }
    }

    protected function tearDown(): void
    {
        $remove = static function (string $path) use (&$remove): void {
            if (is_dir($path) && !is_link($path)) {
                chmod($path, 0700);
                foreach (scandir($path) ?: [] as $name) {
                    if ($name !== '.' && $name !== '..') { $remove($path . '/' . $name); }
                }
                rmdir($path);
            } else {
                if (!is_link($path)) { @chmod($path, 0600); }
                unlink($path);
            }
        };
        $remove($this->root);
    }

    public function testPreflightCreatesOnlyEmptyRuntimeDirectoriesWithoutLeavingProbeFiles(): void
    {
        tpRecoveryPublicationPreflight($this->root);
        foreach (['secrets', 'storage/upload', 'storage/files/kb_attachments', 'public/assets/avatars'] as $relative) {
            self::assertSame(['.', '..'], scandir($this->root . '/' . $relative));
        }
        self::assertSame(['.', '..'], scandir($this->root . '/app/config'));
        self::assertFileDoesNotExist($this->root . '/app/config/settings.php');
    }

    public function testPreflightReportsAServiceAccountWithoutWriteAccessBeforePublication(): void
    {
        if (PHP_OS_FAMILY === 'Windows' || (function_exists('posix_geteuid') && posix_geteuid() === 0)) {
            self::markTestSkipped('Requires POSIX permissions and a non-root service account');
        }
        chmod($this->root . '/app/config', 0500);
        $this->expectExceptionMessage('RECOVERY_DESTINATION_UNWRITABLE');
        tpRecoveryPublicationPreflight($this->root);
    }

    public function testRetryAcceptsAnIdenticalPublishedFileWithoutChangingItsPermissions(): void
    {
        $path = $this->root . '/app/config/settings.php';
        tpRecoveryPublishFile($path, 'synthetic destination settings');
        chmod($path, 0400);
        clearstatcache();
        $mode = fileperms($path);
        tpRecoveryPublishFile($path, 'synthetic destination settings');
        clearstatcache();
        self::assertSame($mode, fileperms($path));
        self::assertSame('synthetic destination settings', file_get_contents($path));
    }

    public function testPublicationNeverOverwritesADifferentExistingFile(): void
    {
        $path = $this->root . '/app/config/settings.php';
        file_put_contents($path, 'unrelated installation');
        try {
            tpRecoveryPublishFile($path, 'synthetic destination settings');
            self::fail('A conflicting file was replaced');
        } catch (TeamPassRecoveryException $exception) {
            self::assertSame('RECOVERY_PUBLICATION_CONFLICT', $exception->reason);
        }
        self::assertSame('unrelated installation', file_get_contents($path));
    }

    public function testInitialCsrfConfigurationCanBeReplacedOnlyWhileItsRecordedHashStillMatches(): void
    {
        $path = $this->root . '/app/includes/libraries/csrfp/libs/csrfp.config.php';
        file_put_contents($path, 'shipped initial CSRF configuration');
        $baseline = hash_file('sha256', $path);
        tpRecoveryPublishFile($path, 'new recovery CSRF configuration', $baseline);
        tpRecoveryPublishFile($path, 'new recovery CSRF configuration', $baseline);
        self::assertSame('new recovery CSRF configuration', file_get_contents($path));
        file_put_contents($path, 'configuration changed outside recovery');
        $this->expectExceptionMessage('RECOVERY_PUBLICATION_CONFLICT');
        tpRecoveryPublishFile($path, 'new recovery CSRF configuration', $baseline);
    }

    public function testInterruptedPublicationAcceptsOnlyItsOwnMatchingDocumentsAndMasterKey(): void
    {
        tpRecoveryPublicationPreflight($this->root);
        $job = ['kit' => ['secure_name' => 'synthetic.key'],
            'publication' => ['master_sha256' => hash('sha256', 'synthetic key')],
            'manifest' => ['document_entries' => [['kind' => 'item_attachment',
                'stored_name' => 'synthetic.bin', 'sha256' => hash('sha256', 'synthetic attachment')]]]];
        file_put_contents($this->root . '/secrets/synthetic.key', 'synthetic key');
        file_put_contents($this->root . '/storage/upload/synthetic.bin', 'synthetic attachment');
        tpRecoveryAssertPublicationData($this->root, $job);
        self::assertSame('synthetic key', file_get_contents($this->root . '/secrets/synthetic.key'));
        file_put_contents($this->root . '/storage/upload/synthetic.bin', 'different attachment');
        $this->expectExceptionMessage('RECOVERY_PUBLICATION_CONFLICT');
        tpRecoveryAssertPublicationData($this->root, $job);
    }

    public function testInterruptedPublicationRejectsAnUnrelatedDestinationFile(): void
    {
        tpRecoveryPublicationPreflight($this->root);
        file_put_contents($this->root . '/storage/upload/unrelated.bin', 'unrelated');
        $this->expectExceptionMessage('RECOVERY_PUBLICATION_CONFLICT');
        tpRecoveryAssertPublicationData($this->root, ['kit' => ['secure_name' => 'synthetic.key'],
            'publication' => ['master_sha256' => hash('sha256', 'synthetic key')], 'manifest' => []]);
    }

    public function testAJobCanResumeAnInterruptedMasterKeyTemporaryWrite(): void
    {
        tpRecoveryPublicationPreflight($this->root);
        $id = str_repeat('a', 32);
        $path = $this->root . '/secrets/synthetic.key';
        $temporary = tpRecoveryPublicationTemporary($path, $id);
        file_put_contents($temporary, 'partial original master key');
        $job = ['id' => $id, 'kit' => ['secure_name' => 'synthetic.key'],
            'publication' => ['master_sha256' => hash('sha256', 'synthetic key')], 'manifest' => []];
        tpRecoveryAssertPublicationData($this->root, $job);
        tpRecoveryPublishFile($path, 'synthetic key', null, $id);
        self::assertSame('synthetic key', file_get_contents($path));
        self::assertFileDoesNotExist($temporary);
    }

    public function testAJobNeverRemovesAnotherJobsTemporaryFile(): void
    {
        tpRecoveryPublicationPreflight($this->root);
        $path = $this->root . '/secrets/synthetic.key';
        $foreign = tpRecoveryPublicationTemporary($path, str_repeat('b', 32));
        file_put_contents($foreign, 'unrelated recovery operation');
        try {
            tpRecoveryAssertPublicationData($this->root, ['id' => str_repeat('a', 32),
                'kit' => ['secure_name' => 'synthetic.key'],
                'publication' => ['master_sha256' => hash('sha256', 'synthetic key')], 'manifest' => []]);
            self::fail('An unrelated temporary was accepted');
        } catch (TeamPassRecoveryException $exception) {
            self::assertSame('RECOVERY_PUBLICATION_CONFLICT', $exception->reason);
        }
        self::assertSame('unrelated recovery operation', file_get_contents($foreign));
    }

    public function testDocumentPublicationResumesPartialTemporaryWritesAndKeepsIdenticalDocuments(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZIP extension required');
        }
        tpRecoveryPublicationPreflight($this->root);
        $id = str_repeat('c', 32);
        $path = $this->root . '/storage/upload/synthetic.bin';
        $temporary = tpRecoveryPublicationTemporary($path, $id);
        file_put_contents($temporary, 'partial attachment');
        $zip = new ZipArchive();
        $zipPath = $this->root . '/synthetic.zip';
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('documents/item_attachments/synthetic.bin', 'synthetic attachment');
        $zip->close();
        $job = ['id' => $id, 'manifest' => ['document_entries' => [['kind' => 'item_attachment',
            'stored_name' => 'synthetic.bin', 'package_path' => 'documents/item_attachments/synthetic.bin',
            'sha256' => hash('sha256', 'synthetic attachment')]]]];
        $settings = ['path_to_upload_folder' => $this->root . '/storage/upload'];
        tpRecoveryPublishDocuments($zipPath, $job, $settings);
        self::assertSame('synthetic attachment', file_get_contents($path));
        self::assertFileDoesNotExist($temporary);
        tpRecoveryPublishDocuments($zipPath, $job, $settings);
        self::assertSame('synthetic attachment', file_get_contents($path));
    }

    public function testDockerConfigurationLinksWriteToPersistentTargetsAndKeepTheLinks(): void
    {
        if (PHP_OS_FAMILY === 'Windows') { self::markTestSkipped('Windows symlinks require additional privileges'); }
        foreach (['settings.php' => 'app/config', 'csrfp.config.php' => 'app/includes/libraries/csrfp/libs'] as $name => $directory) {
            $path = $this->root . '/' . $directory . '/' . $name;
            $target = $this->root . '/storage/config/' . $name;
            symlink($target, $path);
            self::assertSame($target, tpRecoveryConfigDestination($this->root, $name));
            tpRecoveryPublishFile(tpRecoveryConfigDestination($this->root, $name), 'synthetic persistent ' . $name);
            self::assertTrue(is_link($path));
            self::assertSame('synthetic persistent ' . $name, file_get_contents($target));
        }
        tpRecoveryPublicationPreflight($this->root);
    }

    public function testAnUnexpectedConfigurationLinkIsNeverFollowed(): void
    {
        if (PHP_OS_FAMILY === 'Windows') { self::markTestSkipped('Windows symlinks require additional privileges'); }
        $outside = $this->root . '/unrelated.php';
        file_put_contents($outside, 'untouched');
        symlink($outside, $this->root . '/app/config/settings.php');
        try {
            tpRecoveryPublicationPreflight($this->root);
            self::fail('An unexpected link was accepted');
        } catch (TeamPassRecoveryException $exception) {
            self::assertSame('RECOVERY_PATH_INVALID', $exception->reason);
        }
        self::assertSame('untouched', file_get_contents($outside));
    }

    public function testAnOfficialLinkCannotChainThroughAnotherLink(): void
    {
        if (PHP_OS_FAMILY === 'Windows') { self::markTestSkipped('Windows symlinks require additional privileges'); }
        file_put_contents($this->root . '/unrelated.php', 'untouched');
        symlink($this->root . '/unrelated.php', $this->root . '/storage/config/settings.php');
        symlink($this->root . '/storage/config/settings.php', $this->root . '/app/config/settings.php');
        $this->expectExceptionMessage('RECOVERY_PATH_INVALID');
        tpRecoveryConfigDestination($this->root, 'settings.php');
    }

    public function testDestinationDirectoriesCannotTraverseASymlinkedAncestor(): void
    {
        if (PHP_OS_FAMILY === 'Windows') { self::markTestSkipped('Windows symlinks require additional privileges'); }
        mkdir($this->root . '/unrelated', 0700);
        symlink($this->root . '/unrelated', $this->root . '/storage/files');
        $this->expectExceptionMessage('RECOVERY_PATH_INVALID');
        tpRecoveryPublicationPreflight($this->root);
    }
}
