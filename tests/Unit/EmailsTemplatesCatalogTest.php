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

    /**
     * The "new item created" email used to append the literal string "email_body3" — a language
     * key that exists in no file — inside its link, and to substitute markers without their
     * closing '#'. Both defects are silent: the email is still delivered, just broken.
     */
    public function testItemCreatedEmailNoLongerCarriesTheDeadKeyOrMalformedMarkers(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/sources/items.queries.php');

        $this->assertStringNotContainsString(
            'email_body3',
            $source,
            'The dead language key email_body3 is back in the item creation email'
        );
        $this->assertStringContainsString(
            "array('#label#', '#label', '#link#', '#link')",
            $source,
            'The item creation email must substitute both the canonical and the legacy markers'
        );
    }

    /**
     * The subject and the body of an email must never be taken from an HTTP request: the
     * mail_me endpoint accepts a catalog identifier and resolves the text server-side. Before
     * that, a manager could mail arbitrary content to the users they administrate.
     */
    public function testMailMeEndpointDoesNotAcceptAClientSuppliedBody(): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string) file_get_contents($root . '/app/sources/main.queries.php');

        $start = strpos($source, "case 'mail_me'");
        $this->assertNotFalse($start, 'The mail_me case is gone from main.queries.php');
        $end = strpos($source, "case 'send_waiting_emails'", (int) $start);
        $block = substr($source, (int) $start, (int) $end - (int) $start);

        foreach (["\$dataReceived['body']", "\$dataReceived['subject']"] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $block,
                sprintf('mail_me reads %s from the request again', $forbidden)
            );
        }
        $this->assertStringContainsString(
            "\$dataReceived['template']",
            $block,
            'mail_me must resolve the email from a catalog identifier'
        );
    }

    public function testBothMailMeCallersSendAKnownTemplateIdentifier(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['app/pages/users.js.php', 'app/core/load.js.php'] as $file) {
            $source = (string) file_get_contents($root . '/' . $file);
            $this->assertSame(
                1,
                preg_match("/'template': '([a-z_]+)'/", $source, $matches),
                sprintf('%s no longer sends a template identifier to mail_me', $file)
            );
            $this->assertArrayHasKey(
                $matches[1],
                self::$catalog,
                sprintf('%s sends the unknown template identifier "%s"', $file, $matches[1])
            );
        }
    }

    /**
     * The templates editor needs summernote, which public/index.php loads per page.
     *
     * Every page of $mngPages takes the `$menuAdmin === true` branch, so declaring the script in
     * the `elseif (isset($get['page']))` chain below it — where the kb page does — silently loads
     * nothing. The symptom is remote from the cause: the body stays empty, the preview does
     * nothing and the progress toast never stops, because the missing plugin throws and skips the
     * rest of each callback.
     */
    public function testSummernoteIsLoadedInsideTheAdminBranchOfIndex(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/public/index.php');

        $adminBranch = strpos($source, 'if ($menuAdmin === true) {');
        $this->assertNotFalse($adminBranch, 'The $menuAdmin asset branch is gone from public/index.php');

        $nextBranch = strpos($source, "} elseif (isset(\$get['page']) === true) {", (int) $adminBranch);
        $this->assertNotFalse($nextBranch, 'The page-specific asset branch is gone from public/index.php');

        $block = substr($source, (int) $adminBranch, (int) $nextBranch - (int) $adminBranch);

        $this->assertStringContainsString(
            "\$get['page'] === 'emails_templates'",
            $block,
            'The emails_templates asset condition must sit in the $menuAdmin branch'
        );
        $this->assertStringContainsString(
            'summernote-bs4.min.js',
            $block,
            'summernote is not loaded for the emails_templates page'
        );
    }

    /**
     * The response carries the HTML of the templates: decoding it with the client purifier
     * returns plain text, which would show a tag-stripped body and store it on the next save.
     */
    public function testTemplatesPageDecodesItsResponsesWithoutThePurifier(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/pages/emails_templates.js.php');

        $this->assertStringNotContainsString(
            'decodeQueryReturn(',
            $source,
            'decodeQueryReturn() purifies the response and strips the template markup'
        );
        $this->assertMatchesRegularExpression(
            '/prepareExchangedData\(\s*receivedData,\s*\'decode\'/',
            $source,
            'The page must decode its responses explicitly, with purification disabled'
        );
    }

    /**
     * A subject must be customizable end to end.
     *
     * `subject_prefix` is the shipped default only: the single resolver drops it
     * as soon as an administrator has customized the subject. A call site that
     * concatenates the prefix itself would put it back on every email and make
     * the first words of the line uneditable again.
     */
    public function testNoCallSiteConcatenatesASubjectPrefix(): void
    {
        $root = dirname(__DIR__, 2);
        $prefixes = [];
        foreach (self::$catalog as $template) {
            $prefix = (string) ($template['subject_prefix'] ?? '');
            if ($prefix !== '') {
                $prefixes[$prefix] = true;
            }
        }
        $this->assertNotEmpty($prefixes, 'No prefixed subject left to guard — drop this test');

        $files = [
            'app/scripts/traits/UserHandlerTrait.php',
            'app/sources/main.queries.php',
            'app/sources/emails_templates.queries.php',
        ];

        foreach ($files as $file) {
            $source = (string) file_get_contents($root . '/' . $file);
            foreach (array_keys($prefixes) as $prefix) {
                $this->assertStringNotContainsString(
                    "'" . $prefix . "' .",
                    $source,
                    sprintf('%s prepends "%s" to a subject instead of using getEmailTemplateSubject()', $file, $prefix)
                );
            }
            $this->assertStringNotContainsString(
                "subject_prefix'] ?? '') . emailsTemplates",
                $source,
                sprintf('%s prepends the default prefix to an edited subject', $file)
            );
        }

        $this->assertStringContainsString(
            'getEmailTemplateSubject(',
            (string) file_get_contents($root . '/app/scripts/traits/UserHandlerTrait.php'),
            'UserHandlerTrait must resolve its subject through the shared resolver'
        );
        $this->assertStringContainsString(
            'getEmailTemplateSubject($mailTemplateId',
            (string) file_get_contents($root . '/app/sources/main.queries.php'),
            'mail_me must resolve its subject through the shared resolver'
        );
    }

    /**
     * UserHandlerTrait resolves the subject from a single template identifier
     * while sending four different bodies. That shortcut is only correct as long
     * as those bodies share one subject key and one prefix.
     */
    public function testUserHandlerCredentialTemplatesShareOneSubject(): void
    {
        $shared = [
            'user_keys_ready_credentials',
            'user_new_password',
            'user_keys_ready',
            'user_created_credentials',
        ];

        $reference = self::$catalog['user_keys_ready_credentials'];
        foreach ($shared as $id) {
            $this->assertArrayHasKey($id, self::$catalog, sprintf('Template "%s" left the catalog', $id));
            $this->assertSame(
                $reference['subject_key'],
                self::$catalog[$id]['subject_key'],
                sprintf('Template "%s" no longer shares the credentials subject key', $id)
            );
            $this->assertSame(
                $reference['subject_prefix'] ?? '',
                self::$catalog[$id]['subject_prefix'] ?? '',
                sprintf('Template "%s" no longer shares the credentials subject prefix', $id)
            );
        }
    }

    /**
     * The editor holds the complete subject line, so the read-only prefix block
     * has no reason to exist any more.
     */
    public function testTemplatesPageShowsNoReadOnlySubjectPrefix(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['app/pages/emails_templates.php', 'app/pages/emails_templates.js.php'] as $file) {
            $this->assertStringNotContainsString(
                'emails-templates-subject-prefix',
                (string) file_get_contents($root . '/' . $file),
                sprintf('%s still renders the subject prefix outside the editable field', $file)
            );
        }

        $this->assertStringNotContainsString(
            "'subject_prefix' =>",
            (string) file_get_contents($root . '/app/sources/emails_templates.queries.php'),
            'The prefix must not be sent to the page any more'
        );
    }

    public function testLanguageExposesTheCustomizationFlag(): void
    {
        $this->assertStringContainsString(
            'public function isCustomized(string $key): bool',
            (string) file_get_contents(
                dirname(__DIR__, 2) . '/app/vendor/teampassclasses/language/src/Language.php'
            ),
            'getEmailTemplateSubject() needs Language::isCustomized() to know when to drop the prefix'
        );
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
