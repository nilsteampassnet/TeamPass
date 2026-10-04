<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel tests for the credential settings (LDAP bind password, SMTP password).
 *
 * They are stored encrypted with the instance key, so every consumer must go through
 * tpGetSecretSetting(), the settings pages must never render them, the save path must encrypt
 * them as typed, and the upgrade must migrate the plaintext values already stored.
 */
class SecretSettingsWiringTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $source = file_get_contents(__DIR__ . '/../../' . $relativePath);
        self::assertIsString($source, $relativePath . ' must be readable');

        return $source;
    }

    private function section(string $source, string $startMarker, string $endMarker): string
    {
        $start = strpos($source, $startMarker);
        self::assertIsInt($start, 'Start marker must exist: ' . $startMarker);
        $end = strpos($source, $endMarker, $start + strlen($startMarker));
        self::assertIsInt($end, 'End marker must exist after: ' . $startMarker);

        return substr($source, $start, $end - $start);
    }

    private function inputTag(string $page, string $id): string
    {
        $matched = preg_match("/<input[^>]*id='" . $id . "'[^>]*>/", $page, $tag);
        self::assertSame(1, $matched, 'Input #' . $id . ' must exist');

        return $tag[0];
    }

    public function testEveryLdapConnectionDecryptsTheBindPassword(): void
    {
        foreach (['app/vendor', 'app/includes/libraries'] as $root) {
            self::assertStringContainsString(
                "tpGetSecretSetting(\$this->settings, 'ldap_password')",
                $this->source($root . '/teampassclasses/ldapextra/src/LdapExtra.php'),
                'Only the vendor/ copy is autoloaded, but both copies must carry the change'
            );
        }

        $users = $this->source('app/sources/users.queries.php');
        self::assertSame(2, substr_count($users, "tpGetSecretSetting(\$SETTINGS, 'ldap_password')"));
        self::assertStringNotContainsString("=> \$SETTINGS['ldap_password']", $users);

        $check = $this->section(
            $this->source('app/sources/main.functions.php'),
            'function ldapCheckUserPassword(',
            'new Connection($config)'
        );
        self::assertStringContainsString("tpGetSecretSetting(\$SETTINGS, 'ldap_password')", $check);
    }

    public function testTheMailerDecryptsTheSmtpPassword(): void
    {
        foreach (['app/vendor', 'app/includes/libraries'] as $root) {
            $settings = $this->source($root . '/teampassclasses/emailservice/src/EmailSettings.php');
            self::assertStringContainsString("tpGetSecretSetting(\$SETTINGS, 'email_auth_pwd')", $settings);
            self::assertStringNotContainsString("\$this->authPassword = \$SETTINGS['email_auth_pwd'] ?? '';", $settings);
        }
    }

    public function testSettingsPagesNeverRenderTheCredentials(): void
    {
        foreach (['app/pages/ldap.php' => 'ldap_password', 'app/pages/emails.php' => 'email_auth_pwd'] as $page => $id) {
            $tag = $this->inputTag($this->source($page), $id);
            self::assertStringContainsString("value=''", $tag, $page . ' must not send the stored value to the browser');
            self::assertStringContainsString('setting-secret', $tag, 'The save handler must send it as typed');
            self::assertStringContainsString("autocomplete='new-password'", $tag);
            self::assertStringNotContainsString("value='<?php", $tag);
        }
    }

    public function testSaveEncryptsTheCredentialAsTyped(): void
    {
        $save = $this->section(
            $this->source('app/sources/admin.queries.php'),
            "case 'save_option_change':",
            "case 'get_values_for_statistics':"
        );

        $secret = strpos($save, 'in_array($post_field, tpSecretSettingNames(), true)');
        $write = strpos($save, 'DB::update(');
        self::assertIsInt($secret);
        self::assertIsInt($write);
        self::assertLessThan($write, $secret, 'The value must be encrypted before it is written');
        self::assertStringContainsString("cryption(\$clearSecret, '', 'encrypt', \$SETTINGS)", $save);
        self::assertStringContainsString("is_string(\$dataReceived['value'] ?? null)", $save, 'The raw value, not the HTML-encoded one');
        self::assertSame(2, substr_count($save, "array('is_encrypted' => 1)"), 'Both the insert and the update must flag it');
    }

    public function testTheBrowserSendsCredentialsUnsanitised(): void
    {
        $save = $this->section($this->source('app/pages/admin.js.php'), 'function saveFieldValue(', '.fail(function()');

        self::assertStringContainsString("var isSecret = \$field.hasClass('setting-secret');", $save);
        self::assertStringContainsString('isSelect2 === false && isSecret === false', $save);
        self::assertStringContainsString("isSecret === true && value === ''", $save, 'Blank must keep the stored value');
    }

    public function testTheUpgradeEncryptsStoredPlaintextAndVerifiesFirst(): void
    {
        self::assertStringContainsString(
            'tpEncryptStoredSecretSettings($SETTINGS)',
            $this->source('public/install/upgrade_run_3.2.2.php')
        );

        $migration = $this->section(
            $this->source('app/sources/main.functions.php'),
            'function tpEncryptStoredSecretSettings(',
            "\n}\n"
        );
        $verify = strpos($migration, "\$check['string'] !== \$value");
        $replace = strpos($migration, "'is_encrypted' => 1, 'updated_at' => time()");
        self::assertIsInt($verify, 'The new ciphertext must be decrypted back and compared');
        self::assertIsInt($replace);
        self::assertLessThan($replace, $verify);
        self::assertStringContainsString('ConfigManager::invalidateCache();', $migration);
    }
}
