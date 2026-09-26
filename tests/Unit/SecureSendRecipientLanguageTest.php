<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TeampassClasses\Language\Language;

require_once __DIR__ . '/../../app/sources/secure_send_logic.php';

/** The public recipient route bypasses index.php's normal language initialization. */
class SecureSendRecipientLanguageTest extends TestCase
{
    /** Anonymous visitors inherit the instance language; existing preferences still win. */
    #[DataProvider('languageChoices')]
    public function testRecipientLanguagePrecedence(?string $sessionLanguage, array $settings, string $expected): void
    {
        self::assertSame($expected, secureSendRecipientLanguage($sessionLanguage, $settings));
    }

    /** Cover fresh sessions, empty preferences and missing/empty instance settings. */
    public static function languageChoices(): array
    {
        return [
            'anonymous French instance' => [null, ['default_language' => 'french'], 'french'],
            'empty session preference' => ['', ['default_language' => 'french'], 'french'],
            'blank session preference' => ['  ', ['default_language' => 'french'], 'french'],
            'existing English preference' => ['english', ['default_language' => 'french'], 'english'],
            'existing French preference' => ['french', ['default_language' => 'english'], 'french'],
            'missing default' => [null, [], 'english'],
            'null default' => [null, ['default_language' => null], 'english'],
            'empty default' => [null, ['default_language' => ''], 'english'],
            'blank default' => [null, ['default_language' => ' '], 'english'],
        ];
    }

    /** Exercise actual shipped catalogs, not substitute translated strings. */
    public function testFreshRecipientUsesFrenchMessages(): void
    {
        $directory = dirname(__DIR__, 2) . '/app/includes/language/';
        $settings = ['default_language' => 'french'];
        $sessionLanguage = null;
        $language = new Language(secureSendRecipientLanguage($sessionLanguage, $settings), $directory);
        self::assertSame('Envoi sécurisé', $language->getShipped('secure_send'));
        self::assertSame('Mot de passe', $language->getShipped('password'));

        $preferred = new Language(secureSendRecipientLanguage('english', $settings), $directory);
        self::assertSame('Secure Send', $preferred->getShipped('secure_send'));
        self::assertSame('Password', $preferred->getShipped('password'));
    }
}
