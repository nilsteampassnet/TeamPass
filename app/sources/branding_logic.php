<?php

declare(strict_types=1);

/**
 * Branding images of the login page: the custom logo and the custom background.
 *
 * Both settings hold either the bare name of an image placed in public/assets/custom/,
 * a folder no TeamPass release ships images into, or a URL. They are stored HTML-encoded
 * by save_option_change, so every value is decoded first; callers escape the result for
 * their own output context.
 */

/** Web path of the custom branding folder, relative to the web root (public/). */
const BRANDING_CUSTOM_WEB_PATH = './assets/custom/';

/**
 * Image extensions accepted in the custom branding folder. SVG is left out: opened
 * directly, an SVG file runs its scripts in the TeamPass origin.
 */
const BRANDING_IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

/**
 * Tell whether a value is the bare name of an image of the custom branding folder:
 * no directory part, no hidden file, an accepted image extension.
 *
 * @param string $value Decoded setting value
 *
 * @return bool
 */
function brandingIsCustomImageName(string $value): bool
{
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $value) !== 1) {
        return false;
    }

    return in_array(strtolower((string) pathinfo($value, PATHINFO_EXTENSION)), BRANDING_IMAGE_EXTENSIONS, true);
}

/**
 * Resolve a value to the image of the custom branding folder it names.
 *
 * @param string $value           Decoded setting value
 * @param string $customDirectory Absolute path of public/assets/custom
 *
 * @return string Web path of the image, or '' when the value names no existing image of the folder
 */
function brandingCustomImageUrl(string $value, string $customDirectory): string
{
    if (brandingIsCustomImageName($value) === false) {
        return '';
    }

    return is_file(rtrim($customDirectory, '/\\') . DIRECTORY_SEPARATOR . $value)
        ? BRANDING_CUSTOM_WEB_PATH . $value
        : '';
}

/**
 * Resolve the custom logo setting. A value that names no image of the custom folder is
 * used as before this folder existed: whatever URL or path the administrator entered.
 *
 * @param string $storedValue     Setting value as stored by save_option_change
 * @param string $customDirectory Absolute path of public/assets/custom
 *
 * @return string Unescaped URL, or '' for the default logo
 */
function brandingLogoUrl(string $storedValue, string $customDirectory): string
{
    $value = trim(html_entity_decode($storedValue, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($value === '') {
        return '';
    }

    $customImage = brandingCustomImageUrl($value, $customDirectory);

    return $customImage !== '' ? $customImage : $value;
}

/**
 * Resolve the custom login background setting: an image of the custom folder, or an
 * absolute http(s) URL. The result lands in a CSS url(), so a URL holding a quote, a
 * parenthesis, a backslash, whitespace or an angle bracket is refused.
 *
 * @param string $storedValue     Setting value as stored by save_option_change
 * @param string $customDirectory Absolute path of public/assets/custom
 *
 * @return string Unescaped URL, or '' for the default background
 */
function brandingBackgroundUrl(string $storedValue, string $customDirectory): string
{
    $value = trim(html_entity_decode($storedValue, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($value === '') {
        return '';
    }

    $customImage = brandingCustomImageUrl($value, $customDirectory);
    if ($customImage !== '') {
        return $customImage;
    }

    if (filter_var($value, FILTER_VALIDATE_URL) === false
        || preg_match('#^https?://#i', $value) !== 1
        || preg_match('/["\'()\\\\\s<>]/', $value) === 1
    ) {
        return '';
    }

    return $value;
}
