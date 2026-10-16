<?php

declare(strict_types=1);

namespace TeamPass\Tests\PerformChecksGuard;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Database fake: counts the queries so a test can prove an anonymous caller never reaches it. */
final class GuardDB
{
    /** @var array<string, mixed>|null */
    public static $row = null;

    /** @var int */
    public static $queries = 0;

    /**
     * @param mixed ...$args Query parameters
     *
     * @return array<string, mixed>|null
     */
    public static function queryFirstRow(string $sql, ...$args): ?array
    {
        ++self::$queries;
        return self::$row;
    }

    public static function count(): int
    {
        return self::$row === null ? 0 : 1;
    }
}

/** Session fake exposing the three calls PerformChecks makes. */
final class GuardSession
{
    /** @var array<string, mixed> */
    public $values = [];

    /** @var bool */
    public $invalidated = false;

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->values);
    }

    /** @return mixed */
    public function get(string $name)
    {
        return $this->values[$name] ?? null;
    }

    public function invalidate(): void
    {
        $this->invalidated = true;
    }
}

final class GuardSessionManager
{
    /** @var GuardSession|null */
    public static $session = null;

    public static function getSession(): GuardSession
    {
        if (self::$session === null) {
            self::$session = new GuardSession();
        }
        return self::$session;
    }
}

function guardPrefixTable(string $table): string
{
    return 'tp_' . $table;
}

/**
 * GHSA-988j-pf6f-2m2r: the access guard of every AJAX handler accepted a request carrying no
 * session at all. userAccessPage() granted any page when the URL contained "index.php", and
 * checkSession() tested the null identity with isset(), which is false for null. Both copies of
 * the production class are evaluated against fakes, then the handler-side layers are pinned.
 */
final class PerformChecksAnonymousIdentityTest extends TestCase
{
    private const COPIES = [
        'vendor' => 'app/vendor/teampassclasses/performchecks/src/PerformChecks.php',
        'includes' => 'app/includes/libraries/teampassclasses/performchecks/src/PerformChecks.php',
    ];

    /** @var array<string, string> Evaluated class name per copy */
    private static $classes = [];

    /** @var string|null */
    private $previousRequestUri;

    protected function setUp(): void
    {
        GuardDB::$row = null;
        GuardDB::$queries = 0;
        GuardSessionManager::$session = null;
        $this->previousRequestUri = $_SERVER['REQUEST_URI'] ?? null;
        // The exact URL of the reported exploit.
        $_SERVER['REQUEST_URI'] = '/sources/admin.queries.php?index.php';
    }

    protected function tearDown(): void
    {
        if ($this->previousRequestUri === null) {
            unset($_SERVER['REQUEST_URI']);
        } else {
            $_SERVER['REQUEST_URI'] = $this->previousRequestUri;
        }
    }

    /** @return iterable<string, array{string}> */
    public static function copies(): iterable
    {
        foreach (array_keys(self::COPIES) as $copy) {
            yield $copy => [$copy];
        }
    }

    /** @return iterable<string, array{mixed, mixed}> */
    public static function emptyIdentities(): iterable
    {
        yield 'no session at all' => [null, null];
        yield 'user without key' => [7, null];
        yield 'key without user' => [null, 'session-key'];
        yield 'empty strings' => ['', ''];
        yield 'user zero' => [0, 'session-key'];
    }

    #[DataProvider('copies')]
    public function testAnAnonymousRequestIsRefusedEvenWithIndexPhpInTheUrl(string $copy): void
    {
        // A valid administrator row is available: the guard must refuse before even asking.
        GuardDB::$row = self::userRow(1);
        $checks = self::newChecks($copy, null, null);

        $this->assertFalse($checks->checkSession());
        foreach (['admin', 'items', 'home', 'profile', 'options', 'backups'] as $page) {
            $this->assertFalse($checks->userAccessPage($page), $page . ' must be refused to an anonymous caller.');
        }
        $this->assertSame(0, GuardDB::$queries);
    }

    #[DataProvider('emptyIdentities')]
    public function testAnyEmptyIdentityPartIsRefused(mixed $userId, mixed $userKey): void
    {
        GuardDB::$row = self::userRow(1);
        foreach (array_keys(self::COPIES) as $copy) {
            $checks = self::newChecks($copy, $userId, $userKey);
            $this->assertFalse($checks->checkSession(), $copy);
            $this->assertFalse($checks->userAccessPage('admin'), $copy);
        }
    }

    #[DataProvider('copies')]
    public function testAValidAdministratorSessionStillPasses(string $copy): void
    {
        GuardDB::$row = self::userRow(1);
        $checks = self::newChecks($copy, 1, 'session-key');

        $this->assertTrue($checks->checkSession());
        $this->assertTrue($checks->userAccessPage('admin'));
        $this->assertFalse(GuardSessionManager::getSession()->invalidated);
    }

    #[DataProvider('copies')]
    public function testAValidUserSessionDoesNotOpenAdministratorPages(string $copy): void
    {
        GuardDB::$row = self::userRow(0);
        $checks = self::newChecks($copy, 7, 'session-key');

        $this->assertTrue($checks->checkSession());
        $this->assertTrue($checks->userAccessPage('items'));
        $this->assertFalse($checks->userAccessPage('admin'));
    }

