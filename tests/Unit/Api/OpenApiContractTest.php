<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: OpenAPI 3.1 contract check (REST-9).
 *
 * Keeps app/api/openapi.json and the router/controllers in sync:
 * - the spec must be valid JSON, OpenAPI 3.1, with the expected base metadata;
 * - every documented path must map to an existing controller action method;
 * - every public *Action method of the controllers must be documented;
 * - error responses must use the RFC 9457 problem envelope (REST-5);
 * - paginated collections must expose X-Total-Count (REST-8);
 * - create operations must return 201 (REST-6).
 */
class OpenApiContractTest extends TestCase
{
    private const CONTROLLER_DIR = '/app/api/Controller/Api';

    /**
     * Maps the first URI segment to its controller file. The /authorize and
     * /authorizeToken routes are handled by AuthController without a segment
     * prefix (see app/api/index.php).
     */
    private const SEGMENT_TO_CONTROLLER = [
        'authorize' => 'AuthController.php',
        'authorizeToken' => 'AuthController.php',
        'auth' => 'AuthController.php',
        'item' => 'ItemController.php',
        'folder' => 'FolderController.php',
        'user' => 'UserController.php',
        'misc' => 'MiscController.php',
    ];

    private static ?array $spec = null;

    private function getSpec(): array
    {
        if (self::$spec === null) {
            $path = __DIR__ . '/../../../app/api/openapi.json';
            self::assertFileExists($path, 'openapi.json not found');
            $raw = file_get_contents($path);
            self::assertIsString($raw);
            $decoded = json_decode($raw, true);
            self::assertIsArray($decoded, 'openapi.json is not valid JSON');
            self::$spec = $decoded;
        }
        return self::$spec;
    }

    /**
     * Extracts the public *Action methods declared in a controller file.
     *
     * @return string[] action names without the 'Action' suffix
     */
    private function getControllerActions(string $controllerFile): array
    {
        $path = __DIR__ . '/../../..' . self::CONTROLLER_DIR . '/' . $controllerFile;
        self::assertFileExists($path, "Controller '$controllerFile' not found");
        $source = (string) file_get_contents($path);

        preg_match_all('/public function (\w+)Action\s*\(/', $source, $matches);
        return $matches[1];
    }

    public function testSpecIsOpenApi31(): void
    {
        $spec = $this->getSpec();
        self::assertArrayHasKey('openapi', $spec);
        self::assertStringStartsWith('3.1', (string) $spec['openapi']);
        self::assertArrayHasKey('info', $spec);
        self::assertArrayHasKey('paths', $spec);
        self::assertNotEmpty($spec['paths']);
        self::assertArrayHasKey('securitySchemes', $spec['components'] ?? []);
    }

    public function testEveryDocumentedPathMapsToAControllerAction(): void
    {
        $spec = $this->getSpec();

        foreach (array_keys($spec['paths']) as $path) {
            if ($path === '/openapi.json') {
                continue; // served statically by the router, no controller
            }

            $segments = array_values(array_filter(explode('/', $path)));
            $first = $segments[0];

            self::assertArrayHasKey(
                $first,
                self::SEGMENT_TO_CONTROLLER,
                "Documented path '$path' does not match a routed controller"
            );

            // /authorize → authorizeAction; /item/get → getAction; etc.
            $action = $segments[1] ?? $first;
            $actions = $this->getControllerActions(self::SEGMENT_TO_CONTROLLER[$first]);

            self::assertContains(
                $action,
                $actions,
                "Documented path '$path' has no {$action}Action in " . self::SEGMENT_TO_CONTROLLER[$first]
            );
        }
    }

