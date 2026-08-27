<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * Shared path-scope policy for file integrity and permission diagnostics.
 *
 * Repository metadata and development-only material are not part of the
 * deployed application runtime. They are therefore neutral when present in a
 * source checkout and must not affect System Health or permission remediation.
 */

/**
 * Return explicitly known repository and development-only paths.
 *
 * Hidden files are never excluded as a class. Security-sensitive runtime files
 * such as .htaccess, .user.ini and .env deliberately remain outside this list.
 * Deployment inputs (composer files, Docker assets and migration scripts) also
 * remain protected.
 *
 * @return array{prefixes: array<int,string>, exact: array<int,string>}
 */
function tpFileScopeRepositoryArtifactRules(): array
{
    return [
        'prefixes' => [
            '.agents/',
            '.claude/',
            '.codex/',
            '.devcontainer/',
            '.git/',
            '.github/',
            '.vscode/',
            '_tools/',
            'docs/',
            'tests/',
        ],
        'exact' => [
            '.dockerignore',
            '.editorconfig',
            '.eslintignore',
            '.eslintrc',
            '.gitattributes',
            '.gitignore',
            '.phpstan-baseline.neon',
            '.scrutinizer.yml',
            'AGENTS.md',
            'CLAUDE.md',
            'CODE_OF_CONDUCT.md',
            'CONTRIBUTING.md',
            'DOCKER-HUB-README.md',
            'LICENSE.md',
            'LICENSE_COMPLIANCE_REPORT.md',
            'README.md',
            'SECURITY.md',
            'composer.phar',
            'package-lock.json',
            'package.json',
            'phpstan.neon',
            'phpstan.neon.dist',
            'phpunit.xml',
            'phpunit.xml.dist',
        ],
    ];
}

/**
 * Tell whether a root-relative path is repository or development-only material.
 */
function tpFileScopeIsRepositoryArtifact(string $path): bool
{
    $path = trim(str_replace('\\', '/', $path));
    while (str_starts_with($path, './')) {
        $path = substr($path, 2);
    }
    $path = trim($path, '/');
    if ($path === '' || $path === '.') {
        return false;
    }

    $rules = tpFileScopeRepositoryArtifactRules();
    if (in_array($path, $rules['exact'], true)) {
        return true;
    }

    $pathWithSlash = $path . '/';
    foreach ($rules['prefixes'] as $prefix) {
        if (str_starts_with($pathWithSlash, $prefix)) {
            return true;
        }
    }

    return false;
}
