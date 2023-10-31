<?php

namespace TeampassClasses\PerformChecks;

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      PerformChecks.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2023 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */

Use EZimuel\PHPSecureSession;
Use DB;

class PerformChecks
{
    /**
     * Construct the class.
     *
     * @param string|array $postType
     */
    public function __construct($postType)
    {
        $this->postType = (string) $postType['type'];
    }

    /**
     * Handle the case
     *
     * @return void
     */
    public function caseHandler()
    {
        switch ($this->postType) {
            case 'checkSessionExists':
                $this->checkUserSessionExists();
                break;
        }
    }

    /**
     * Check if user's session exists.
     *
     * @return string
     */
    function checkUserSessionExists(): string
    {
        // Case permit to check if SESSION is still valid
        session_name('teampass_session');
        session_start();

        if (isset($_SESSION['CPM']) === true) {
            echo json_encode([
                'status' => true,
            ]);
        } else {
            // In case that no session is available
            // Force the page to be reloaded and attach the CSRFP info
            // Load CSRFP
            $csrfp_array = __DIR__ . '/../includes/libraries/csrfp/libs/csrfp.config.php';

            // Send back CSRFP info
            echo $csrfp_array['CSRFP_TOKEN'] . ';' . filter_input(INPUT_POST, $csrfp_array['CSRFP_TOKEN'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
    }

    /**
     * Checks if user is allowed to open the page.
     *
     * @param int    $userId      User's ID
     * @param int    $userKey     User's temporary key
     * @param string $pageVisited Page visited
     * @param array  $SETTINGS    Settings
     *
     * @return bool
     */
    function userAccessPage($userId, $userKey, $pageVisited, $SETTINGS)
    {
        // Should we start?
        if (empty($userId) === true || empty($pageVisited) === true || empty($userKey) === true) {
            return false;
        }

        // Definition
        $pagesRights = array(
            'user' => array(
                'home', 'items', 'search', 'kb', 'favourites', 'suggestion', 'profile', 'import', 'export', 'folders', 'offline',
            ),
            'manager' => array(
                'home', 'items', 'search', 'kb', 'favourites', 'suggestion', 'folders', 'roles', 'utilities', 'users', 'profile',
                'import', 'export', 'offline', 'process',
                'utilities.deletion', 'utilities.renewal', 'utilities.database', 'utilities.logs', 'tasks',
            ),
            'human_resources' => array(
                'home', 'items', 'search', 'kb', 'favourites', 'suggestion', 'folders', 'roles', 'utilities', 'users', 'profile',
                'import', 'export', 'offline', 'process',
                'utilities.deletion', 'utilities.renewal', 'utilities.database', 'utilities.logs', 'tasks',
            ),
            'admin' => array(
                'home', 'items', 'search', 'kb', 'favourites', 'suggestion', 'folders', 'manage_roles', 'manage_folders',
                'import', 'export', 'offline', 'process',
                'manage_views', 'manage_users', 'manage_settings', 'manage_main',
                'admin', '2fa', 'profile', '2fa', 'api', 'backups', 'emails', 'ldap', 'special',
                'statistics', 'fields', 'options', 'views', 'roles', 'folders', 'users', 'utilities',
                'utilities.deletion', 'utilities.renewal', 'utilities.database', 'utilities.logs', 'tasks',
            ),
        );
        // Convert to array
        $pageVisited = (is_array(json_decode($pageVisited, true)) === true) ? json_decode($pageVisited, true) : [$pageVisited];

        // load user's data
        $data = DB::queryfirstrow(
            'SELECT login, key_tempo, admin, gestionnaire, can_manage_all_users FROM ' . prefixTable('users') . ' WHERE id = %i',
            $userId
        );

        // check if user exists and tempo key is coherant
        if (empty($data['login']) === true || empty($data['key_tempo']) === true || $data['key_tempo'] !== $userKey) {
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
    private function isValueInArray($pages, $table)
    {
        foreach ($pages as $page) {
            if (in_array($page, $table) === true) {
                return true;
            }
        }

        return false;
    }
}