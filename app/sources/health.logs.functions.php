<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * @file      health.logs.functions.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

/**
 * Split a log buffer into complete lines.
 *
 * When the buffer does not start at offset zero, its first fragment is discarded:
 * it may be the end of a line whose beginning was outside the read budget.
 *
 * @return list<string>
 */
function tpHealthLogBufferLines(string $buffer, bool $startsMidFile): array
{
    if ($buffer === '') {
        return array();
    }

    $buffer = str_replace(array("\r\n", "\r"), "\n", $buffer);
    $parts = explode("\n", $buffer);

    if ($startsMidFile === true) {
        array_shift($parts);
    }
    if (empty($parts) === false && $parts[count($parts) - 1] === '') {
        array_pop($parts);
    }

    return array_values($parts);
}

/**
 * Read the tail of an uncompressed log file within a strict byte budget.
 *
 * @return array{lines: list<string>, bytes_read: int, status: string}
 */
function tpHealthReadPlainLogTail(string $path, int $lines, int $maxBytes): array
{
    if ($lines <= 0) {
        return array('lines' => array(), 'bytes_read' => 0, 'status' => 'lines_reached');
    }
    if ($maxBytes <= 0) {
        return array('lines' => array(), 'bytes_read' => 0, 'status' => 'limit_reached');
    }
    if (is_readable($path) === false) {
        return array('lines' => array(), 'bytes_read' => 0, 'status' => 'not_readable');
    }

    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return array('lines' => array(), 'bytes_read' => 0, 'status' => 'not_readable');
    }

    if (fseek($handle, 0, SEEK_END) !== 0) {
        fclose($handle);
        return array('lines' => array(), 'bytes_read' => 0, 'status' => 'read_error');
    }

    $position = ftell($handle);
    if ($position === false) {
        fclose($handle);
        return array('lines' => array(), 'bytes_read' => 0, 'status' => 'read_error');
    }

    $position = (int) $position;
    $buffer = '';
    $bytesRead = 0;

    while (true) {
        if ($position === 0) {
            $resultLines = tpHealthLogBufferLines($buffer, false);
            fclose($handle);
            return array(
                'lines' => $resultLines,
                'bytes_read' => $bytesRead,
                'status' => 'exhausted',
            );
        }

        $remainingBytes = $maxBytes - $bytesRead;
        if ($remainingBytes <= 0) {
            $resultLines = tpHealthLogBufferLines($buffer, true);
            fclose($handle);
            return array(
                'lines' => $resultLines,
                'bytes_read' => $bytesRead,
                'status' => 'limit_reached',
            );
        }

        $readLength = min(16384, $position, $remainingBytes);
        $readPosition = $position - $readLength;
        if (fseek($handle, $readPosition, SEEK_SET) !== 0) {
            $resultLines = tpHealthLogBufferLines($buffer, $position > 0);
            fclose($handle);
            return array(
                'lines' => $resultLines,
                'bytes_read' => $bytesRead,
                'status' => 'read_error',
            );
        }

        $chunk = fread($handle, $readLength);
        if ($chunk === false || $chunk === '') {
            $resultLines = tpHealthLogBufferLines($buffer, $position > 0);
            fclose($handle);
            return array(
                'lines' => $resultLines,
                'bytes_read' => $bytesRead,
                'status' => 'read_error',
            );
        }

        $position = $readPosition;
        $buffer = $chunk . $buffer;
        $bytesRead += strlen($chunk);

        if (substr_count($buffer, "\n") + substr_count($buffer, "\r") >= $lines) {
            $resultLines = tpHealthLogBufferLines($buffer, $position > 0);
            if (count($resultLines) >= $lines) {
                fclose($handle);
                return array(
                    'lines' => array_slice($resultLines, -$lines),
                    'bytes_read' => $bytesRead,
                    'status' => 'lines_reached',
                );
            }
        }
    }
}

/**
 * Read a gzip log in streaming mode within a strict decompressed-byte budget.
 *
 * Gzip streams cannot be read backwards. If the end of the archive cannot be
 * reached within the budget, no line from that archive is returned because its
 * actual tail has not been determined.
 *
 * @return array{lines: list<string>, bytes_read: int, status: string}
 */
