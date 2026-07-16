<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * The API must store item fields the way the web form stores them — regression guards for
 * GHSA-r298-6mxv-j9hc.
 *
 * ItemModel::validateData() handed its data to dataSanitizer() and discarded the returned
 * array, so every field reached the database exactly as the client sent it. The renderers
 * insert an item label into the page markup as-is (items list, recycle bin), which turned an
 * API-created label into stored XSS in the browser of anyone listing the folder or opening the
 * recycle bin.
 *
 * Locked invariants:
 * - the fields rendered as HTML come back neutralized;
 * - the secrets ('password', 'totp') keep their exact bytes — encoding them would corrupt
 *   stored passwords;
 * - the sanitized array is actually assigned back in the create path;
 * - the update path cannot reintroduce raw markup.
 */
class ItemFieldSanitizationTest extends TestCase
{
    private static function itemModelSource(): string
    {
        $path = __DIR__ . '/../../../app/api/Model/ItemModel.php';
        self::assertFileExists($path, 'ItemModel.php not found');
        $content = file_get_contents($path);
        self::assertIsString($content);
        return $content;
    }

    /**
     * Runs the real sanitizer over a full item payload.
     */
    private function sanitize(array $overrides = []): array
    {
        require_once __DIR__ . '/../../../app/api/Model/ItemModel.php';

        $data = array_merge([
            'folderId' => 1,
            'label' => '',
            'password' => '',
            'description' => '',
            'login' => '',
            'email' => '',
            'tags' => '',
            'anyoneCanModify' => 0,
            'url' => '',
            'icon' => '',
            'totp' => '',
            'favicon_url' => '',
        ], $overrides);

        $method = new ReflectionMethod(ItemModel::class, 'validateData');
        $method->setAccessible(true);

        $result = $method->invoke(new ItemModel(), $data);
        self::assertIsArray($result, 'validateData must return the sanitized data');

        return $result;
    }

    /**
     * The exact payload from the advisory must not survive as markup.
     */
    public function testAdvisoryLabelPayloadIsNeutralized(): void
    {
        $result = $this->sanitize(['label' => '<img src=x onerror="window.__cc2455=1">']);

        self::assertStringNotContainsString('<img', $result['label']);
        self::assertStringNotContainsString('"', $result['label']);
        self::assertStringContainsString('&lt;img', $result['label'], 'The label must be stored HTML-encoded');
    }

    /**
     * A label is plain text for every renderer, so no markup character may pass through.
     */
    public function testFieldsRenderedAsTextAreEncoded(): void
    {
        $result = $this->sanitize([
            'label' => '<b>x</b>',
            'login' => '"><script>alert(1)</script>',
            'tags' => '<svg/onload=alert(1)>',
        ]);

        foreach (['label', 'login', 'tags'] as $field) {
            self::assertStringNotContainsString('<', $result[$field], "Field '$field' must not keep a raw tag opener");
            self::assertStringNotContainsString('>', $result[$field], "Field '$field' must not keep a raw tag closer");
        }
    }

    /**
     * A javascript: URL is script execution as soon as the item card renders the link.
     */
    public function testDangerousUrlSchemesAreDropped(): void
    {
        foreach (['javascript:alert(1)', 'JavaScript:alert(1)', ' data:text/html,<script>alert(1)</script>', 'vbscript:msgbox'] as $url) {
            self::assertSame('', $this->sanitize(['url' => $url])['url'], "Scheme of '$url' must be refused");
        }

        self::assertSame(
            'https://example.com/x?a=1',
            $this->sanitize(['url' => 'https://example.com/x?a=1'])['url'],
            'A legitimate URL must be preserved'
        );
    }

    /**
     * Encoding a secret would corrupt it: the stored password would no longer be the one the
     * client sent. This is why the fix is field-by-field and not a blanket escape.
     */
    public function testSecretsAreLeftUntouched(): void
    {
        $password = 'a<b>&"\'c/\\d';
        $totp = 'JBSWY3DPEHPK3PXP';

        $result = $this->sanitize(['password' => $password, 'totp' => $totp]);

        self::assertSame($password, $result['password'], 'The password must keep its exact bytes');
        self::assertSame($totp, $result['totp'], 'The TOTP secret must keep its exact bytes');
    }

    /**
     * The description is rich text: the markup the editor produces stays, the executable parts go.
     */
    public function testDescriptionKeepsMarkupButDropsScript(): void
    {
        $result = $this->sanitize(['description' => '<b>kept</b><script>alert(1)</script>']);

        self::assertStringContainsString('<b>kept</b>', $result['description'], 'Rich text must survive');
        self::assertStringNotContainsString('<script', $result['description'], 'Executable markup must be dropped');
    }

    /**
     * The original defect verbatim: the sanitized array was computed and thrown away.
     */
    public function testCreatePathAssignsTheSanitizedData(): void
    {
        $src = self::itemModelSource();

        self::assertStringContainsString(
            '$data = $this->validateData($data);',
            $src,
            'The sanitized array must be assigned back, not discarded'
        );

        self::assertDoesNotMatchRegularExpression(
            '/^\s*\$this->validateData\(\$data\);/m',
            $src,
            'Calling validateData() without using its result is what let raw labels through'
        );
    }

    /**
     * An update must not be able to put back what creation strips.
     */
    public function testUpdatePathSanitizesEveryRenderedField(): void
    {
        $src = self::itemModelSource();

        self::assertSame(
            1,
            preg_match('/\$fieldsDefinitions = \[(.*?)\];/s', $src, $matches),
            'The update field map must exist'
        );
        $map = $matches[1];

        foreach (['label', 'login'] as $field) {
            self::assertMatchesRegularExpression(
                "/'" . $field . "'\s*=> \['db_key' => '" . $field . "', 'type' => 'encoded'\]/",
                $map,
                "Field '$field' must be stored encoded on update"
            );
        }

        self::assertStringNotContainsString(
            "'type' => 'string'",
            $map,
            "A 'string' type stored the client value verbatim — every field needs an explicit filter"
        );

        self::assertStringNotContainsString(
            'default => $params[$paramKey],',
            $src,
            'A default arm silently stores unfiltered values for any type added later'
        );
    }
}
