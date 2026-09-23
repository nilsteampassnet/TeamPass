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

    public function testASaltkeyWithoutSpecialCharactersCostsASingleDerivation(): void
    {
        self::assertSame(['MySaltKey2019!'], legacyPersonalSaltkeyCandidates('MySaltKey2019!'));
    }

    /**
     * Expected values checked against the 2.x pipelines run with the real FILTER_SANITIZE_STRING.
     *
     * @dataProvider legacySaltkeyProvider
     *
     * @param list<string> $expected
     */
    public function testListsTheFormsTeampass2xProtectedTheKeyWith(string $typed, array $expected): void
    {
        self::assertSame($expected, legacyPersonalSaltkeyCandidates($typed));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function legacySaltkeyProvider(): iterable
    {
        yield 'single quote' => ["l'été", ["l'été", 'l&#39;été']];
        yield 'double quote' => ['say "hi"', ['say "hi"', 'say &quot;hi&quot;']];
        yield 'backslash' => ['back\\slash', ['back\\slash', 'back&#92;slash']];
        yield 'tag' => ['a<b>c', ['a<b>c', 'ac']];
        yield 'lone "<" followed by a space' => ['x < y', ['x < y', 'x ']];
        yield 'script block' => ['a<script>alert(1)</script>b', ['a<script>alert(1)</script>b', 'ab']];
        yield 'literal entity' => ['é&amp;', ['é&amp;', 'é&']];
        yield 'every form differs' => [
            "It's \"a\" <b>\\test</b>",
            ["It's \"a\" <b>\\test</b>", 'It&#39;s &quot;a&quot; &#92;test', "It's \"a\" <b>&#92;test</b>"],
        ];
    }

    public function testUnlocksAKeyProtectedByThe2xFirstDefinitionOfTheSaltkey(): void
    {
        $typed = "l'été \"42\"";
        $protected = \Defuse\Crypto\KeyProtectedByPassword::createRandomPasswordProtectedKey(
            'l&#39;été &quot;42&quot;'
        )->saveToAsciiSafeString();

        $unlocked = null;
        foreach (legacyPersonalSaltkeyCandidates($typed) as $candidate) {
            try {
                $unlocked = \Defuse\Crypto\KeyProtectedByPassword::loadFromAsciiSafeString($protected)
                    ->unlockKey($candidate);
                break;
            } catch (\Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $e) {
                continue;
            }
        }

        self::assertNotNull($unlocked, 'The saltkey as typed must unlock a key protected by TeamPass 2.x.');
    }

    /**
     * @dataProvider reencryptionOutcomeProvider
     */
    public function testErasesTheSaltkeyOnlyOnceNoPersonalItemIsLeft(
        int $batchSize,
        int $remainingItems,
        bool $finished,
        bool $clearSaltkey
    ): void {
        self::assertSame(
            ['finished' => $finished, 'clear_saltkey' => $clearSaltkey],
            personalItemsReencryptionOutcome($batchSize, 1000, $remainingItems)
        );
    }

    /**
     * @return iterable<string, array{int, int, bool, bool}>
     */
    public static function reencryptionOutcomeProvider(): iterable
    {
        yield 'full batch, items left to read' => [1000, 1500, false, false];
        yield 'full batch, nothing left' => [1000, 0, true, true];
        yield 'last batch, everything re-encrypted' => [14, 0, true, true];
        yield 'last batch, items that did not decrypt' => [14, 3, true, false];
        yield 'nothing to read, items that did not decrypt' => [0, 3, true, false];
    }

    public function testThePersonalItemsMigrationKeepsItemsItCannotDecrypt(): void
    {
        $caseBody = self::switchCaseBody("case 'user_psk_reencryption'");
        self::assertStringNotContainsString("filter_var(\$dataReceived['userPsk']", $caseBody);

        $source = self::mainQueriesSource();
        $start = strpos($source, 'function migrateTo3_DoUserPersonalItemsEncryption(');
        self::assertIsInt($start, 'The personal items migration must exist.');
        $end = strpos($source, "\nfunction ", $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        self::assertStringContainsString('legacyPersonalSaltkeyCandidates(', $body);
        self::assertStringContainsString("if (\$passwd['error'] !== false) {", $body);
        self::assertStringContainsString('personalItemsReencryptionOutcome(', $body);
        self::assertStringContainsString("if (\$outcome['clear_saltkey'] === true) {", $body);
        // Owner + TP_USER, like any personal object
        self::assertStringNotContainsString('insertOrUpdateSharekey(', $body);
        self::assertSame(
            2,
            preg_match_all("/storeUsersShareKey\\(\\s*'sharekeys_(?:items|files)',\\s*1,/", $body)
        );
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