function tpHealthReadGzipLogTail(string $path, int $lines, int $maxBytes): array
{
    if ($lines <= 0) {
        return array('lines' => array(), 'bytes_read' => 0, 'status' => 'lines_reached');
    }
    if ($maxBytes <= 0) {
        return array('lines' => array(), 'bytes_read' => 0, 'status' => 'limit_reached');
    }
    if (
        function_exists('gzopen') === false
        || function_exists('gzread') === false
        || function_exists('gzeof') === false
    ) {
        return array('lines' => array(), 'bytes_read' => 0, 'status' => 'gzip_unavailable');
    }
    if (is_readable($path) === false) {
        return array('lines' => array(), 'bytes_read' => 0, 'status' => 'not_readable');
    }

    $handle = @gzopen($path, 'rb');
    if ($handle === false) {
        return array('lines' => array(), 'bytes_read' => 0, 'status' => 'read_error');
    }

    $buffer = '';
    $tail = array();
    $bytesRead = 0;
    $reachedEnd = false;

    while ($bytesRead < $maxBytes) {
        $chunk = @gzread($handle, min(4096, $maxBytes - $bytesRead));
        if ($chunk === false) {
            @gzclose($handle);
            return array('lines' => array(), 'bytes_read' => $bytesRead, 'status' => 'read_error');
        }
        if ($chunk === '') {
            $reachedEnd = @gzeof($handle);
            break;
        }

        $bytesRead += strlen($chunk);
        $buffer .= $chunk;

        while (($newlinePosition = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $newlinePosition);
            $buffer = substr($buffer, $newlinePosition + 1);
            $tail[] = rtrim($line, "\r");
            if (count($tail) > $lines * 2) {
                $tail = array_slice($tail, -$lines);
            }
        }
    }

    if ($reachedEnd === false) {
        $reachedEnd = @gzeof($handle);
    }
    @gzclose($handle);

    if ($reachedEnd === false) {
        return array('lines' => array(), 'bytes_read' => $bytesRead, 'status' => 'limit_reached');
    }

    if ($buffer !== '') {
        $tail[] = rtrim($buffer, "\r");
    }

    return array(
        'lines' => array_values(array_slice($tail, -$lines)),
        'bytes_read' => $bytesRead,
        'status' => 'exhausted',
    );
}

/**
 * Read the tail of one physical log file.
 *
 * @return array{lines: list<string>, bytes_read: int, status: string}
 */
function tpHealthReadLogFileTail(string $path, int $lines, int $maxBytes): array
{
    if (str_ends_with(strtolower($path), '.gz') === true) {
        return tpHealthReadGzipLogTail($path, $lines, $maxBytes);
    }

    return tpHealthReadPlainLogTail($path, $lines, $maxBytes);
}

/**
 * Backward-compatible single-file tail helper.
 *
 * @param string $path     Absolute path of the log file to read.
 * @param int    $lines    Number of trailing lines to return.
 * @param int    $maxBytes Maximum number of bytes the reader may consume.
 * @return string The trailing lines joined by a single line feed.
 */
function tpTailFileLines(string $path, int $lines, int $maxBytes = 2097152): string
{
    $result = tpHealthReadLogFileTail($path, $lines, $maxBytes);

    return implode("\n", $result['lines']);
}

/**
 * Query-string parameters whose value must never be displayed in a log viewer.
 *
 * A web server access log records the full request line, and TeamPass carries
 * link secrets in the query string: a Secure Send / OTV URL is built as
 * `index.php?otv=1&code=<secret>&key=<secret>&stamp=<ts>` and possession of
 * that URL is the whole credential. Attachment and backup download links carry
 * `key` / `key_tmp` the same way. Displaying those values would let anyone able
 * to open the Health page read secrets they hold no sharekey for.
 *
 * @return list<string>
 */
function tpHealthGetRedactedLogParameters(): array
{
    return array(
        'key_tmp',
        'api_key',
        'apikey',
        'password',
        'passwd',
        'secret',
        'token',
        'code',
        'key',
        'pw',
    );
}

/**
 * Mask the value of every secret-bearing query-string parameter in a log line.
 *
 * The parameter name is kept so the line stays readable for diagnostics; only
 * the value is replaced. Referer fields are covered too because the match is
 * not anchored to the request target.
 *
 * @param string $line One raw log line.
 * @return string The same line with secret values replaced by a placeholder.
 */
function tpHealthRedactLogLine(string $line): string
{
    if ($line === '') {
        return '';
    }

    $pattern = '/([?&](?:' . implode('|', tpHealthGetRedactedLogParameters()) . ')=)[^\s&"\']+/i';
    $redacted = preg_replace($pattern, '${1}[REDACTED]', $line);

    return is_string($redacted) === true ? $redacted : $line;
}

/**
 * Apply {@see tpHealthRedactLogLine()} to a whole log excerpt.
 *
 * @param string $content Log excerpt, lines separated by a line feed.
 * @return string The excerpt with every secret value masked.
 */
