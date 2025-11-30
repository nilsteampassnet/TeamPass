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
    private $language;
    private $path;
    private $translations;
    private $fallbackTranslations; // New property for English translations

    public function __construct($language = null, $path = __DIR__."/../../../../includes/language") {
        if (null === $language || empty($language) === true ) {
            $language = 'english';
        }
        $this->setLanguage($language, $path);
    }

    public function setLanguage($language, $path) {
        $this->language = $language;
        $this->path = $path;
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
     * Fallback strategy: Primary language -> English translation -> Key itself.
     *
     * @param string $key The translation key.
     * @return string The translated string.
     */
    public function get($key) {
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
}