    #[DataProvider('copies')]
    public function testAWrongSessionKeyIsRefused(string $copy): void
    {
        GuardDB::$row = self::userRow(1);
        $checks = self::newChecks($copy, 1, 'stolen-or-stale');

        $this->assertFalse($checks->checkSession());
        $this->assertFalse($checks->userAccessPage('admin'));
    }

    public function testTheGuardNoLongerReadsTheRequestUrl(): void
    {
        $vendor = self::source(self::COPIES['vendor']);
        $this->assertSame($vendor, self::source(self::COPIES['includes']), 'Only the vendor copy is autoloaded: both must stay identical.');
        $this->assertStringNotContainsString("\$_SERVER['REQUEST_URI']", $vendor);
        $this->assertStringNotContainsString("isset(\$this->sessionVar['user_id']) === true &&", $vendor);
        $this->assertStringNotContainsString("isset(\$this->sessionVar['user_key']) === true &&", $vendor);
    }

    /** Most admin actions carry no role check of their own: the file gate must require a real session. */
    public function testTheAdminHandlerGateRequiresAnAuthenticatedSession(): void
    {
        $gate = self::gate(self::source('app/sources/admin.queries.php'), "userAccessPage('admin') === false");

        $this->assertStringContainsString("\$session->get('user-id')", $gate);
        $this->assertStringContainsString("empty(\$session->get('key')) === true", $gate);
    }

    /** The upload token is not a gate (an unknown token is not rejected), so the session must be. */
    public function testTheUploadHandlerRefusesAnonymousCallersAndUnknownTypes(): void
    {
        $source = self::source('app/sources/upload.files.php');
        $gate = self::gate($source, "userAccessPage('items') === false");
        $this->assertStringContainsString("\$session->get('user-id')", $gate);
        $this->assertStringContainsString("empty(\$session->get('key')) === true", $gate);

        $allowList = strpos($source, "['upload_profile_photo', 'import_items_from_csv', 'import_items_from_keepass', 'restore_db'], true) === false");
        $this->assertNotFalse($allowList, 'Upload types outside the processed ones must be rejected.');
        $this->assertLessThan(strpos($source, '$targetDir = realpath('), $allowList);
        $this->assertLessThan(strpos($source, "fopen(\$tempFilePath"), $allowList);
    }

    /** The settings writer must refuse a storage folder inside the web root before storing it. */
    public function testSaveOptionChangeChecksTheStorageFolderBeforeStoringIt(): void
    {
        $source = self::source('app/sources/admin.queries.php');
        $start = strpos($source, "case 'save_option_change':");
        $this->assertNotFalse($start);
        $end = strpos($source, "\n    case '", $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $guard = strpos($body, "storageFolderIsInsideWebRoot((string) \$post_value, TEAMPASS_ROOT . '/public')");
        $this->assertNotFalse($guard);
        $this->assertLessThan(strpos($body, 'DB::'), $guard);
    }

    /** Defense in depth for the shipped and documented nginx configurations. */
    public function testNginxNeverExecutesPhpFromTheStaticDirectories(): void
    {
        foreach (['docker/nginx/teampass.conf', 'docs/install/installation.md', 'docs/install/upgrade.md'] as $file) {
            $config = self::source($file);
            $deny = strpos($config, 'location ~* ^/(assets|plugins)/.*\.php$ {');
            $this->assertNotFalse($deny, $file);
            // Regex locations match in order: the deny rule must come first.
            $this->assertLessThan(strpos($config, 'location ~ \.php$ {'), $deny, $file);
        }
    }

    /** @return array<string, mixed> */
    private static function userRow(int $admin): array
    {
        return [
            'id' => $admin === 1 ? 1 : 7,
            'login' => $admin === 1 ? 'admin' : 'alice',
            'key_tempo' => 'session-key',
            'session_end' => time() + 600,
            'deleted_at' => null,
            'admin' => $admin,
            'gestionnaire' => 0,
            'can_manage_all_users' => 0,
            'can_manage_lapr' => 0,
        ];
    }

    private static function newChecks(string $copy, mixed $userId, mixed $userKey): object
    {
        $class = self::classFor($copy);
        return new $class(['type' => ''], ['user_id' => $userId, 'user_key' => $userKey]);
    }

    /** Evaluate the production class body against the fakes, once per copy. */
    private static function classFor(string $copy): string
    {
        if (isset(self::$classes[$copy]) === false) {
            $namespace = __NAMESPACE__ . '\\Copy' . ucfirst($copy);
            $source = self::source(self::COPIES[$copy]);
            eval('namespace ' . $namespace . ';'
                . ' use ' . GuardDB::class . ' as DB;'
                . ' use ' . GuardSessionManager::class . ' as SessionManager;'
                . ' use function ' . __NAMESPACE__ . '\\guardPrefixTable as prefixTable;'
                . substr($source, (int) strpos($source, 'class PerformChecks')));
            self::$classes[$copy] = $namespace . '\\PerformChecks';
        }
        return self::$classes[$copy];
    }

    /** The access gate of a handler: from its page check to the exit that ends it. */
    private static function gate(string $source, string $marker): string
    {
        $start = strpos($source, $marker);
        self::assertNotFalse($start, $marker);
        $end = strpos($source, 'exit;', $start);
        self::assertNotFalse($end);
        return substr($source, $start, $end - $start);
    }

    private static function source(string $path): string
    {
        $content = file_get_contents(__DIR__ . '/../../' . $path);
        self::assertIsString($content, $path);
        return $content;
    }
}
