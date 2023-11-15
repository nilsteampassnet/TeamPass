<?php
namespace TeampassClasses\Language;

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 * @file      Language.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
*/

use TeampassClasses\SuperGlobal\SuperGlobal;

class Language {
    private $language;
    private $path;
    private $translations;

    public function __construct($language = null, $path = __DIR__."/../../../../language") {
        $superGlobal = new SuperGlobal();
        if (null === $language || empty($language) === true) {
            $userLanguage = $superGlobal->get('user_language', 'SESSION', 'user');
            if (null !== $userLanguage) {
                $language = $userLanguage;
            } else {
                $language = 'english';
            }
        }
        $this->setLanguage($language, $path);
    }

    public function setLanguage($language, $path) {
        $this->language = $language;
        $this->path = $path;
        $this->loadTranslations();
    }

    private function loadTranslations() {
        // Load the translations from a file or database
        // This is just a placeholder, replace with actual loading logic
        $this->translations = include $this->path."/{$this->language}.php";
    }

    public function get($key) {
        return $this->translations[$key] ?? $key;
    }
}