<?php

namespace Goodby\CSV\Import\Tests\Standard;

/**
 * Managing sandbox directory to use when joining with the file system for tests
 */
class SandboxDirectoryManager
{
    /**
     * Return sandbox directory
     * @return string
     */
    public static function getSandboxDirectory()
    {
        return sys_get_temp_dir().'/goodby/csv';
    }

    /**
     * Reset sandbox directory
     */
    public static function resetSandboxDirectory()
    {
        $sandboxDir = self::getSandboxDirectory();

        if ( file_exists($sandboxDir) ) {
            exec(sprintf('rm -rf %s', $sandboxDir));
        }

        if ( file_exists($sandboxDir) ) {
            throw new \RuntimeException(
                sprintf('Cannot continue test: sandbox directory already exists: %s', $sandboxDir)
            );
        }

        mkdir($sandboxDir, 0777, true);

        if ( file_exists($sandboxDir) === false ) {
            throw new \RuntimeException(
                sprintf('Cannot continue test: sandbox directory does not exist: %s', $sandboxDir)
            );
        }
    }
}

