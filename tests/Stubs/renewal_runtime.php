<?php

declare(strict_types=1);

namespace TeamPass\Tests\Renewal;

require_once __DIR__ . '/../../app/sources/security_posture_logic.php';
require_once __DIR__ . '/../../app/sources/renewal_logic.php';
require_once __DIR__ . '/../../app/sources/rotation.functions.php';
require_once __DIR__ . '/../../app/sources/reports.functions.php';
require_once __DIR__ . '/../../app/vendor/sergeytsalkov/meekrodb/db.class.php';

/** SQLite transport for the real renewal SQL; no authorization decision is mocked. */
class DB
{
    public static \SQLite3 $connection;

    /** Keep the real MeekroDB parser, substituting only the connection's string escaping. */
    public static function query(string $sql, ...$args): array
    {
        $parser = new class extends \MeekroDB {
            /** Quote fixture values without opening a MySQL connection. */
            public function escape($value)
            {
                return "'" . \SQLite3::escapeString((string) $value) . "'";
            }
        };
        $result = self::$connection->query($parser->parse($sql, ...$args));
        if ($result->numColumns() === 0) {
            $result->finalize();
            return [];
        }
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** Return the first database row. */
    public static function queryFirstRow(string $sql, ...$args): ?array
    {
        return self::query($sql, ...$args)[0] ?? null;
    }

    /** Return the first scalar value. */
    public static function queryFirstField(string $sql, ...$args): mixed
    {
        $row = self::queryFirstRow($sql, ...$args);
        return $row === null ? null : array_values($row)[0];
    }

    /** Return a grant or role id column. */
    public static function queryFirstColumn(string $sql, ...$args): array
    {
        return array_map(static fn (array $row) => array_values($row)[0], self::query($sql, ...$args));
    }
}

/** Settings boundary, independent of the feature-specific dashboard switches. */
class ConfigManager
{
    public static array $settings = [];

    /** Supply the settings used by the production folder resolver. */
    public function getAllSettings(): array { return self::$settings; }
}

/** Minimal session boundary; deliberately permits stale folder and role arrays in tests. */
class Session
{
    /** Hold the session values supplied by a test. */
    public function __construct(private array $values) {}

    /** Read one session value. */
    public function get(string $key): mixed { return $this->values[$key] ?? null; }
}

/** SQLite counterpart of NestedTree's read-only path query (no tree writes). */
class NestedTree
{
    /** Accept the existing handler's tree constructor. */
    public function __construct(...$args) {}