function tpHealthRedactLogContent(string $content): string
{
    if ($content === '') {
        return '';
    }

    return implode("\n", array_map('tpHealthRedactLogLine', explode("\n", $content)));
}

/**
 * Resolve one numeric rotation without scanning arbitrary sibling files.
 *
 * Only `<logical path>.<number>` and `<logical path>.<number>.gz` are accepted;
 * rotation 0 designates the current file.
 *
 * @param string $logicalPath Path of the current (non-rotated) log file.
 * @param int    $rotation    Rotation index, 0 for the current file.
 * @return string The existing physical path, or an empty string when absent.
 */
function tpHealthGetLogRotationPath(string $logicalPath, int $rotation): string
{
    if ($rotation <= 0) {
        return is_file($logicalPath) === true ? $logicalPath : '';
    }

    $plainPath = $logicalPath . '.' . $rotation;
    if (is_file($plainPath) === true) {
        return $plainPath;
    }

    $gzipPath = $plainPath . '.gz';

    return is_file($gzipPath) === true ? $gzipPath : '';
}

/**
 * Read a logical log across its numeric rotations with one global byte budget.
 *
 * Physical files are inspected newest first, while contributing segments are
 * returned in chronological display order.
 *
 * @return array{content: string, lines: list<string>, source_files: list<string>, bytes_read: int, status: string}
 */
function tpHealthReadLogicalLog(
    string $logicalPath,
    int $lines,
    int $maxBytes = 2097152,
    int $maxRotations = 50
): array {
    $segments = array();
    $sourceFiles = array();
    $bytesRead = 0;
    $remainingLines = max(0, $lines);
    $status = 'not_found';
    $physicalFileFound = false;

    for ($rotation = 0; $rotation <= max(0, $maxRotations); $rotation++) {
        if ($remainingLines === 0) {
            $status = 'lines_reached';
            break;
        }

        $path = tpHealthGetLogRotationPath($logicalPath, $rotation);
        if ($path === '') {
            continue;
        }

        $physicalFileFound = true;
        $remainingBytes = max(0, $maxBytes - $bytesRead);
        $fileResult = tpHealthReadLogFileTail($path, $remainingLines, $remainingBytes);
        $bytesRead += $fileResult['bytes_read'];

        if (empty($fileResult['lines']) === false) {
            array_unshift($segments, $fileResult['lines']);
            array_unshift($sourceFiles, $path);
            $remainingLines -= count($fileResult['lines']);
        }

        $status = $fileResult['status'];
        if ($remainingLines === 0) {
            $status = 'lines_reached';
            break;
        }
        if ($bytesRead >= $maxBytes) {
            $status = 'limit_reached';
            break;
        }
        if ($fileResult['status'] !== 'exhausted') {
            break;
        }
    }

    if ($physicalFileFound === true && $status === 'not_found') {
        $status = 'exhausted';
    }

    $resultLines = array();
    foreach ($segments as $segment) {
        foreach ($segment as $line) {
            $resultLines[] = $line;
        }
    }

    return array(
        'content' => implode("\n", $resultLines),
        'lines' => $resultLines,
        'source_files' => $sourceFiles,
        'bytes_read' => $bytesRead,
        'status' => $status,
    );
}

/**
 * Tell whether a path is absolute in the POSIX sense.
 *
 * @param string $path Path to test.
 * @return bool True when the path starts with a slash.
 */
function tpHealthIsAbsolutePath(string $path): bool
{
    return $path !== '' && str_starts_with($path, '/');
}

/**
 * Trim, drop empty entries and de-duplicate while preserving order.
 *
 * @param array<int, mixed> $values
 * @return list<string>
 */
function tpHealthUniqueNonEmptyStrings(array $values): array
{
    $normalized = array();
    foreach ($values as $value) {
        $value = trim((string) $value);
        if ($value !== '') {
            $normalized[$value] = $value;
        }
    }

    return array_values($normalized);
}

/**
 * Resolve APACHE_LOG_DIR from an Apache envvars file without executing shell code.
 *
 * Debian and Ubuntu declare `/var/log/apache2$SUFFIX`. For the default
 * `/etc/apache2` instance the suffix is empty; named instances use the part
 * following `apache2-` in their configuration directory. Only these known
 * variables are expanded. Any other shell expression makes the value unusable.
 *
 * @param string $content         Contents of the envvars file.
 * @param string $apacheConfigDir Directory containing the Apache configuration.
 * @return string Resolved absolute directory, or an empty string.
 */
