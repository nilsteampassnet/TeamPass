<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/main_queries_logic.php';

/**
 * Behavioural and integration tests for the main.queries.php request logic.
 */
class MainQueriesLogicTest extends TestCase
{
    public function testKeepsADecodedPayloadUntouched(): void
    {
        $payload = ['user_id' => 42, 'action' => 'perform'];

        self::assertSame($payload, mainQueryNormalizeReceivedData($payload));
        self::assertSame([], mainQueryNormalizeReceivedData([]));
    }

    /**
     * Every value prepareExchangedData(..., 'decode') can hand back instead of an array.
     *
     * @dataProvider unusablePayloadProvider
     */
    public function testTurnsAnUnusablePayloadIntoAnEmptyArray(mixed $payload): void
    {
        self::assertSame([], mainQueryNormalizeReceivedData($payload));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unusablePayloadProvider(): iterable
    {
        yield 'no data posted' => [''];
        yield 'failed decryption' => ['not-decryptable'];
        yield 'invalid json' => [null];
        yield 'json integer' => [5];
        yield 'json float' => [1.5];
        yield 'json boolean' => [true];
    }

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
     * @param array<array-key, mixed> $payload
     */
    public function testRejectsIncompleteMalformedOrStaleRequests(
        array $payload,
        string $postKey,
        string $sessionKey
    ): void {
        self::assertFalse(isSaveUserLocationRequestValid($payload, $postKey, $sessionKey));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string, string}>
     */
    public static function invalidRequestProvider(): iterable
    {
        yield 'normalized unusable payload' => [[], 'current-session-key', 'current-session-key'];
        yield 'missing action' => [['user_id' => 42], 'current-session-key', 'current-session-key'];
        yield 'unexpected action' => [['action' => 'skip'], 'current-session-key', 'current-session-key'];
        yield 'non-string action' => [['action' => true], 'current-session-key', 'current-session-key'];
        yield 'stale client key' => [['action' => 'perform'], 'old-session-key', 'current-session-key'];
        yield 'missing session key' => [['action' => 'perform'], '', ''];
    }

    public function testMainQueryNormalizesBeforeDispatchingToTheHandlers(): void
    {
        $source = self::mainQueriesSource();

        $normalization = strpos($source, 'mainQueryNormalizeReceivedData($dataReceived)');
        $dispatch = strpos($source, "switch (\$inputData['type_category'])");

        self::assertIsInt($normalization, 'mainQuery() must normalize the decoded payload.');
        self::assertIsInt($dispatch, 'The type_category dispatch switch must exist.');
        self::assertLessThan(
            $dispatch,
            $normalization,
            'Normalization must happen before any handler receives the payload.'
        );
    }

    public function testMainHandlerValidatesBeforeSavingTheLocation(): void
    {
        $caseBody = self::switchCaseBody("case 'save_user_location'");

        $validation = strpos($caseBody, 'isSaveUserLocationRequestValid(');
        $save = strpos($caseBody, 'userSaveIp(');

        self::assertIsInt($validation, 'The route must validate the request.');
        self::assertIsInt($save, 'The route must still save the location.');
        self::assertLessThan($save, $validation, 'Validation must run before the write.');
        self::assertStringContainsString('http_response_code(400);', $caseBody);
        self::assertStringNotContainsString("\$dataReceived['action']", $caseBody);
    }

    /**
     * Read main.queries.php once, for the wiring assertions above.
     */
    private static function mainQueriesSource(): string
    {
        $source = file_get_contents(__DIR__ . '/../../app/sources/main.queries.php');
        self::assertIsString($source, 'main.queries.php must be readable.');

        return $source;
    }

    /**
     * Extract the body of a switch case, up to the next case label or default branch.
     *
     * Anchored on a regular expression rather than on one literal delimiter, so that
     * reformatting or inserting another case does not silently widen the scanned region.
     */
    private static function switchCaseBody(string $caseLabel): string
    {
        $source = self::mainQueriesSource();

        $start = strpos($source, $caseLabel);
        self::assertIsInt($start, sprintf('"%s" must exist in main.queries.php.', $caseLabel));

        $matched = preg_match(
            '/^\s*(?:case\s+[\'"]|default\s*:)/m',
            $source,
            $matches,
            PREG_OFFSET_CAPTURE,
            $start + strlen($caseLabel)
        );
        self::assertSame(1, $matched, sprintf('"%s" must be followed by another branch.', $caseLabel));

        return substr($source, $start, $matches[0][1] - $start);
    }
}
