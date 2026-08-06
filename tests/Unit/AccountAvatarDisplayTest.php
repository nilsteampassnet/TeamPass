<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for the account avatar displayed in the topbar.
 */
class AccountAvatarDisplayTest extends TestCase
{
    private static function source(string $relativePath): string
    {
        $content = file_get_contents(__DIR__ . '/../../' . $relativePath);
        self::assertNotFalse($content, $relativePath . ' must be readable');

        return (string) $content;
    }

    public function testAccountAvatarUsesSessionThumbnailWithSafeFallbacks(): void
    {
        $index = self::source('public/index.php');

        self::assertStringContainsString("foreach (['user-avatar_thumb', 'user-avatar']", $index);
        self::assertStringContainsString('basename(trim((string) $session->get($tpAvatarSessionKey)))', $index);
        self::assertStringContainsString('is_file($tpAvatarPath) === true', $index);
        self::assertStringContainsString("'./assets/avatars/' . rawurlencode(\$tpAvatarFile)", $index);
        self::assertSame(2, substr_count($index, 'class="tp-account-avatar-img"'));
        self::assertSame(
            2,
            substr_count($index, "htmlspecialchars(\$tpInitials, ENT_QUOTES, 'UTF-8')"),
            'Both account avatar locations must retain the initials fallback'
        );
    }

    public function testAccountAvatarImageIsCircularAndCropped(): void
    {
        $stylesheet = self::source('public/assets/css/teampass.css');

        self::assertStringContainsString('.tp-account-avatar-img {', $stylesheet);
        self::assertStringContainsString('object-fit: cover;', $stylesheet);
        self::assertStringContainsString('overflow: hidden;', $stylesheet);
    }

    public function testProfileUpdatesAndRemovesTheTopbarAvatarWithoutReloading(): void
    {
        $javascript = self::source('app/pages/profile.js.php');

        self::assertStringContainsString('const updateAccountAvatar = (avatarUrl) => {', $javascript);
        self::assertStringContainsString("accountAvatar.find('.tp-account-avatar-img').remove()", $javascript);
        self::assertStringContainsString(
            "updateAccountAvatar('assets/avatars/' + encodeURIComponent(myData.filename_thumb || myData.filename))",
            $javascript
        );
        self::assertStringContainsString("updateAccountAvatar('')", $javascript);
    }
}