    public function testEveryControllerActionIsDocumented(): void
    {
        $spec = $this->getSpec();
        $documentedPaths = array_keys($spec['paths']);

        $segmentByController = [];
        foreach (self::SEGMENT_TO_CONTROLLER as $segment => $controller) {
            // AuthController actions are routed without a controller segment
            if ($controller !== 'AuthController.php') {
                $segmentByController[$controller] = $segment;
            }
        }

        foreach ($segmentByController as $controller => $segment) {
            foreach ($this->getControllerActions($controller) as $action) {
                self::assertContains(
                    '/' . $segment . '/' . $action,
                    $documentedPaths,
                    "Action {$action}Action in $controller is not documented in openapi.json"
                );
            }
        }

        foreach ($this->getControllerActions('AuthController.php') as $action) {
            // authorize/authorizeToken are routed without a segment prefix;
            // session-lifecycle actions live under /auth/<action> (e.g. /auth/logout)
            $candidates = ['/' . $action, '/auth/' . $action];
            self::assertNotEmpty(
                array_intersect($candidates, $documentedPaths),
                "Action {$action}Action in AuthController.php is not documented in openapi.json"
            );
        }
    }

    public function testErrorResponsesUseProblemEnvelope(): void
    {
        $spec = $this->getSpec();
        $responses = $spec['components']['responses'] ?? [];
        self::assertNotEmpty($responses);

        foreach ($responses as $name => $response) {
            // Skip success-shaped shared responses
            if (in_array($name, ['JwtToken', 'ExtensionSettings'], true)) {
                continue;
            }
            self::assertArrayHasKey(
                'application/problem+json',
                $response['content'] ?? [],
                "Shared error response '$name' must use application/problem+json (RFC 9457)"
            );
        }

        // The Problem schema must keep the legacy 'error' member for one major version
        $problem = $spec['components']['schemas']['Problem']['properties'] ?? [];
        foreach (['type', 'title', 'status', 'detail', 'error'] as $member) {
            self::assertArrayHasKey($member, $problem, "Problem schema must define '$member'");
        }
    }

    public function testPaginatedCollectionsExposeTotalCount(): void
    {
        $spec = $this->getSpec();

        foreach (['/item/get', '/item/inFolders', '/folder/listFolders'] as $path) {
            $headers = $spec['paths'][$path]['get']['responses']['200']['headers'] ?? [];
            self::assertArrayHasKey(
                'X-Total-Count',
                $headers,
                "$path must document the X-Total-Count pagination header (REST-8)"
            );
        }
    }

    public function testCreateOperationsReturn201(): void
    {
        $spec = $this->getSpec();

        foreach (['/item/create', '/folder/create'] as $path) {
            $responses = $spec['paths'][$path]['post']['responses'] ?? [];
            self::assertArrayHasKey('201', $responses, "$path must document a 201 Created response (REST-6)");
            self::assertArrayNotHasKey('200', $responses, "$path must not document 200 anymore (REST-6)");
        }

        // Implementation guard: the controllers actually send 201 + Location (item)
        $itemController = (string) file_get_contents(
            __DIR__ . '/../../..' . self::CONTROLLER_DIR . '/ItemController.php'
        );
        self::assertStringContainsString('$intSuccessStatus = 201;', $itemController);
        self::assertStringContainsString("'Location: '", $itemController);

        $folderController = (string) file_get_contents(
            __DIR__ . '/../../..' . self::CONTROLLER_DIR . '/FolderController.php'
        );
        self::assertStringContainsString('$intSuccessStatus = 201;', $folderController);
    }

    public function testNoCustomReasonPhrasesLeft(): void
    {
        // REST-1/REST-4 guard: the legacy non-standard status lines must not come back
        foreach (self::SEGMENT_TO_CONTROLLER as $controller) {
            $source = (string) file_get_contents(
                __DIR__ . '/../../..' . self::CONTROLLER_DIR . '/' . $controller
            );
            self::assertStringNotContainsString(
                'Expected parameters not provided',
                $source,
                "$controller must not use the custom '401 Expected parameters not provided' status line"
            );
            self::assertDoesNotMatchRegularExpression(
                '/Method not supported.;\s*\n\s*\$strErrorHeader = .HTTP\/1\.1 422/',
                $source,
                "$controller must return 405 (not 422) on unsupported methods"
            );
        }
    }
}
