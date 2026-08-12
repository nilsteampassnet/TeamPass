<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Guards the email templates catalog (app/config/emails_templates.php) and the
 * two Language copies that consume it.
 *
 * The catalog drives three things at once: which language keys Language::get()
 * may override from the database, what the administration page lists, and what
 * the save-time token validation accepts. A key that drifts away from the
 * language files silently makes a template uneditable, so it is checked here.
 */
class EmailsTemplatesCatalogTest extends TestCase
{
    /** @var array<string, array<string, mixed>> */
    private static array $catalog;

    /** @var array<string, string> */
    private static array $english;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 2);
        self::$catalog = require $root . '/app/config/emails_templates.php';
        self::$english = require $root . '/app/includes/language/english.php';
    }

    public function testCatalogIsANonEmptyMapOfTemplates(): void
    {
        $this->assertNotEmpty(self::$catalog, 'The email templates catalog is empty');

        foreach (self::$catalog as $id => $template) {
            $this->assertIsString($id, 'Template identifiers must be strings');
            $this->assertIsArray($template, sprintf('Template "%s" is not an array', $id));
        }
    }

    public function testEveryDeclaredKeyExistsInEnglishLanguageFile(): void
    {
        foreach (self::$catalog as $id => $template) {
            $this->assertArrayHasKey('body_key', $template, sprintf('Template "%s" has no body_key', $id));
            $this->assertArrayHasKey(
                $template['body_key'],
                self::$english,
                sprintf('Template "%s": body key "%s" is missing from english.php', $id, $template['body_key'])
            );

            // A fragment is inlined into another body and carries no subject.
            if (empty($template['subject_key']) === true) {
                $this->assertTrue(
                    ($template['fragment'] ?? false) === true,
                    sprintf('Template "%s" has no subject_key but is not flagged as a fragment', $id)
                );
                continue;
            }

            $this->assertArrayHasKey(
                $template['subject_key'],
                self::$english,
                sprintf('Template "%s": subject key "%s" is missing from english.php', $id, $template['subject_key'])
            );
        }
    }

    public function testTokensAreDeclaredAndRequiredTokensAreASubset(): void
    {
        foreach (self::$catalog as $id => $template) {
            $this->assertArrayHasKey('tokens', $template, sprintf('Template "%s" has no tokens list', $id));
            $this->assertIsArray($template['tokens'], sprintf('Template "%s": tokens must be an array', $id));

            $this->assertArrayHasKey('required_tokens', $template, sprintf('Template "%s" has no required_tokens', $id));
            $this->assertIsArray($template['required_tokens'], sprintf('Template "%s": required_tokens must be an array', $id));

            foreach ($template['required_tokens'] as $token) {
                $this->assertContains(
                    $token,
                    $template['tokens'],
                    sprintf('Template "%s": required token "%s" is not in its tokens list', $id, $token)
                );
            }
        }
    }

    public function testRequiredTokensArePresentInTheShippedEnglishBody(): void
    {
        foreach (self::$catalog as $id => $template) {
            $body = self::$english[$template['body_key']];

            foreach ($template['required_tokens'] as $token) {
                $this->assertStringContainsString(
                    $token,
                    $body,
                    sprintf(
                        'Template "%s": required token "%s" is absent from the shipped body "%s" — '
                        . 'the default itself would be refused by the save validation',
                        $id,
                        $token,
                        $template['body_key']
                    )
                );
            }
        }
    }

    public function testCatalogDoesNotExposeUnusedLegacyKeys(): void
    {
        // These keys still live in english.php but are referenced nowhere.
        // Making them editable would advertise emails that are never sent.
        $deadKeys = [
            'email_body_temporary_encryption_code',
            'email_bodyalt_item_updated',
            'email_subject',
            'email_body3',
        ];

        $declared = [];
        foreach (self::$catalog as $template) {
            $declared[] = $template['body_key'];
            if (empty($template['subject_key']) === false) {
                $declared[] = $template['subject_key'];
            }
        }

        foreach ($deadKeys as $deadKey) {
            $this->assertNotContains(
                $deadKey,
                $declared,
                sprintf('Dead language key "%s" must not be part of the catalog', $deadKey)
            );
        }
    }

    public function testLanguageCopiesAreByteIdentical(): void
    {
        $root = dirname(__DIR__, 2);
        $vendor = $root . '/app/vendor/teampassclasses/language/src/Language.php';
        $includes = $root . '/app/includes/libraries/teampassclasses/language/src/Language.php';

        $this->assertFileExists($vendor, 'Composer copy of Language is missing');
        $this->assertFileExists($includes, 'includes/libraries copy of Language is missing');

        $this->assertSame(
            hash_file('sha256', $vendor),
            hash_file('sha256', $includes),
            'The two Language copies have diverged — edit both identically (see CLAUDE.md).'
        );
    }
}
