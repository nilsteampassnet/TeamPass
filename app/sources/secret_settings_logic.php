<?php

declare(strict_types=1);

/**
 * Admin settings that hold a credential: the LDAP bind password and the SMTP password.
 *
 * They are stored in teampass_misc encrypted with the instance key (is_encrypted = 1),
 * decrypted only where they are used, and never rendered back into a page: on save, a blank
 * value keeps the stored one. Values stored before this change are plaintext and keep working
 * until they are saved again or migrated by the upgrade. The decryption itself lives in
 * tpGetSecretSetting() (main.functions.php), as it needs the instance key.
 */

/**
 * Names of the admin settings that hold a credential.
 *
 * @return string[]
 */
function tpSecretSettingNames(): array
{
    return ['ldap_password', 'email_auth_pwd'];
}

/**
 * Tell whether a stored value is ciphertext produced by cryption() with the instance key.
 *
 * Defuse encodes its ciphertext as lowercase hex behind the 0xDEF50200 version header. Any
 * other value was stored before these settings were encrypted.
 *
 * @param string $value Stored value
 *
 * @return bool
 */
function tpIsInstanceKeyCiphertext(string $value): bool
{
    return strlen($value) > 8 && strncmp($value, 'def50200', 8) === 0 && ctype_xdigit($value) === true;
}
