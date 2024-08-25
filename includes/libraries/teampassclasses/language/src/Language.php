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
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

class Language {
    private $language;
    private $path;
    private $translations;

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
        // Load the translations from a file or database
        $this->translations = include $this->path."/{$this->language}.php";
    }

    public function get($key) {
        return $this->translations[$key] ?? $key;
    }
}