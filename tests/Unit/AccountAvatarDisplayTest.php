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

    public function testAvatarUrlIsResolvedByASingleSafeHelper(): void
    {
        $functions = self::source('app/sources/main.functions.php');

        self::assertStringContainsString('function getUserAvatarUrl(array $fileNames): string', $functions);
        self::assertStringContainsString('basename(trim((string) $fileName))', $functions);
        self::assertStringContainsString('is_file(TEAMPASS_ROOT', $functions);
        self::assertStringContainsString('rawurlencode($safeFileName)', $functions);

        // Every server-side caller must go through the helper, so the basename +
        // is_file + rawurlencode chain cannot be bypassed by a new call site.
        foreach (['public/index.php', 'app/pages/profile.php'] as $caller) {
            $content = self::source($caller);
            self::assertStringContainsString('getUserAvatarUrl(', $content);
            self::assertStringNotContainsString(
                "'./assets/avatars/'",
                $content,
                $caller . ' must not build an avatar URL by itself'
            );
        }
    }

    public function testTopbarPrefersTheThumbnailOverTheFullAvatar(): void
    {
        self::assertStringContainsString(
            "getUserAvatarUrl([\$session->get('user-avatar_thumb'), \$session->get('user-avatar')])",
            self::source('public/index.php')
        );
    }

    public function testBothAccountBadgesKeepTheInitialsBehindTheAvatar(): void
    {
        $matched = preg_match_all(
            '/<span class="tp-account-avatar[^"]*">(.*?)<\/span>/s',
            self::source('public/index.php'),
            $matches
        );

        self::assertSame(2, $matched, 'The chip and the dropdown header must both show an account badge');

        foreach ($matches[1] as $badge) {
            self::assertStringContainsString('$tpInitials', $badge, 'Initials must remain as fallback content');
            self::assertStringContainsString('$tpAvatarImg', $badge, 'The avatar must overlay the initials');
        }
    }

    public function testLargeAccountBadgeMatchesTheThumbnailWidth(): void
    {
        self::assertSame(
            1,
            preg_match('/makeThumbnail\([^)]*?(\d+)\s*\)/s', self::source('app/sources/upload.files.php'), $thumbnail),
            'The avatar upload must create a thumbnail'
        );
        self::assertSame(
            1,
            preg_match('/\.tp-account-avatar-lg\s*\{[^}]*width:\s*(\d+)px/', self::source('public/assets/css/teampass.css'), $badge),
            'The large account badge must declare a pixel width'
        );

        self::assertSame(
            $thumbnail[1],
            $badge[1],
            'The large badge must match the thumbnail width, otherwise the image is upscaled'
        );
    }

    public function testAccountAvatarImageIsCircularAndCropped(): void
    {
        $stylesheet = self::source('public/assets/css/teampass.css');

        self::assertStringContainsString('.tp-account-avatar-img {', $stylesheet);
        self::assertStringContainsString('object-fit: cover;', $stylesheet);
        self::assertSame(
            1,
            preg_match('/\.tp-account-avatar\s*\{[^}]*overflow:\s*hidden/', $stylesheet),
            'The badge must clip the avatar to its circular shape'
        );
    }

    public function testBrokenAvatarFallsBackToInitialsWithoutAnInlineHandler(): void
    {
        self::assertStringNotContainsString(
            'onerror=',
            self::source('public/index.php'),
            'The fallback must not rely on an inline event handler'
        );

        $loadJs = self::source('app/core/load.js.php');
        self::assertStringContainsString("$('.tp-account-avatar-img').each(function() {", $loadJs);
        self::assertStringContainsString("accountAvatarImg.addEventListener('error'", $loadJs);
        self::assertStringContainsString('accountAvatarImg.naturalWidth === 0', $loadJs);
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
