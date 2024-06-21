<?php

namespace TeampassClasses\PerformChecks;
Use DB;

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
 * @file      PerformChecks.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;

class PerformChecks
{
    private $postType;
    private $sessionVar;
    /**
     * Construct the class.
     *
     * @param string|array $postType
     * @param array $sessionVar
     */
    public function __construct($postType, $sessionVar = null)
    {
        $this->postType = (string) $postType['type'];
        $this->sessionVar = is_null($sessionVar) === true ? [] : $sessionVar;
    }

    /**
     * Checks if session variables are the expected one
     *
     * @return bool
     */
    public function checkSession(): bool
    {
        //error_log('Initial login array: '.print_r($this->sessionVar, true));
        // Check if session is valid
        if (count($this->sessionVar) > 0) {
            // if user is not logged in
            if (isset($this->sessionVar['login']) === true && is_null($this->sessionVar['login']) === false && empty($this->sessionVar['login']) === false) {
                return $this->initialLogin();
            }
            // Other cases
            if (isset($this->sessionVar['user_id']) === true && (is_null($this->sessionVar['user_id']) === true || empty($this->sessionVar['user_id']) === true)) {
                return false;
            }
            if (isset($this->sessionVar['user_key']) === true && (is_null($this->sessionVar['user_key']) === true || empty($this->sessionVar['user_key']) === true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Handle the case
     *
     * @return string
     */
    public function caseHandler(): string
    {
        switch ($this->postType) {
            case 'checkSessionExists':
                return $this->checkUserSessionExists();
        }
        return false;
    }

    /**
     * Check if user's session exists.
     *
     * @return string
     */
    function checkUserSessionExists(): string
    {
        // Case permit to check if SESSION is still valid        
        $session = SessionManager::getSession();

        if (null !== $session->get('key')) {
            return json_encode([
                'status' => true,
            ]);
        }

        // In case that no session is available
        // Force the page to be reloaded and attach the CSRFP info
        // Load CSRFP
        $csrfp_array = __DIR__ . '/../includes/libraries/csrfp/libs/csrfp.config.php';

        // Send back CSRFP info
        return $csrfp_array['CSRFP_TOKEN'] . ';' . filter_input(INPUT_POST, $csrfp_array['CSRFP_TOKEN'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }

    /**
     * Is this an initial login?
     *
     * @return bool
     */
    public function initialLogin(): bool
    {
        if (empty($this->sessionVar['user_id']) === true && empty($this->sessionVar['login']) === false && substr($_SERVER['SCRIPT_NAME'], strrpos($_SERVER['SCRIPT_NAME'], '/')+1) === 'identify.php') {
            // Check if user exists in DB
            DB::queryfirstrow(
                'SELECT id FROM ' . prefixTable('users') . ' WHERE login = %s',
                $this->sessionVar['login']
            );
            if (DB::count() > 0 || (isset($this->sessionVar['sso']) === true && (int) $this->sessionVar['sso'] === 1)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if user is allowed to open the page.
     *
     * @param string $pageVisited Page visited
     *
     * @return bool
     */
    function userAccessPage($pageVisited): bool
    {
        // Should we start?
        if (empty($pageVisited) === true) {
            return false;
        }

        // Case not user logged in
        if (empty($this->sessionVar['user_id']) === true && empty($this->sessionVar['user_key']) === true && empty($this->sessionVar['CPM']) === true && strpos($_SERVER['REQUEST_URI'] , "index.php") !== false) {
            return true;
        }
        
        // Definition
        $pagesRights = array(
            'user' => array(
                'home', 'items', 'search', 'kb', 'favourites', 'suggestion', 'profile', 'import', 'export', 'offline',
            ),
            'manager' => array(
                'home', 'items', 'search', 'kb', 'favourites', 'suggestion', 'folders', 'roles', 'utilities', 'users', 'profile',
                'import', 'export', 'offline', 'process',
                'utilities.deletion', 'utilities.renewal', 'utilities.database', 'utilities.logs', 'tasks',
            ),
            'human_resources' => array(
                'home', 'items', 'search', 'kb', 'favourites', 'suggestion', 'folders', 'roles', 'utilities', 'users', 'profile',
                'import', 'export', 'offline', 'process',
                'utilities.deletion', 'utilities.renewal', 'utilities.database', 'utilities.logs1', 'tasks',
            ),
            'admin' => array(
                'home', 'items', 'search', 'kb', 'favourites', 'suggestion', 'folders', 'manage_roles', 'manage_folders',
                'import', 'export', 'offline', 'process',
                'manage_views', 'manage_users', 'manage_settings', 'manage_main',
                'admin', 'profile', 'mfa', 'api', 'backups', 'emails', 'ldap', 'special',
                'statistics', 'fields', 'options', 'views', 'roles', 'folders', 'users', 'utilities',
                'utilities.deletion', 'utilities.renewal', 'utilities.database', 'utilities.logs', 'tasks', 'uploads', 'oauth', 'tools'
            ),
        );
        
        // Convert to array
        $pageVisited = (is_array(json_decode($pageVisited, true)) === true) ? json_decode($pageVisited, true) : [$pageVisited];
        
        // load user's data
        $data = DB::queryfirstrow(
            'SELECT id, login, key_tempo, admin, gestionnaire, can_manage_all_users FROM ' . prefixTable('users') . ' WHERE id = %i',
            $this->sessionVar['user_id']
        );
        
        // check if user exists and tempo key is coherant
        if (empty($data['login']) === true || empty($data['key_tempo']) === true || $data['key_tempo'] !== $this->sessionVar['user_key']) {
            return false;
        }
        
        if (
            ((int) $data['admin'] === 1 && $this->isValueInArray($pageVisited, $pagesRights['admin']) === true)
            ||
            (((int) $data['gestionnaire'] === 1 || (int) $data['can_manage_all_users'] === 1)
            && ($this->isValueInArray($pageVisited, array_merge($pagesRights['manager'], $pagesRights['human_resources'])) === true))
            ||
            ($this->isValueInArray($pageVisited, $pagesRights['user']) === true)
        ) {
            return true;
        }
        
        return false;
    }

    /**
     * Permits to check if at least one input is in array.
     *
     * @param array $pages Input
     * @param array $table Checked against this array
     *
     * @return bool
     */
    private function isValueInArray($pages, $table): bool
    {
        foreach ($pages as $page) {
            if (in_array($page, $table) === true) {
                return true;
            }
        }

        return false;
    }
}