    /** Return every ancestor so the production output must remove forbidden names. */
    public function getPath($folderId, bool $includeSelf = false): array
    {
        return array_map(static fn (array $row): object => (object) $row, DB::query(
            'SELECT ancestor.* FROM renewal_nested_tree AS ancestor
            INNER JOIN renewal_nested_tree AS child ON child.id = %i
            WHERE ancestor.nleft <= child.nleft AND ancestor.nright >= child.nright
            ORDER BY ancestor.nleft',
            $folderId
        ));
    }
}

/** Isolate the fixture's table names from any real installation. */
function prefixTable(string $table): string { return 'renewal_' . $table; }

/** The test already loaded the database boundary. */
function loadClasses(string $className = ''): void {}

/** Load a tracked source without its HTTP/bootstrap side effects. */
function source(string $path): string
{
    return str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../' . $path));
}

/** Extract a complete, top-level production function. */
function declaration(string $source, string $name): string
{
    $start = strpos($source, 'function ' . $name . '(');
    $end = $start === false ? false : strpos($source, "\n}\n", $start);
    if ($start === false || $end === false) {
        throw new \RuntimeException('Missing production function: ' . $name);
    }
    return substr($source, $start, $end + 2 - $start);
}

/**
 * Load actual authorization and handler code in a fresh namespace per request.
 * This resets the production helpers' request-local caches without changing their code.
 */
function newRequest(): string
{
    static $sequence = 0;
    $namespace = __NAMESPACE__ . '\\Request' . ++$sequence;
    $imports = 'namespace ' . $namespace . ';'
        . ' use ' . __NAMESPACE__ . '\\DB;'
        . ' use ' . __NAMESPACE__ . '\\ConfigManager;'
        . ' use ' . __NAMESPACE__ . '\\NestedTree;'
        . ' use function ' . __NAMESPACE__ . '\\prefixTable;'
        . ' use function ' . __NAMESPACE__ . '\\loadClasses;'
        . ' use function \securityPostureResolveAuthorizedFolders;'
        . ' use function \itemRestrictionSqlPredicate;'
        . ' const TP_ONE_DAY_SECONDS = 86400;';
    $checks = source('app/vendor/teampassclasses/performchecks/src/PerformChecks.php');
    $code = substr($checks, strpos($checks, 'class PerformChecks'));
    $functions = source('app/sources/main.functions.php');
    foreach (['getPersonalFolderIdsWithDescendants', 'getOwnPersonalFolderIds', 'securityPostureUserRoleIds',
        'securityPostureAuthorizedFolderIds', 'securityPostureItemAccessSql', 'renewalItemDueAt', 'renewalItemStatus', 'renewalEligibleItemSql', 'renewalApplicablePeriodSql'] as $name) {
        $code .= "\n" . declaration($functions, $name);
    }
    foreach (['laprNormalizeHostname', 'laprClassifySelfTarget', 'laprGetItemRelations'] as $name) {
        $code .= "\n" . declaration(source('app/sources/lapr.functions.php'), $name);
    }
    $code .= "\n" . declaration(source('app/sources/renewal_preview.php'), 'renewalPreview');
    eval($imports . $code);
    return $namespace;
}

/** Execute the renewal table's production body, substituting only HTTP exit with callback return. */
function runTable(string $namespace, array $query = [], array $sessionValues = []): array
{
    $session = new Session($sessionValues + [
        'user-id' => 7, 'user-login' => 'alice', 'user-accessible_folders' => [11, 20, 21],
        'user-forbiden_personal_folders' => [30, 31], 'user-roles' => '',
    ]);
    $request = new \Symfony\Component\HttpFoundation\Request($query);
    $SETTINGS = ConfigManager::$settings;
    $handler = source('app/sources/expired.datatables.php');
    $body = substr($handler, strpos($handler, '$tree = new NestedTree('));
    $body = str_replace('exit;', 'return;', $body);
    $imports = 'namespace ' . $namespace . '; use ' . __NAMESPACE__ . '\\DB;'
        . ' use ' . __NAMESPACE__ . '\\NestedTree; use function ' . __NAMESPACE__ . '\\prefixTable;';
    ob_start();
    try {
        eval($imports . '(static function () use ($session, $request, $SETTINGS) {' . $body . '})();');
        return json_decode(ob_get_contents(), true, 512, JSON_THROW_ON_ERROR);
    } finally {
        ob_end_clean();
    }
}

/** Replace encryption transport only, keeping report queries and row shaping real. */
function prepareExchangedData(array $data, string $mode): string { return json_encode($data, JSON_THROW_ON_ERROR); }

/** Execute the actual rotation report cases against the renewal SQL fixture. */
function runRotationReport(string $namespace, string $type): array
{
    $SETTINGS = ConfigManager::$settings + ['rotation_tracking_enabled' => 1];
    $lang = new class {
        /** Return stable labels for assertions independent of the user's locale. */
        public function get(string $key): string { return $key; }
    };
    $source = source('app/sources/reports.queries.php');
    $start = strpos($source, '$folderRenewalEnabled =');
    $setup = substr($source, $start, strpos($source, '// Do checks', $start) - $start);
    $body = substr($source, strpos($source, "case 'report_rotation_overdue':"));
    $imports = 'namespace ' . $namespace . '; use ' . __NAMESPACE__ . '\\DB;'
        . ' use function ' . __NAMESPACE__ . '\\prefixTable;'
        . ' use function ' . __NAMESPACE__ . '\\prepareExchangedData;';
    ob_start();
    try {
        eval($imports . '(static function () use ($SETTINGS, $lang, $type) {' . $setup . 'switch ($type) {' . $body . '})();');
        return json_decode(ob_get_contents(), true, 512, JSON_THROW_ON_ERROR);
    } finally {
        ob_end_clean();
    }
}
