<?php
namespace TeampassClasses\Language;

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      Language.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

class Language {
    /**
     * Name of the table holding the administrator customizations, without prefix.
     */
    private const EMAIL_TEMPLATES_TABLE = 'emails_templates';

    /**
     * Setting acting as the global kill switch for the customization layer.
     */
    private const EMAIL_TEMPLATES_SETTING = 'emails_templates_enabled';

    private $language;
    private $path;
    private $translations;
    private $fallbackTranslations; // New property for English translations

    /**
     * Flat set of the language keys the administrator may override, built once
     * per process from the email templates catalog. Shape: [key => true].
     *
     * @var array<string, bool>|null
     */
    private static $emailKeys = null;

    /**
     * Result of the kill switch lookup, resolved once per process.
     *
     * @var bool|null
     */
    private static $emailOverridesEnabled = null;

    /**
     * Administrator customizations for the current language and for English.
     * Shape: [language => [key => content]]. Null until the first email key is
     * requested, so a request that sends no email issues no query at all.
     *
     * @var array<string, array<string, string>>|null
     */
    private $emailOverrides = null;

    public function __construct($language = null, $path = __DIR__."/../../../../includes/language") {
        if (null === $language || empty($language) === true ) {
            $language = 'english';
        }
        $this->setLanguage($language, $path);
    }

    public function setLanguage($language, $path) {
        $this->language = $language;
        $this->path = $path;
        // The cached overrides belong to the previous language.
        $this->emailOverrides = null;
        $this->loadTranslations();
    }

    private function loadTranslations() {
        // 1. Load Fallback (English) Translations first
        $this->fallbackTranslations = $this->loadLanguageFile('english');

        // 2. Load Primary Translations
        // Only load if the requested language is not already English
        if ($this->language === 'english') {
            // If the primary language is english, use fallback as primary
            $this->translations = $this->fallbackTranslations;
        } else {
            $this->translations = $this->loadLanguageFile($this->language);
        }
    }

    /**
     * Helper function to safely load a specific language file.
     *
     * @param string $lang_code The code of the language file to load (e.g., 'french').
     * @return array The array of translations, or an empty array on failure.
     */
    private function loadLanguageFile($lang_code) {
        $filepath = $this->path . DIRECTORY_SEPARATOR . basename(strtolower($lang_code)) . '.php';
        $translations = [];

        if (file_exists($filepath) && is_file($filepath)) {
            // Suppress warnings as file inclusion can be noisy, error handling is done by checking array type.
            $result = @include $filepath;
            if (is_array($result)) {
                $translations = $result;
            } else {
                // LOGGING: Language file was included but did not return a valid array.
            }
        } else {
            // LOGGING: Language file not found or inaccessible: {$filepath}
        }

        return $translations;
    }

    /**
     * Retrieves the translation for a given key.
     * Fallback strategy: administrator customization (email templates only)
     * -> Primary language -> English translation -> Key itself.
     *
     * @param string $key The translation key.
     * @return string The translated string.
     */
    public function get($key) {
        // 0. Check for an administrator customization
        $override = $this->getEmailOverride((string) $key);
        if ($override !== null) {
            return $override;
        }

        // 1..3 Language files, then the key itself
        return $this->getShipped($key);
    }

    /**
     * Retrieves the translation shipped with the application, ignoring any
     * administrator customization.
     *
     * Used by the email templates administration page to display the original
     * text next to a customized one, and to offer a revert.
     *
     * @param string $key The translation key.
     * @return string The translated string.
     */
    public function getShipped($key) {
        // 1. Check in Primary Language
        if (isset($this->translations[$key]) && $this->translations[$key] !== "") {
            return $this->translations[$key];
        }

        // 2. Check in Fallback (English) Language
        if (isset($this->fallbackTranslations[$key])) {
            return $this->fallbackTranslations[$key];
        }

        // 3. Last resort: Return the key itself
        return $key;
    }

    /**
     * Tells whether an administrator customization applies to a key.
     *
     * Used by the callers that prepend a literal prefix to a subject
     * (`TEAMPASS - `, ...): that prefix belongs to the shipped default only, so
     * a customized subject must be sent verbatim and stay editable end to end.
     *
     * @param string $key The translation key.
     * @return bool True when the value comes from the emails_templates table.
     */
    public function isCustomized(string $key): bool
    {
        return $this->getEmailOverride($key) !== null;
    }

