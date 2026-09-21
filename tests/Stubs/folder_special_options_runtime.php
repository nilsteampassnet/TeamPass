<?php

declare(strict_types=1);

namespace TeamPass\Tests\SpecialOptions;

// Display labels are normally initialized by the HTTP language bootstrap.
const TP_PW_COMPLEXITY = [0 => [0, 'Weak', 'danger'], 38 => [38, 'Medium', 'warning'], 60 => [60, 'Strong', 'success']];

/** In-memory SQL boundary for the production declarations exercised by these tests. */
class DB
{
    public static array $rows = [];
    public static array $queryResults = [];
    public static array $writes = [];
    private static int $rowCount = 0;

    /** Reset the SQL boundary between cases. */
    public static function reset(): void
    {
        self::$rows = self::$queryResults = self::$writes = [];
        self::$rowCount = 0;
    }

    /** Consume the next test row, without connecting to a database. */
    public static function queryFirstRow(string $sql, ...$args): ?array
    {
        $row = array_shift(self::$rows);
        self::$rowCount = $row === null ? 0 : 1;
        return $row;
    }

    /** Consume a result set and expose its row count to the production caller. */
    public static function query(string $sql, ...$args): array
    {
        $rows = array_shift(self::$queryResults) ?? [];
        self::$rowCount = count($rows);
        return $rows;
    }

    /** Return the last simulated result size. */
    public static function count(): int { return self::$rowCount; }
    /** Use a stable id for the newly inserted test folder. */
    public static function insertId(): int { return 100; }
    /** Transaction effects are outside this in-memory option test. */
    public static function startTransaction(): void {}
    /** No external transaction is opened by the test boundary. */
    public static function commit(): void {}
    /** No external transaction is opened by the test boundary. */
    public static function rollback(): void {}

    /** Capture the values production would insert. */
    public static function insert(string $table, array $data): void
    {
        self::$writes[] = ['table' => $table, 'data' => $data];
    }

    /** Capture the values production would update. */
    public static function update(string $table, array $data, ...$where): void
    {
        self::$writes[] = ['table' => $table, 'data' => $data];
    }
}

class SessionManager
{
    /** Supply a session for the isolated KeePass helper. */
    public static function getSession(): TestSession { return new TestSession(); }
    /** Session array side effects are outside these option tests. */
    public static function addRemoveFromSessionArray(...$args): void {}
}

class TestSession
{
    /** Supply a fixed user context to the production request mapping. */
    public function get(string $key): mixed
    {
        return match ($key) {
            'user-accessible_folders', 'user-personal_folders', 'system-array_roles' => [],
            'user-roles', 'user-login' => '',
            'user-admin', 'user-id' => 1,
            default => 0,
        };
    }
    /** Do not persist the import helper's session updates. */
    public function set(string $key, mixed $value): void {}
}

/** Keep table names distinguishable from production tables. */
function prefixTable(string $table): string { return 'test_' . $table; }
/** Cache invalidation is outside the isolated SQL boundary. */
function invalidateCacheForFolderUsers(int $id): void {}

/** Use the actual sanitizer, rather than reproducing its integer/null conversion rules. */
function dataSanitizer(array $data, array $filters): array
{
    return (new \voku\helper\AntiXSS())->xss_clean(
        (new \Elegant\Sanitizer\Sanitizer($data, $filters))->sanitize()
    );
}

/** Execute production source in an isolated namespace, leaving the suite's DB untouched. */
function evaluateSource(string $source, array $variables = []): array
{
    extract($variables, EXTR_SKIP);
    eval('namespace ' . __NAMESPACE__ . '; use \InvalidArgumentException; use \RuntimeException; use \Throwable; ' . $source);
    return get_defined_vars();
}

/** Extract a delimited production section; fail if a refactor removes its boundaries. */
function sourceBetween(string $source, string $start, string $end): string
{
    $offset = strpos($source, $start);
    $limit = $offset === false ? false : strpos($source, $end, $offset + strlen($start));
    if ($offset === false || $limit === false) {
        throw new \RuntimeException('Production section not found: ' . $start . ' / ' . $end);
    }
    return substr($source, $offset, $limit - $offset);
}

/** Read repository code without executing the HTTP bootstrap. */
function productionSource(string $path): string
{
    return str_replace("\r\n", "\n", file_get_contents(__DIR__ . '/../../' . $path));
}

foreach (['app/sources/folders.class.php', 'app/api/Model/ItemModel.php', 'app/api/Model/FolderModel.php'] as $path) {
    $classSource = str_replace('__DIR__', var_export(dirname(__DIR__ . '/../../' . $path), true), productionSource($path));
    evaluateSource(preg_replace('/^<\?php\s*(?:declare\(strict_types=1\);)?/', '', $classSource));
}

// Keep the legacy KeePass creation path and the password score conversion real too.
evaluateSource(sourceBetween(productionSource('app/sources/import.queries.php'), 'function createFolder(', "/**\n * Tell whether"));
$mainFunctions = productionSource('app/sources/main.functions.php');
evaluateSource(sourceBetween($mainFunctions, 'function convertPasswordStrength(', "\n/**"));
require_once __DIR__ . '/../../app/sources/password_strength.functions.php';
require_once __DIR__ . '/../../app/config/include.php';