function tpHealthResolveApacheLogDirFromEnvvars(string $content, string $apacheConfigDir): string
{
    if (
        preg_match(
            '/^\s*(?:export\s+)?APACHE_LOG_DIR\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\r\n#]*))/m',
            $content,
            $matches
        ) !== 1
    ) {
        return '';
    }

    $rawValue = '';
    foreach (array(1, 2, 3) as $matchIndex) {
        if (isset($matches[$matchIndex]) === true && (string) $matches[$matchIndex] !== '') {
            $rawValue = trim((string) $matches[$matchIndex]);
            break;
        }
    }
    if ($rawValue === '') {
        return '';
    }

    $configDir = rtrim(str_replace('\\', '/', trim($apacheConfigDir)), '/');
    $suffix = '';
    if (preg_match('#/apache2-([^/]+)$#', $configDir, $suffixMatches) === 1) {
        $suffix = '-' . (string) $suffixMatches[1];
    }

    $resolved = str_replace(
        array('${APACHE_CONFDIR}', '$APACHE_CONFDIR', '${SUFFIX}', '$SUFFIX'),
        array($configDir, $configDir, $suffix, $suffix),
        $rawValue
    );
    $resolved = trim($resolved);

    if ($resolved === '' || str_contains($resolved, '$') === true || tpHealthIsAbsolutePath($resolved) === false) {
        return '';
    }

    return rtrim($resolved, '/');
}

/**
 * List the Apache envvars files worth parsing, the default instance first.
 *
 * Debian and Ubuntu support several side-by-side instances, each with its own
 * /etc/apache2-<name>/envvars declaring a distinct ${APACHE_LOG_DIR}. Reading
 * only the default instance would resolve every named instance to
 * /var/log/apache2, which is not where its logs are written.
 *
 * @return list<string>
 */
function tpHealthGetApacheEnvvarsPaths(): array
{
    $paths = array('/etc/apache2/envvars');

    $namedInstances = glob('/etc/apache2-*/envvars');
    if (is_array($namedInstances) === true) {
        // Bounded on purpose: the list feeds a per-directive candidate loop.
        foreach (array_slice($namedInstances, 0, 10) as $namedInstance) {
            $paths[] = (string) $namedInstance;
        }
    }

    return tpHealthUniqueNonEmptyStrings($paths);
}

/**
 * List the directories ${APACHE_LOG_DIR} may expand to, most reliable first.
 *
 * A concrete value declared in an Apache envvars file wins over conventional
 * paths. Values that still contain shell variables are discarded safely.
 *
 * @return list<string>
 */
function tpHealthGetApacheLogDirCandidates(): array
{
    $candidates = array();

    foreach (tpHealthGetApacheEnvvarsPaths() as $envvarsPath) {
        if (is_file($envvarsPath) === false || is_readable($envvarsPath) === false) {
            continue;
        }

        $envvars = file_get_contents($envvarsPath);
        if (is_string($envvars) === false) {
            continue;
        }

        $resolvedDirectory = tpHealthResolveApacheLogDirFromEnvvars($envvars, dirname($envvarsPath));
        if ($resolvedDirectory !== '') {
            $candidates[] = $resolvedDirectory;
        }
    }

    return tpHealthUniqueNonEmptyStrings(array_merge(
        $candidates,
        array('/var/log/apache2', '/var/log/httpd', '/usr/local/apache/logs')
    ));
}

/**
 * Extract only the local-file token from an Apache or nginx log directive.
 *
 * Formats and options are dropped, and destinations that are not a local file
 * (piped programs, syslog:, nginx `off` / `stderr`) are rejected.
 *
 * @param string $rawDirective Directive value, without its keyword.
 * @param string $serverFamily 'apache' or 'nginx'.
 * @return string The local path, or an empty string when unusable.
 */
function tpHealthExtractLogDirectivePathToken(string $rawDirective, string $serverFamily): string
{
    $directive = trim($rawDirective, " \t\n\r\0\x0B;");
    if ($directive === '') {
        return '';
    }

    $path = '';
    if ($directive[0] === '"' || $directive[0] === "'") {
        if (preg_match('/^(["\'])(.*?)\1(?:\s|$)/s', $directive, $matches) !== 1) {
            return '';
        }
        $path = (string) $matches[2];
    } else {
        $parts = preg_split('/\s+/', $directive, 2);
        $path = is_array($parts) === true ? (string) $parts[0] : '';
    }

    $path = trim(str_replace("\0", '', $path));
    $pathLower = strtolower($path);
    if (
        $path === ''
        || str_starts_with($path, '|') === true
        || str_starts_with($pathLower, 'syslog:') === true
        || ($serverFamily === 'nginx' && in_array($pathLower, array('off', 'stderr'), true) === true)
    ) {
        return '';
    }

    return $path;
}