    /**
     * Returns the administrator customization for an email template key, or null.
     *
     * Resolution mirrors the language files: the current language wins, English
     * is the fallback. An override stored as an empty string is ignored, so an
     * accidentally blanked template degrades to the shipped one instead of
     * falling through to the key-as-value branch.
     *
     * @param string $key The translation key.
     * @return string|null The customized content, or null when there is none.
     */
    private function getEmailOverride(string $key): ?string
    {
        // Non-email keys cost one isset() and never reach the database.
        $emailKeys = self::emailTemplateKeys();
        if (isset($emailKeys[$key]) === false) {
            return null;
        }

        $overrides = $this->loadEmailOverrides();
        $candidates = ($this->language === 'english')
            ? ['english']
            : [$this->language, 'english'];

        foreach ($candidates as $languageName) {
            if (isset($overrides[$languageName][$key]) === false) {
                continue;
            }

            $content = $overrides[$languageName][$key];
            if (trim($content) !== '') {
                return $content;
            }
        }

        return null;
    }

    /**
     * Loads the customizations for the current language and for English.
     *
     * Executed at most once per instance, and only when an email key has
     * actually been requested. Any failure (no database yet, missing table on a
     * partially upgraded instance, installer context) silently yields no
     * override so the shipped language files keep being used.
     *
     * @return array<string, array<string, string>> [language => [key => content]]
     */
    private function loadEmailOverrides(): array
    {
        if ($this->emailOverrides !== null) {
            return $this->emailOverrides;
        }

        $this->emailOverrides = [];

        if (self::emailOverridesEnabled() === false) {
            return $this->emailOverrides;
        }

        try {
            $languages = ($this->language === 'english')
                ? ['english']
                : [$this->language, 'english'];

            $rows = \DB::query(
                'SELECT template_key, language, content
                 FROM ' . \prefixTable(self::EMAIL_TEMPLATES_TABLE) . '
                 WHERE language IN %ls',
                $languages
            );

            foreach ($rows as $row) {
                $this->emailOverrides[(string) $row['language']][(string) $row['template_key']]
                    = (string) $row['content'];
            }
        } catch (\Throwable $e) {
            // Customization is a best-effort layer: never break a page or a
            // background task because the table is unavailable.
            $this->emailOverrides = [];
        }

        return $this->emailOverrides;
    }

    /**
     * Tells whether the customization layer may query the database at all.
     *
     * Guards on the availability of the database layer (the class is also
     * instantiated by the installer and by error pages) and on the
     * `emails_templates_enabled` setting, which lets support disable every
     * customization without deleting the administrator's work.
     *
     * @return bool
     */
    private static function emailOverridesEnabled(): bool
    {
        if (self::$emailOverridesEnabled !== null) {
            return self::$emailOverridesEnabled;
        }

        self::$emailOverridesEnabled = false;

        if (class_exists('\DB') === false || function_exists('prefixTable') === false) {
            return self::$emailOverridesEnabled;
        }

        try {
            $value = \DB::queryFirstField(
                'SELECT valeur FROM ' . \prefixTable('misc') . '
                 WHERE type = %s AND intitule = %s',
                'admin',
                self::EMAIL_TEMPLATES_SETTING
            );

            // Absent setting means "not configured yet" and defaults to enabled:
            // an empty templates table makes the feature a no-op anyway.
            self::$emailOverridesEnabled = ($value === null || (int) $value === 1);
        } catch (\Throwable $e) {
            self::$emailOverridesEnabled = false;
        }

        return self::$emailOverridesEnabled;
    }

    /**
     * Builds, once per process, the flat set of overridable language keys.
     *
     * @return array<string, bool> [language key => true]
     */
    private static function emailTemplateKeys(): array
    {
        if (self::$emailKeys !== null) {
            return self::$emailKeys;
        }

        self::$emailKeys = [];

        $catalog = self::loadEmailTemplatesCatalog();
        foreach ($catalog as $template) {
            if (is_array($template) === false) {
                continue;
            }

            if (empty($template['subject_key']) === false) {
                self::$emailKeys[(string) $template['subject_key']] = true;
            }
            if (empty($template['body_key']) === false) {
                self::$emailKeys[(string) $template['body_key']] = true;
            }
        }

        return self::$emailKeys;
    }

    /**
     * Reads the email templates catalog from app/config/emails_templates.php.
     *
     * The path is derived from TEAMPASS_APP when the application constants are
     * loaded, and from the language directory otherwise, so the class keeps
     * working in the CLI and installer contexts.
     *
     * @return array<string, mixed> The catalog, empty when unavailable.
     */
    private static function loadEmailTemplatesCatalog(): array
    {
        $candidates = [];
        if (defined('TEAMPASS_APP') === true) {
            $candidates[] = constant('TEAMPASS_APP') . '/config/emails_templates.php';
        }
        $candidates[] = __DIR__ . '/../../../../config/emails_templates.php';

        foreach ($candidates as $filepath) {
            if (file_exists($filepath) === false || is_file($filepath) === false) {
                continue;
            }

            $catalog = @include $filepath;
            if (is_array($catalog) === true) {
                return $catalog;
            }
        }

        return [];
    }
}