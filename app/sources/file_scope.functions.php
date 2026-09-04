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
 * `prefixes` and `exact` are anchored at the repository root. `segments` and
 * `basenames` are matched at any depth, because vendored dependencies ship
 * their own repository metadata (.github/, .gitignore, .travis.yml, ...) which
 * is no more part of the deployed runtime than ours.
 *
 * @return array{
 *     prefixes: array<int,string>,
 *     exact: array<int,string>,
 *     segments: array<int,string>,
 *     basenames: array<int,string>
 * }
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
        // Directory names carrying repository, CI or editor metadata, matched on
        // any path segment. `.externals` is deliberately absent: it is a runtime
        // directory (app/includes/.externals) and must stay protected.
        'segments' => [
            '.circleci',
            '.devcontainer',
            '.git',
            '.github',
            '.gitlab',
            '.husky',
            '.idea',
            '.travis',
            '.vscode',
        ],
        // Development metadata file names, matched on the basename at any depth.
        // Deployed hidden files (.htaccess, .user.ini, .env*, .gitkeep) are absent
        // on purpose: they belong to the runtime and stay protected.
        'basenames' => [
            '.codeclimate.yml',
            '.coveralls.yml',
            '.deepsource.toml',
            '.dockerignore',
            '.doctrine-project.json',
            '.duo_linting.xml',
            '.editorconfig',
            '.eslintignore',
            '.eslintrc',
            '.eslintrc.json',
            '.gitattributes',
            '.gitignore',
            '.gitmodules',
            '.jshintrc',
            '.npmignore',
            '.php-cs-fixer.dist.php',
            '.php-cs-fixer.php',
            '.php_cs',
            '.php_cs.dist',
            '.phpstorm.meta.php',
            '.phpunit.result.cache',
            '.prettierrc',
            '.scrutinizer.yml',
            '.styleci.yml',
            '.travis.yml',
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

    $segments = explode('/', $path);

    // A top-level directory whose name starts with a dot is tooling metadata:
    // .claude/, .github/, .vscode/ and whatever tool is added next. Keeping this
    // structural avoids the enumeration drifting behind the repository again.
    // Root-level hidden *files* are not covered, so .htaccess, .user.ini and
    // .env keep being audited.
    if (count($segments) > 1 && str_starts_with($segments[0], '.')) {
        return true;
    }

    if (in_array(end($segments), $rules['basenames'], true)) {
        return true;
    }

    foreach ($segments as $segment) {
        if (in_array($segment, $rules['segments'], true)) {
            return true;
        }
    }

    return false;
}
