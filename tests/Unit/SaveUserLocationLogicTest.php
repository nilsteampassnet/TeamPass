<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/main_queries_logic.php';

/**
 * Behavioural and integration tests for save_user_location request validation.
 */
class SaveUserLocationLogicTest extends TestCase
{
    public function testAcceptsTheExpectedPayloadFromTheCurrentSession(): void
    {
        self::assertTrue(
            isSaveUserLocationRequestValid(
                ['user_id' => 42, 'action' => 'perform'],
                'current-session-key',
                'current-session-key'
            )
        );
    }

    /**
     * @dataProvider invalidRequestProvider
     *
     * @param array<string, mixed>|null|string $payload
     */
    public function testRejectsIncompleteMalformedOrStaleRequests(
        array|null|string $payload,
        string $postKey,
        string $sessionKey
    ): void {
        self::assertFalse(isSaveUserLocationRequestValid($payload, $postKey, $sessionKey));
    }

    /**
     * @return iterable<string, array{array<string, mixed>|null|string, string, string}>
     */
    public static function invalidRequestProvider(): iterable
    {
        yield 'missing payload' => ['', 'current-session-key', 'current-session-key'];
        yield 'null payload' => [null, 'current-session-key', 'current-session-key'];
        yield 'missing action' => [[], 'current-session-key', 'current-session-key'];
        yield 'unexpected action' => [['action' => 'skip'], 'current-session-key', 'current-session-key'];
        yield 'non-string action' => [['action' => true], 'current-session-key', 'current-session-key'];
        yield 'stale client key' => [['action' => 'perform'], 'old-session-key', 'current-session-key'];
        yield 'missing session key' => [['action' => 'perform'], '', ''];
    }

    public function testMainHandlerValidatesBeforeSavingTheLocation(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/sources/main.queries.php');
        self::assertIsString($source);

        $caseStart = strpos($source, "case 'save_user_location'");
        $caseEnd = strpos($source, 'default :', (int) $caseStart);
        self::assertIsInt($caseStart);
        self::assertIsInt($caseEnd);

        $caseBody = substr($source, $caseStart, $caseEnd - $caseStart);
        $validationPosition = strpos($caseBody, 'isSaveUserLocationRequestValid(');
        $savePosition = strpos($caseBody, 'userSaveIp(');

        self::assertIsInt($validationPosition);
        self::assertIsInt($savePosition);
        self::assertLessThan($savePosition, $validationPosition);
        self::assertStringContainsString('http_response_code(400);', $caseBody);
        self::assertStringNotContainsString("\$dataReceived['action']", $caseBody);
    }
}