/**
 * Resolve an Apache log directive to an absolute path.
 *
 * @param string            $rawDirective   Directive value, without its keyword.
 * @param string            $configPath     Config file the directive was read from.
 * @param list<string>|null $logDirectories ${APACHE_LOG_DIR} candidates, null to auto-detect.
 * @return string The absolute path, or an empty string when unresolvable.
 */
function tpHealthResolveApacheLogDirectivePath(
    string $rawDirective,
    string $configPath,
    ?array $logDirectories = null
): string {
    $path = tpHealthExtractLogDirectivePathToken($rawDirective, 'apache');
    if ($path === '') {
        return '';
    }

    if (str_contains($path, '${APACHE_LOG_DIR}') === true) {
        $firstResolvedCandidate = '';
        $candidateWithExistingDirectory = '';

        foreach ($logDirectories ?? tpHealthGetApacheLogDirCandidates() as $logDirectory) {
            $candidate = str_replace('${APACHE_LOG_DIR}', rtrim($logDirectory, '/'), $path);
            if (str_contains($candidate, '$') === true || tpHealthIsAbsolutePath($candidate) === false) {
                continue;
            }
            if ($firstResolvedCandidate === '') {
                $firstResolvedCandidate = $candidate;
            }
            if (is_file($candidate) === true) {
                return $candidate;
            }
            if ($candidateWithExistingDirectory === '' && is_dir(dirname($candidate)) === true) {
                $candidateWithExistingDirectory = $candidate;
            }
        }

        $path = $candidateWithExistingDirectory !== '' ? $candidateWithExistingDirectory : $firstResolvedCandidate;
    }

    if ($path === '' || str_contains($path, '$') === true) {
        return '';
    }
    if (tpHealthIsAbsolutePath($path) === true) {
        return $path;
    }

    $candidate = dirname($configPath) . '/' . ltrim($path, '/');

    return tpHealthIsAbsolutePath($candidate) === true ? $candidate : '';
}

/**
 * Resolve an nginx log directive to an absolute path.
 *
 * Dynamic destinations built from nginx variables are rejected.
 *
 * @param string $rawDirective Directive value, without its keyword.
 * @param string $configPath   Config file the directive was read from.
 * @return string The absolute path, or an empty string when unresolvable.
 */
function tpHealthResolveNginxLogDirectivePath(string $rawDirective, string $configPath): string
{
    $path = tpHealthExtractLogDirectivePathToken($rawDirective, 'nginx');
    if ($path === '' || str_contains($path, '$') === true) {
        return '';
    }
    if (tpHealthIsAbsolutePath($path) === true) {
        return $path;
    }

    $candidate = dirname($configPath) . '/' . ltrim($path, '/');

    return tpHealthIsAbsolutePath($candidate) === true ? $candidate : '';
}

/**
 * Collect every usable path declared by one Apache directive in a config file.
 *
 * @param string            $content        Configuration file content.
 * @param string            $directive      Directive keyword, e.g. 'CustomLog'.
 * @param string            $configPath     Config file the content was read from.
 * @param list<string>|null $logDirectories ${APACHE_LOG_DIR} candidates, null to auto-detect.
 * @return list<string>
 */
function tpHealthExtractApacheLogPathsFromContent(
    string $content,
    string $directive,
    string $configPath,
    ?array $logDirectories = null
): array {
    $paths = array();
    $pattern = '/^\s*' . preg_quote($directive, '/') . '\s+(.+)$/mi';
    if (preg_match_all($pattern, $content, $matches) > 0) {
        foreach ($matches[1] as $rawDirective) {
            $path = tpHealthResolveApacheLogDirectivePath((string) $rawDirective, $configPath, $logDirectories);
            if ($path !== '') {
                $paths[] = $path;
            }
        }
    }

    return tpHealthUniqueNonEmptyStrings($paths);
}

/**
 * Collect every usable path declared by one nginx directive in a config file.
 *
 * @param string $content    Configuration file content.
 * @param string $directive  Directive keyword, e.g. 'access_log'.
 * @param string $configPath Config file the content was read from.
 * @return list<string>
 */
function tpHealthExtractNginxLogPathsFromContent(
    string $content,
    string $directive,
    string $configPath
): array {
    $paths = array();
    $pattern = '/^\s*' . preg_quote($directive, '/') . '\s+([^;]+);/mi';
    if (preg_match_all($pattern, $content, $matches) > 0) {
        foreach ($matches[1] as $rawDirective) {
            $path = tpHealthResolveNginxLogDirectivePath((string) $rawDirective, $configPath);
            if ($path !== '') {
                $paths[] = $path;
            }
        }
    }

    return tpHealthUniqueNonEmptyStrings($paths);
}
