<?php
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
 * @file      run.step1.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Elegant\Sanitizer\Sanitizer;
use voku\helper\AntiXSS;

// Check if function exists
if (!function_exists('dataSanitizer')) {
    /**
     * Uses Sanitizer to perform data sanitization
     *
     * @param array     $data
     * @param array     $filters
     * @return array|string
     */
    function dataSanitizer(array $data, array $filters): array|string
    {
        // Load Sanitizer library
        $sanitizer = new Sanitizer($data, $filters);

        // Load AntiXSS
        $antiXss = new AntiXSS();

        // Sanitize post and get variables
        return $antiXss->xss_clean($sanitizer->sanitize());
    }
}