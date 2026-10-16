<?php

declare(strict_types=1);

/**
 * Storage folders set by an administrator: the files folder and the upload folder.
 *
 * Uploads are written to them, some under the name the client sent. Inside the web root the web
 * server would serve those files and run a PHP one, so these settings must never point there
 * (GHSA-988j-pf6f-2m2r). Pure logic, no database: unit-tested by StorageFolderLogicTest.
 */

/**
 * Names of the admin settings that hold a storage folder.
 *
 * @return string[]
 */
function storageFolderSettingNames(): array
{
    return ['path_to_files_folder', 'path_to_upload_folder'];
}

/**
 * Tell whether a storage folder is the web root or sits inside it.
 *
 * Both paths are resolved with realpath(), so "..", symbolic links and trailing separators
 * cannot hide the containment. A folder that does not exist yet is judged on its nearest
 * existing parent, which is where it would be created.
 *
 * @param string $folder  Folder entered by the administrator
 * @param string $webRoot Web root of the instance
 *
 * @return bool
 */
function storageFolderIsInsideWebRoot(string $folder, string $webRoot): bool
{
    $root = realpath($webRoot);
    if ($root === false || trim($folder) === '') {
        return false;
    }

    $candidate = $folder;
    $resolved = realpath($candidate);
    while ($resolved === false) {
        $parent = dirname($candidate);
        if ($parent === $candidate) {
            return false;
        }
        $candidate = $parent;
        $resolved = realpath($candidate);
    }

    $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $resolved = rtrim($resolved, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    return strncmp($resolved, $root, strlen($root)) === 0;
}
