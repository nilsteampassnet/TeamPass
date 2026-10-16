<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/storage_folder_logic.php';

/**
 * Storage folders must stay out of the web root (storage_folder_logic.php, GHSA-988j-pf6f-2m2r):
 * uploads written inside it would be served, and a PHP one executed, by the web server.
 */
class StorageFolderLogicTest extends TestCase
{
    /** @var string */
    private $base;

    /** @var string */
    private $webRoot;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tp-storage-' . bin2hex(random_bytes(6));
        $this->webRoot = $this->base . '/public';
        foreach (['/public/assets/avatars', '/public2', '/storage/files'] as $dir) {
            mkdir($this->base . $dir, 0700, true);
        }
    }

    protected function tearDown(): void
    {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isLink() === true || $item->isFile() === true) {
                unlink($item->getPathname());
            } else {
                rmdir($item->getPathname());
            }
        }
        rmdir($this->base);
    }

    public function testTheFilesAndUploadFoldersAreStorageSettings(): void
    {
        $this->assertSame(['path_to_files_folder', 'path_to_upload_folder'], storageFolderSettingNames());
    }

    public function testAFolderInsideTheWebRootIsDetected(): void
    {
        $this->assertTrue(storageFolderIsInsideWebRoot($this->webRoot, $this->webRoot));
        $this->assertTrue(storageFolderIsInsideWebRoot($this->webRoot . '/assets/avatars', $this->webRoot));
        $this->assertTrue(storageFolderIsInsideWebRoot($this->webRoot . '/assets/avatars/', $this->webRoot));
    }

    public function testAFolderOutsideTheWebRootIsAccepted(): void
    {
        $this->assertFalse(storageFolderIsInsideWebRoot($this->base . '/storage/files', $this->webRoot));
        $this->assertFalse(storageFolderIsInsideWebRoot($this->base, $this->webRoot));
    }

    public function testASiblingSharingTheNamePrefixIsNotInside(): void
    {
        $this->assertFalse(storageFolderIsInsideWebRoot($this->base . '/public2', $this->webRoot));
    }

    public function testAFolderNotCreatedYetIsJudgedOnItsExistingParent(): void
    {
        $this->assertTrue(storageFolderIsInsideWebRoot($this->webRoot . '/new/deeper', $this->webRoot));
        $this->assertFalse(storageFolderIsInsideWebRoot($this->base . '/storage/new/deeper', $this->webRoot));
    }

    public function testDotDotSegmentsCannotHideTheContainment(): void
    {
        $this->assertTrue(storageFolderIsInsideWebRoot($this->base . '/storage/../public/assets', $this->webRoot));
        $this->assertFalse(storageFolderIsInsideWebRoot($this->webRoot . '/../storage/files', $this->webRoot));
    }

    public function testASymbolicLinkIntoTheWebRootIsDetected(): void
    {
        $link = $this->base . '/storage/link-to-avatars';
        if (@symlink($this->webRoot . '/assets/avatars', $link) === false) {
            $this->markTestSkipped('Symbolic links are not available on this system.');
        }

        $this->assertTrue(storageFolderIsInsideWebRoot($link, $this->webRoot));
    }

    public function testEmptyValuesAreNotJudged(): void
    {
        $this->assertFalse(storageFolderIsInsideWebRoot('', $this->webRoot));
        $this->assertFalse(storageFolderIsInsideWebRoot('   ', $this->webRoot));
        $this->assertFalse(storageFolderIsInsideWebRoot($this->webRoot, $this->base . '/missing'));
    }
}
