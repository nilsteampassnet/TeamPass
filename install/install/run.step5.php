<?php

require '../../vendor/autoload.php';
use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\PasswordManager\PasswordManager;

// Get some data
require_once __DIR__.'/../../includes/config/include.php';
// Load functions
include_once(__DIR__ . '/../tp.functions.php');

$superGlobal = new SuperGlobal();

// Initialize variables
$keys = [
    'action',
    'dbHost',
    'dbName',
    'dbLogin',
    'dbPw',
    'dbPort',
    'tablePrefix',
];

// Initialiser les tableaux
$inputData = [];
$filters = [];

// Boucle pour récupérer les variables POST et constituer les tableaux
foreach ($keys as $key) {
    $inputData[$key] = $superGlobal->get($key, 'POST') ?? '';
    $filters[$key] = 'trim|escape';
}
$inputData = dataSanitizer(
    $inputData,
    $filters
);

header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Perform checks
$databaseStatus = checks($inputData);

//error_log('Calling action: ' . $inputData['action']." - ".print_r($databaseStatus, true));
// Prepare the response
if ($databaseStatus['success'] === true) {
    $response = [
        'success' => true,
        'message' => '<i class="fa-solid fa-check"></i> Done',
    ];
} else {
    $response = [
        'success' => false,
        'message' => $databaseStatus['message'],
    ];
}

// Send the response
echo json_encode($response);


/**
 * Checks the data
 * 
 * @param array $inputData
 * 
 * @return string
 */
function checks($inputData): array
{
    // Initialize database connection
    DB::$host = $inputData['dbHost'];
    DB::$user = $inputData['dbLogin'];
    DB::$password = $inputData['dbPw'];
    DB::$dbName = $inputData['dbName'];
    DB::$port = $inputData['dbPort'];
    DB::$encoding = 'utf8';
    DB::$ssl = array(
        "key" => "",
        "cert" => "",
        "ca_cert" => "",
        "ca_path" => "",
        "cipher" => ""
    );
    DB::$connect_options = array(
        MYSQLI_OPT_CONNECT_TIMEOUT => 10
    );
    // Force connecting to this database
    DB::disconnect();
    DB::useDB($inputData['dbName']);

    // Get installation variables
    $installData = DB::query("SELECT `key`, `value` FROM _install");

    // Convertir en tableau associatif
    $installConfig = [];
    foreach ($installData as $row) {
        $installConfig[$row['key']] = $row['value'];
    }

    $installer = new DatabaseInstaller($inputData, $installConfig);
    $response = $installer->handleAction();
    return $response;

}

class DatabaseInstaller
{
    private $inputData;
    private $installConfig;

    public function __construct($inputData, $installConfig)
    {
        $this->inputData = $inputData;
        $this->installConfig = $installConfig;
    }

    // Méthode principale pour gérer les actions
    public function handleAction()
    {
        try {
            if (method_exists($this, $this->inputData['action'])) {
                // Appelle dynamiquement la méthode correspondant à l'action
                call_user_func([$this, $this->inputData['action']]);
            } else {
                throw new Exception('Action not recognized: ' . $this->inputData);
            }

            return [
                'success' => true,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Query error occurred: ' . $e->getMessage(),
            ];
        }
    }

    // Forcer UTF8 sur la base de données
    private function utf8()
    {
        DB::query("ALTER DATABASE %b DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci", $this->inputData['dbName']);
    }

    // Créer la table defuse_passwords
    private function defuse_passwords()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS " . $this->inputData['tablePrefix'] . "defuse_passwords (
                `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                `type` varchar(100) NOT NULL,
                `object_id` int(12) NOT NULL,
                `password` text NOT NULL,
                PRIMARY KEY (`increment_id`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table notification
    private function notification()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS " . $this->inputData['tablePrefix'] . "notification (
                `id` int(12) NOT NULL AUTO_INCREMENT,
                `message` text NOT NULL,
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`id`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table sharekeys_items
    private function sharekeys_items()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS " . $this->inputData['tablePrefix'] . "sharekeys_items (
                `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                `object_id` int(12) NOT NULL,
                `user_id` int(12) NOT NULL,
                `share_key` text NOT NULL,
                PRIMARY KEY (`increment_id`),
                INDEX idx_object_user (`object_id`, `user_id`)
            ) CHARSET=utf8;"
        );
    
        // Vérifier si les index existent
        $keyExists = DB::queryFirstField(
            "SELECT COUNT(1) 
                FROM information_schema.STATISTICS 
                WHERE table_schema = DATABASE() 
                AND table_name = %s 
                AND index_name IN ('object_id_idx', 'user_id_idx')",
            $this->inputData['tablePrefix'] . 'sharekeys_items'
        );
    
        if (intval($keyExists) === 0) {
            // Ajouter les index manquants
            DB::query(
                "ALTER TABLE " . $this->inputData['tablePrefix'] . "sharekeys_items 
                    ADD KEY `object_id_idx` (`object_id`),
                    ADD KEY `user_id_idx` (`user_id`);"
            );
        }
    }

    // Créer la table sharekeys_logs
    private function sharekeys_logs()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS " . $this->inputData['tablePrefix'] . "sharekeys_logs (
                `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                `object_id` int(12) NOT NULL,
                `user_id` int(12) NOT NULL,
                `share_key` text NOT NULL,
                PRIMARY KEY (`increment_id`)
            ) CHARSET=utf8;"
        );
    
        // Vérifier si les index existent
        $keyExists = DB::queryFirstField(
            "SELECT COUNT(1) 
                FROM information_schema.STATISTICS 
                WHERE table_schema = DATABASE() 
                AND table_name = %s 
                AND index_name IN ('object_id_idx', 'user_id_idx')",
            $this->inputData['tablePrefix'] . 'sharekeys_logs'
        );
    
        if (intval($keyExists) === 0) {
            // Ajouter les index manquants
            DB::query(
                "ALTER TABLE " . $this->inputData['tablePrefix'] . "sharekeys_logs 
                    ADD KEY `object_id_idx` (`object_id`),
                    ADD KEY `user_id_idx` (`user_id`);"
            );
        }
    }

    // Créer la table sharekeys_fields
    private function sharekeys_fields()
    {
        DB::query(
            'CREATE TABLE IF NOT EXISTS `' . $this->inputData['tablePrefix'] . 'sharekeys_fields` (
                `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                `object_id` int(12) NOT NULL,
                `user_id` int(12) NOT NULL,
                `share_key` text NOT NULL,
                PRIMARY KEY (`increment_id`)
            ) CHARSET=utf8;'
        );
    }

    // Créer la table sharekeys_suggestions
    private function sharekeys_suggestions()
    {
        DB::query(
            'CREATE TABLE IF NOT EXISTS `' . $this->inputData['tablePrefix'] . 'sharekeys_suggestions` (
                `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                `object_id` int(12) NOT NULL,
                `user_id` int(12) NOT NULL,
                `share_key` text NOT NULL,
                PRIMARY KEY (`increment_id`)
            ) CHARSET=utf8;'
        );
    }

    // Créer la table sharekeys_files
    private function sharekeys_files()
    {
        DB::query(
            'CREATE TABLE IF NOT EXISTS `' . $this->inputData['tablePrefix'] . 'sharekeys_files` (
                `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                `object_id` int(12) NOT NULL,
                `user_id` int(12) NOT NULL,
                `share_key` text NOT NULL,
                PRIMARY KEY (`increment_id`)
            ) CHARSET=utf8;'
        );
    }

    // Créer la table items
    private function items()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "items` (
            `id` int(12) NOT null AUTO_INCREMENT,
            `label` varchar(500) NOT NULL,
            `description` text DEFAULT NULL,
            `pw` text DEFAULT NULL,
            `pw_iv` text DEFAULT NULL,
            `pw_len` int(5) NOT NULL DEFAULT '0',
            `url` text DEFAULT NULL,
            `id_tree` varchar(10) DEFAULT NULL,
            `perso` tinyint(1) NOT null DEFAULT '0',
            `login` varchar(200) DEFAULT NULL,
            `inactif` tinyint(1) NOT null DEFAULT '0',
            `restricted_to` varchar(200) DEFAULT NULL,
            `anyone_can_modify` tinyint(1) NOT null DEFAULT '0',
            `email` varchar(100) DEFAULT NULL,
            `notification` varchar(250) DEFAULT NULL,
            `viewed_no` int(12) NOT null DEFAULT '0',
            `complexity_level` varchar(3) NOT null DEFAULT '-1',
            `auto_update_pwd_frequency` tinyint(2) NOT null DEFAULT '0',
            `auto_update_pwd_next_date` varchar(100) NOT null DEFAULT '0',
            `encryption_type` VARCHAR(20) NOT NULL DEFAULT 'not_set',
            `fa_icon` varchar(100) DEFAULT NULL,
            `item_key` varchar(500) NOT NULL DEFAULT '-1',
            `created_at` varchar(30) NULL,
            `updated_at` varchar(30) NULL,
            `deleted_at` varchar(30) NULL,
            PRIMARY KEY (`id`),
            KEY `restricted_inactif_idx` (`restricted_to`,`inactif`),
            INDEX items_perso_id_idx (`perso`, `id`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table log_items
    private function log_items()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "log_items` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
            `id_item` int(8) NOT NULL,
            `date` varchar(50) NOT NULL,
            `id_user` int(8) NOT NULL,
            `action` varchar(250) NULL,
            `raison` text NULL,
            `old_value` MEDIUMTEXT NULL DEFAULT NULL,
            `encryption_type` VARCHAR(20) NOT NULL DEFAULT 'not_set',
            PRIMARY KEY (`increment_id`),
            INDEX log_items_item_action_user_idx (`id_item`, `action`, `id_user`)
            ) CHARSET=utf8;"
        );
    
        // Vérifier si les index existent
        $keyExists = DB::queryFirstField(
            "SELECT COUNT(1) 
                FROM information_schema.STATISTICS 
                WHERE table_schema = DATABASE() 
                AND table_name = %s 
                AND index_name IN ('teampass_log_items_id_item_IDX')",
            $this->inputData['tablePrefix'] . 'log_items'
        );
    
        if (intval($keyExists) === 0) {
            // Ajouter les index manquants
            DB::query(
                "ALTER TABLE " . $this->inputData['tablePrefix'] . "log_items 
                    ADD KEY `teampass_log_items_id_item_IDX` (`id_item`, `date`);"
            );
        }
    }

    // Créer la table misc
    private function misc()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "misc` (
            `increment_id` int(12) NOT null AUTO_INCREMENT,
            `type` varchar(50) NOT NULL,
            `intitule` varchar(100) NOT NULL,
            `valeur` varchar(500) NOT NULL,
            `created_at` varchar(255) NULL DEFAULT NULL,
            `updated_at` varchar(255) NULL DEFAULT NULL,
            `is_encrypted` tinyint(1) NOT NULL DEFAULT '0',
            PRIMARY KEY (`increment_id`)
            ) CHARSET=utf8;"
        );

        // include constants
        require_once __DIR__.'/../../includes/config/include.php';

        // add by default settings
        $aMiscVal = array(
            array('admin', 'max_latest_items', '10'),
            array('admin', 'enable_favourites', '1'),
            array('admin', 'show_last_items', '1'),
            array('admin', 'enable_pf_feature', '0'),
            array('admin', 'log_connections', '1'),
            array('admin', 'log_accessed', '1'),
            array('admin', 'time_format', 'H:i:s'),
            array('admin', 'date_format', 'd/m/Y'),
            array('admin', 'duplicate_folder', '0'),
            array('admin', 'item_duplicate_in_same_folder', '0'),
            array('admin', 'duplicate_item', '0'),
            array('admin', 'number_of_used_pw', '3'),
            array('admin', 'manager_edit', '1'),
            array('admin', 'cpassman_dir', $this->installConfig['teampassAbsolutePath']),
            array('admin', 'cpassman_url', $this->installConfig['teampassUrl']),
            array('admin', 'favicon', $this->installConfig['teampassUrl'] . '/favicon.ico'),
            array('admin', 'path_to_upload_folder', $this->installConfig['teampassAbsolutePath'] . '/upload'),
            array('admin', 'path_to_files_folder', $this->installConfig['teampassAbsolutePath'] . '/files'),
            array('admin', 'url_to_files_folder', $this->installConfig['teampassUrl'] . '/files'),
            array('admin', 'activate_expiration', '0'),
            array('admin', 'pw_life_duration', '0'),
            array('admin', 'maintenance_mode', '1'),
            array('admin', 'enable_sts', '0'),
            array('admin', 'encryptClientServer', '1'),
            array('admin', 'teampass_version', TP_VERSION),
            array('admin', 'ldap_mode', '0'),
            array('admin', 'ldap_type', '0'),
            array('admin', 'ldap_user_attribute', '0'),
            array('admin', 'ldap_ssl', '0'),
            array('admin', 'ldap_tls', '0'),
            array('admin', 'ldap_port', '389'),
            array('admin', 'richtext', '0'),
            array('admin', 'allow_print', '0'),
            array('admin', 'roles_allowed_to_print', '0'),
            array('admin', 'show_description', '1'),
            array('admin', 'anyone_can_modify', '0'),
            array('admin', 'anyone_can_modify_bydefault', '0'),
            array('admin', 'nb_bad_authentication', '0'),
            array('admin', 'utf8_enabled', '1'),
            array('admin', 'restricted_to', '0'),
            array('admin', 'restricted_to_roles', '0'),
            array('admin', 'enable_send_email_on_user_login', '0'),
            array('admin', 'enable_user_can_create_folders', '0'),
            array('admin', 'insert_manual_entry_item_history', '0'),
            array('admin', 'enable_kb', '0'),
            array('admin', 'enable_email_notification_on_item_shown', '0'),
            array('admin', 'enable_email_notification_on_user_pw_change', '0'),
            array('admin', 'custom_logo', ''),
            array('admin', 'custom_login_text', ''),
            array('admin', 'default_language', 'english'),
            array('admin', 'send_stats', '0'),
            array('admin', 'send_statistics_items', 'stat_country;stat_users;stat_items;stat_items_shared;stat_folders;stat_folders_shared;stat_admins;stat_managers;stat_ro;stat_mysqlversion;stat_phpversion;stat_teampassversion;stat_languages;stat_kb;stat_suggestion;stat_customfields;stat_api;stat_2fa;stat_agses;stat_duo;stat_ldap;stat_syslog;stat_stricthttps;stat_fav;stat_pf;'),
            array('admin', 'send_stats_time', time() - 2592000),
            array('admin', 'get_tp_info', '1'),
            array('admin', 'send_mail_on_user_login', '0'),
            array('cron', 'sending_emails', '0'),
            array('admin', 'nb_items_by_query', 'auto'),
            array('admin', 'enable_delete_after_consultation', '0'),
            array('admin', 'enable_personal_saltkey_cookie', '0'),
            array('admin', 'personal_saltkey_cookie_duration', '31'),
            array('admin', 'email_smtp_server', ''),
            array('admin', 'email_smtp_auth', ''),
            array('admin', 'email_auth_username', '', '1'),
            array('admin', 'email_auth_pwd', '', '1'),
            array('admin', 'email_port', ''),
            array('admin', 'email_security', ''),
            array('admin', 'email_server_url', ''),
            array('admin', 'email_from', ''),
            array('admin', 'email_from_name', ''),
            array('admin', 'pwd_maximum_length', '40'),
            array('admin', 'google_authentication', '0'),
            array('admin', 'delay_item_edition', '0'),
            array('admin', 'allow_import', '0'),
            array('admin', 'proxy_ip', ''),
            array('admin', 'proxy_port', ''),
            array('admin', 'upload_maxfilesize', '10mb'),
            array('admin', 'upload_docext', 'doc,docx,dotx,xls,xlsx,xltx,rtf,csv,txt,pdf,ppt,pptx,pot,dotx,xltx'),
            array('admin', 'upload_imagesext', 'jpg,jpeg,gif,png'),
            array('admin', 'upload_pkgext', '7z,rar,tar,zip'),
            array('admin', 'upload_otherext', 'sql,xml'),
            array('admin', 'upload_imageresize_options', '1'),
            array('admin', 'upload_imageresize_width', '800'),
            array('admin', 'upload_imageresize_height', '600'),
            array('admin', 'upload_imageresize_quality', '90'),
            array('admin', 'use_md5_password_as_salt', '0'),
            array('admin', 'ga_website_name', 'TeamPass for ChangeMe'),
            array('admin', 'api', '0'),
            array('admin', 'subfolder_rights_as_parent', '0'),
            array('admin', 'show_only_accessible_folders', '0'),
            array('admin', 'enable_suggestion', '0'),
            array('admin', 'otv_expiration_period', '7'),
            array('admin', 'default_session_expiration_time', '60'),
            array('admin', 'duo', '0'),
            array('admin', 'enable_server_password_change', '0'),
            array('admin', 'bck_script_path', $this->installConfig['teampassAbsolutePath'] . '/backups'),
            array('admin', 'bck_script_filename', 'bck_teampass', '1'),
            array('admin', 'syslog_enable', '0'),
            array('admin', 'syslog_host', 'localhost'),
            array('admin', 'syslog_port', '514'),
            array('admin', 'manager_move_item', '0'),
            array('admin', 'create_item_without_password', '0'),
            array('admin', 'otv_is_enabled', '0'),
            array('admin', 'agses_authentication_enabled', '0'),
            array('admin', 'item_extra_fields', '0'),
            array('admin', 'saltkey_ante_2127', 'none'),
            array('admin', 'migration_to_2127', 'done'),
            array('admin', 'files_with_defuse', 'done'),
            array('admin', 'timezone', 'UTC'),
            array('admin', 'enable_attachment_encryption', '1'),
            array('admin', 'personal_saltkey_security_level', '50'),
            array('admin', 'ldap_new_user_is_administrated_by', '0'),
            array('admin', 'disable_show_forgot_pwd_link', '0'),
            array('admin', 'offline_key_level', '0'),
            array('admin', 'enable_http_request_login', '0'),
            array('admin', 'ldap_and_local_authentication', '0'),
            array('admin', 'secure_display_image', '1'),
            array('admin', 'upload_zero_byte_file', '0'),
            array('admin', 'upload_all_extensions_file', '0'),
            array('admin', 'bck_script_passkey', '', '1'),
            array('admin', 'admin_2fa_required', '1'),
            array('admin', 'password_overview_delay', '4'),
            array('admin', 'copy_to_clipboard_small_icons', '1'),
            array('admin', 'duo_ikey', '', '1'),
            array('admin', 'duo_skey', '', '1'),
            array('admin', 'duo_host', '', '1'),
            array('admin', 'duo_failmode', 'secure'),
            array('admin', 'roles_allowed_to_print_select', ''),
            array('admin', 'clipboard_life_duration', '30'),
            array('admin', 'mfa_for_roles', ''),
            array('admin', 'tree_counters', '0'),
            array('admin', 'settings_offline_mode', '0'),
            array('admin', 'settings_tree_counters', '0'),
            array('admin', 'enable_massive_move_delete', '0'),
            array('admin', 'email_debug_level', '0'),
            array('admin', 'ga_reset_by_user', ''),
            array('admin', 'onthefly-backup-key', ''),
            array('admin', 'onthefly-restore-key', ''),
            array('admin', 'ldap_user_dn_attribute', ''),
            array('admin', 'ldap_dn_additional_user_dn', ''),
            array('admin', 'ldap_user_object_filter', ''),
            array('admin', 'ldap_bdn', '', '1'),
            array('admin', 'ldap_hosts', '', '1'),
            array('admin', 'ldap_password', '', '1'),
            array('admin', 'ldap_username', ''),
            array('admin', 'api_token_duration', '60'),
            array('timestamp', 'last_folder_change', ''),
            array('admin', 'enable_tasks_manager', '1'),
            array('admin', 'task_maximum_run_time', '300'),
            array('admin', 'tasks_manager_refreshing_period', '20'),
            array('admin', 'maximum_number_of_items_to_treat', '100'),
            array('admin', 'number_users_build_cache_tree', '10'),
            array('admin', 'ldap_tls_certifacte_check', 'LDAP_OPT_X_TLS_NEVER'),
            array('admin', 'enable_tasks_log', '0'),
            array('admin', 'upgrade_timestamp', time()),
            array('admin', 'enable_ad_users_with_ad_groups', '0'),
            array('admin', 'enable_ad_user_auto_creation', '0'),
            array('admin', 'ldap_guid_attibute', 'objectguid'),
            array('admin', 'sending_emails_job_frequency', '2'),
            array('admin', 'user_keys_job_frequency', '1'),
            array('admin', 'items_statistics_job_frequency', '5'),
            array('admin', 'users_personal_folder_task', ''),
            array('admin', 'clean_orphan_objects_task', ''),
            array('admin', 'purge_temporary_files_task', ''),
            array('admin', 'rebuild_config_file', ''),
            array('admin', 'reload_cache_table_task', ''),
            array('admin', 'maximum_session_expiration_time', '60'),
            array('admin', 'items_ops_job_frequency', '1'),
            array('admin', 'enable_refresh_task_last_execution', '1'),
            array('admin', 'ldap_group_objectclasses_attibute', 'top,groupofuniquenames'),
            array('admin', 'pwd_default_length', '14'),
            array('admin', 'tasks_log_retention_delay', '30'),
            array('admin', 'oauth2_enabled', '0'),
            array('admin', 'oauth2_client_id', '', '1'),
            array('admin', 'oauth2_client_secret', '', '1'),
            array('admin', 'oauth2_client_endpoint', ''),
            array('admin', 'oauth2_client_urlResourceOwnerDetails', ''),
            array('admin', 'oauth2_client_token', '', '1'),
            array('admin', 'oauth2_client_scopes', 'openid,profile,email,User.Read,Group.Read.All'),
            array('admin', 'oauth2_client_appname', 'Login with Azure'),
            array('admin', 'show_item_data', '0'),
            array('admin', 'oauth2_tenant_id', '', '1'),
            array('admin', 'limited_search_default', '0'),
            array('admin', 'highlight_selected', '0'),
            array('admin', 'highlight_favorites', '0'),
        );
        foreach ($aMiscVal as $elem) {
            //Check if exists before inserting
            $tmp = DB::queryFirstField(
                "SELECT COUNT(*) FROM " . $this->inputData['tablePrefix'] . "misc 
                WHERE type = %s AND intitule = %s",
                $elem[0],                  // Type
                $elem[1]                   // Intitule
            );
            
            if (intval($tmp) === 0) {
                $value = isset($elem[3]) ? $elem[3] : 0;
            
                // Insert data using MeekroDB
                DB::insert($this->inputData['tablePrefix'] . 'misc', [
                    'type'         => $elem[0],
                    'intitule'     => $elem[1],
                    'valeur'       => str_replace("'", '', $elem[2]),
                    'created_at'   => $elem[1],
                    'is_encrypted' => $value
                ]);
            }
        }
    }

    // Créer la table nested_tree
    private function nested_tree()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "nested_tree` (
            `id` bigint(20) unsigned NOT null AUTO_INCREMENT,
            `parent_id` int(11) NOT NULL,
            `title` varchar(255) NOT NULL,
            `nleft` int(11) NOT NULL DEFAULT '0',
            `nright` int(11) NOT NULL DEFAULT '0',
            `nlevel` int(11) NOT NULL DEFAULT '0',
            `bloquer_creation` tinyint(1) NOT null DEFAULT '0',
            `bloquer_modification` tinyint(1) NOT null DEFAULT '0',
            `personal_folder` tinyint(1) NOT null DEFAULT '0',
            `renewal_period` int(5) NOT null DEFAULT '0',
            `fa_icon` VARCHAR(100) NOT NULL DEFAULT 'fas fa-folder',
            `fa_icon_selected` VARCHAR(100) NOT NULL DEFAULT 'fas fa-folder-open',
            `categories` longtext NOT NULL,
            `nb_items_in_folder` int(10) NOT NULL DEFAULT '0',
            `nb_subfolders` int(10) NOT NULL DEFAULT '0',
            `nb_items_in_subfolders` int(10) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`),
            KEY `nested_tree_parent_id` (`parent_id`),
            KEY `nested_tree_nleft` (`nleft`),
            KEY `nested_tree_nright` (`nright`),
            KEY `nested_tree_nlevel` (`nlevel`),
            KEY `personal_folder_idx` (`personal_folder`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table rights
    private function rights()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "rights` (
            `id` int(12) NOT null AUTO_INCREMENT,
            `tree_id` int(12) NOT NULL,
            `fonction_id` int(12) NOT NULL,
            `authorized` tinyint(1) NOT null DEFAULT '0',
            PRIMARY KEY (`id`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table users
    private function users()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "users` (
            `id` int(12) NOT null AUTO_INCREMENT,
            `login` varchar(500) NOT NULL,
            `pw` varchar(400) NOT NULL,
            `groupes_visibles` varchar(1000) NOT NULL,
            `derniers` text NULL DEFAULT NULL,
            `key_tempo` varchar(100) NULL DEFAULT NULL,
            `last_pw_change` varchar(30) NULL DEFAULT NULL,
            `last_pw` text NULL DEFAULT NULL,
            `admin` tinyint(1) NOT null DEFAULT '0',
            `fonction_id` varchar(1000) NULL DEFAULT NULL,
            `groupes_interdits` varchar(1000) NULL DEFAULT NULL,
            `last_connexion` varchar(30) NULL DEFAULT NULL,
            `gestionnaire` int(11) NOT null DEFAULT '0',
            `email` varchar(300) NOT NULL DEFAULT 'none',
            `favourites` varchar(1000) NULL DEFAULT NULL,
            `latest_items` varchar(1000) NULL DEFAULT NULL,
            `personal_folder` int(1) NOT null DEFAULT '0',
            `disabled` tinyint(1) NOT null DEFAULT '0',
            `can_create_root_folder` tinyint(1) NOT null DEFAULT '0',
            `read_only` tinyint(1) NOT null DEFAULT '0',
            `timestamp` varchar(30) NOT null DEFAULT '0',
            `user_language` varchar(50) NOT null DEFAULT '0',
            `name` varchar(100) NULL DEFAULT NULL,
            `lastname` varchar(100) NULL DEFAULT NULL,
            `session_end` varchar(30) NULL DEFAULT NULL,
            `isAdministratedByRole` tinyint(5) NOT null DEFAULT '0',
            `psk` varchar(400) NULL DEFAULT NULL,
            `ga` varchar(50) NULL DEFAULT NULL,
            `ga_temporary_code` VARCHAR(20) NOT NULL DEFAULT 'none',
            `avatar` varchar(1000) NULL DEFAULT NULL,
            `avatar_thumb` varchar(1000) NULL DEFAULT NULL,
            `upgrade_needed` BOOLEAN NOT NULL DEFAULT FALSE,
            `treeloadstrategy` varchar(30) NOT null DEFAULT 'full',
            `can_manage_all_users` tinyint(1) NOT NULL DEFAULT '0',
            `usertimezone` VARCHAR(50) NOT NULL DEFAULT 'not_defined',
            `agses-usercardid` VARCHAR(50) NOT NULL DEFAULT '0',
            `encrypted_psk` text NULL DEFAULT NULL,
            `user_ip` varchar(400) NOT null DEFAULT 'none',
            `user_ip_lastdate` varchar(50) NULL DEFAULT NULL,
            `yubico_user_key` varchar(100) NOT null DEFAULT 'none',
            `yubico_user_id` varchar(100) NOT null DEFAULT 'none',
            `public_key` TEXT NULL DEFAULT NULL,
            `private_key` TEXT NULL DEFAULT NULL,
            `special` VARCHAR(250) NOT NULL DEFAULT 'none',
            `auth_type` VARCHAR(200) NOT NULL DEFAULT 'local',
            `is_ready_for_usage` BOOLEAN NOT NULL DEFAULT FALSE,
            `otp_provided` BOOLEAN NOT NULL DEFAULT FALSE,
            `roles_from_ad_groups` varchar(1000) NULL DEFAULT NULL,
            `ongoing_process_id` VARCHAR(100) NULL DEFAULT NULL,
            `mfa_enabled` tinyint(1) NOT null DEFAULT '1',
            `created_at` varchar(30) NULL DEFAULT NULL,
            `updated_at` varchar(30) NULL DEFAULT NULL,
            `deleted_at` varchar(30) NULL DEFAULT NULL,
            `keys_recovery_time` VARCHAR(500) NULL DEFAULT NULL,
            `aes_iv` TEXT NULL DEFAULT NULL,
            `split_view_mode` tinyint(1) NOT null DEFAULT '0',
            PRIMARY KEY (`id`),
            UNIQUE KEY `login` (`login`)
            ) CHARSET=utf8;"
        );

        require_once __DIR__.'/../../includes/config/include.php';

        // Hash password
        $passwordManager = new PasswordManager();
        $hashedPassword = $passwordManager->hashPassword($this->installConfig['adminPassword']);

        // check that admin accounts doesn't exist
        $adminExists = DB::queryFirstField(
            "SELECT COUNT(*) FROM ".$this->inputData['tablePrefix']."users WHERE login = %s",
            'admin'                          // Login à vérifier
        );

        // Si l'utilisateur n'existe pas, insérer un nouvel utilisateur
        if (intval($adminExists) === 0) {
            DB::insert($this->inputData['tablePrefix'] . 'users', [
                'id'                     => 1,
                'login'                  => 'admin',
                'pw'                     => $hashedPassword,
                'admin'                  => 1,
                'gestionnaire'           => 0,
                'personal_folder'        => 0,
                'groupes_visibles'       => 0,
                'email'                  => $this->installConfig['adminEmail'],
                'encrypted_psk'          => '',
                'last_pw_change'         => time(),
                'name'                   => 'Change me',
                'lastname'               => 'Change me',
                'can_create_root_folder' => 1,
                'public_key'             => 'none',
                'private_key'            => 'none',
                'is_ready_for_usage'     => 1,
                'otp_provided'           => 1,
                'created_at'             => time(),
            ]);
        } else {
            // Mettre à jour le mot de passe pour l'utilisateur "admin"
            DB::update($this->inputData['tablePrefix'] . 'users', [
                'pw' => $hashedPassword
            ], "login = %s AND id = %i", 'admin', 1);
        }

        // check that API doesn't exist
        $apiUserExists = DB::queryFirstField(
            "SELECT COUNT(*) FROM ".$this->inputData['tablePrefix']."users WHERE id = %i",
            API_USER_ID                     // ID à vérifier
        );
        
        if (intval($apiUserExists) === 0) {
            // Insère un utilisateur API par défaut
            DB::insert($this->inputData['tablePrefix'] . 'users', [
                'id'                     => API_USER_ID,
                'login'                  => 'API',
                'pw'                     => '',            // Mot de passe vide
                'groupes_visibles'       => '',            // Valeurs par défaut
                'derniers'               => '',
                'key_tempo'              => '',
                'last_pw_change'         => '',
                'last_pw'                => '',
                'admin'                  => 1,             // Utilisateur admin
                'fonction_id'            => '',
                'groupes_interdits'      => '',
                'last_connexion'         => '',
                'gestionnaire'           => 0,
                'email'                  => '',
                'favourites'             => '',
                'latest_items'           => '',
                'personal_folder'        => 0,
                'is_ready_for_usage'     => 1,
                'otp_provided'           => 0,
                'created_at'             => time(),        // Date actuelle
            ]);
        }

        // check that OTV doesn't exist
        $otvUserExists = DB::queryFirstField(
            "SELECT COUNT(*) FROM ".$this->inputData['tablePrefix']."users WHERE id = %i",
            OTV_USER_ID                      // ID à vérifier
        );

        if (intval($otvUserExists) === 0) {
            // Insère un utilisateur OTV par défaut
            DB::insert($this->inputData['tablePrefix'] . 'users', [
                'id'                     => OTV_USER_ID,
                'login'                  => 'OTV',
                'pw'                     => '',            // Mot de passe vide
                'groupes_visibles'       => '',            // Valeurs par défaut
                'derniers'               => '',
                'key_tempo'              => '',
                'last_pw_change'         => '',
                'last_pw'                => '',
                'admin'                  => 1,             // Utilisateur admin
                'fonction_id'            => '',
                'groupes_interdits'      => '',
                'last_connexion'         => '',
                'gestionnaire'           => 0,
                'email'                  => '',
                'favourites'             => '',
                'latest_items'           => '',
                'personal_folder'        => 0,
                'is_ready_for_usage'     => 1,
                'otp_provided'           => 0,
                'created_at'             => time(),        // Date actuelle
            ]);
        }
    }

    // Créer la table tags
    private function tags()
    {
        DB::query(
            'CREATE TABLE IF NOT EXISTS `' . $this->inputData['tablePrefix'] . 'tags` (
            `id` int(12) NOT null AUTO_INCREMENT,
            `tag` varchar(30) NOT NULL,
            `item_id` int(12) NOT NULL,
            PRIMARY KEY (`id`)
            ) CHARSET=utf8;'
        );
    }

    // Créer la table log_system
    private function log_system()
    {
        DB::query(
            'CREATE TABLE IF NOT EXISTS `' . $this->inputData['tablePrefix'] . 'log_system` (
            `id` int(12) NOT null AUTO_INCREMENT,
            `type` varchar(20) NOT NULL,
            `date` varchar(30) NOT NULL,
            `label` text NOT NULL,
            `qui` varchar(255) NOT NULL,
            `field_1` varchar(250) DEFAULT NULL,
            PRIMARY KEY (`id`)
            ) CHARSET=utf8;'
        );
    }

    // Créer la table files
    private function files()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "files` (
            `id` int(11) NOT null AUTO_INCREMENT,
            `id_item` int(11) NOT NULL,
            `name` TEXT NOT NULL,
            `size` int(10) NOT NULL,
            `extension` varchar(10) NOT NULL,
            `type` varchar(255) NOT NULL,
            `file` varchar(50) NOT NULL,
            `status` varchar(50) NOT NULL DEFAULT '0',
            `content` longblob DEFAULT NULL,
            `confirmed` INT(1) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table cache
    private function cache()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "cache` (
            `increment_id`INT(12) NOT NULL AUTO_INCREMENT,
            `id` int(12) NOT NULL,
            `label` varchar(500) NOT NULL,
            `description` MEDIUMTEXT NULL DEFAULT NULL,
            `tags` text DEFAULT NULL,
            `id_tree` int(12) NOT NULL,
            `perso` tinyint(1) NOT NULL,
            `restricted_to` varchar(200) DEFAULT NULL,
            `login` text DEFAULT NULL,
            `folder` text NOT NULL,
            `author` varchar(50) NOT NULL,
            `renewal_period` tinyint(4) NOT NULL DEFAULT '0',
            `timestamp` varchar(50) DEFAULT NULL,
            `url` text NULL DEFAULT NULL,
            `encryption_type` VARCHAR(50) DEFAULT NULL DEFAULT '0',
            PRIMARY KEY (`increment_id`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table roles_title
    private function roles_title()
    {
        // Créer la table roles_title si elle n'existe pas
        DB::query(
            "CREATE TABLE IF NOT EXISTS " . $this->inputData['tablePrefix'] . "roles_title (
                `id` int(12) NOT null AUTO_INCREMENT,
                `title` varchar(50) NOT NULL,
                `allow_pw_change` TINYINT(1) NOT null DEFAULT '0',
                `complexity` INT(5) NOT null DEFAULT '0',
                `creator_id` int(11) NOT null DEFAULT '0',
                PRIMARY KEY (`id`)
            ) CHARSET=utf8;"
        );

        // Vérifier si le rôle par défaut existe
        $tmp = DB::queryFirstField(
            "SELECT COUNT(*) 
            FROM " . $this->inputData['tablePrefix'] . "roles_title 
            WHERE id = 0"
        );

        if (intval($tmp) === 0) {
            // Insérer le rôle par défaut
            DB::insert($this->inputData['tablePrefix'] . 'roles_title', [
                'id'             => null,
                'title'          => 'Default',
                'allow_pw_change'=> 0,
                'complexity'     => 48,
                'creator_id'     => 0
            ]);
        }
    }

    // Créer la table roles_values
    private function roles_values()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "roles_values` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `role_id` int(12) NOT NULL,
            `folder_id` int(12) NOT NULL,
            `type` varchar(5) NOT NULL DEFAULT 'R',
            KEY `role_id_idx` (`role_id`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table kb
    private function kb()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "kb` (
            `id` int(12) NOT null AUTO_INCREMENT,
            `category_id` int(12) NOT NULL,
            `label` varchar(200) NOT NULL,
            `description` text NOT NULL,
            `author_id` int(12) NOT NULL,
            `anyone_can_modify` tinyint(1) NOT null DEFAULT '0',
            PRIMARY KEY (`id`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table kb_categories
    private function kb_categories()
    {
        DB::query(
            'CREATE TABLE IF NOT EXISTS `' . $this->inputData['tablePrefix'] . 'kb_categories` (
            `id` int(12) NOT null AUTO_INCREMENT,
            `category` varchar(50) NOT NULL,
            PRIMARY KEY (`id`)
            ) CHARSET=utf8;'
        );
    }

    // Créer la table kb_items
    private function kb_items()
    {
        DB::query(
            'CREATE TABLE IF NOT EXISTS `' . $this->inputData['tablePrefix'] . 'kb_items` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
            `kb_id` int(12) NOT NULL,
            `item_id` int(12) NOT NULL,
            PRIMARY KEY (`increment_id`)
            ) CHARSET=utf8;'
        );
    }

    // Créer la table restriction_to_roles
    private function restriction_to_roles()
    {
        DB::query(
            'CREATE TABLE IF NOT EXISTS `' . $this->inputData['tablePrefix'] . 'restriction_to_roles` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
            `role_id` int(12) NOT NULL,
            `item_id` int(12) NOT NULL,
            PRIMARY KEY (`increment_id`)
            ) CHARSET=utf8;'
        );
    }

    // Créer la table languages
    private function languages()
    {
        DB::query(
            'CREATE TABLE IF NOT EXISTS ' . $this->inputData['tablePrefix'] . 'languages (
            `id` INT(10) NOT null AUTO_INCREMENT,
            `name` VARCHAR(50) NOT null ,
            `label` VARCHAR(50) NOT null ,
            `code` VARCHAR(10) NOT null ,
            `flag` VARCHAR(50) NOT NULL,
            `code_poeditor` VARCHAR(30) NOT NULL,
            PRIMARY KEY (`id`)
            ) CHARSET=utf8;'
        );

        // add lanaguages
        $tmp = DB::queryFirstField(
            "SELECT COUNT(*) FROM " . $this->inputData['tablePrefix'] . "languages WHERE name = %s",
            'french'                    // Login à vérifier
        );
        if ($tmp === 0) {
            DB::query(
                "INSERT INTO " . $this->inputData['tablePrefix'] . "languages (`id`, `name`, `label`, `code`, `flag`, `code_poeditor`) VALUES
                (1, 'french', 'French', 'fr', 'fr.png', 'fr'),
                (2, 'english', 'English', 'us', 'us.png', 'en'),
                (3, 'spanish', 'Spanish', 'es', 'es.png', 'es'),
                (4, 'german', 'German', 'de', 'de.png', 'de'),
                (5, 'czech', 'Czech', 'cs', 'cz.png', 'cs'),
                (6, 'italian', 'Italian', 'it', 'it.png', 'it'),
                (7, 'russian', 'Russian', 'ru', 'ru.png', 'ru'),
                (8, 'turkish', 'Turkish', 'tr', 'tr.png', 'tr'),
                (9, 'norwegian', 'Norwegian', 'no', 'no.png', 'no'),
                (10, 'japanese', 'Japanese', 'ja', 'ja.png', 'ja'),
                (11, 'portuguese', 'Portuguese', 'pr', 'pr.png', 'pt'),
                (12, 'portuguese_br', 'Portuguese (Brazil)', 'pr-bt', 'pr-bt.png', 'pt-br'),
                (13, 'chinese', 'Chinese', 'zh-Hans', 'cn.png', 'zh-Hans'),
                (14, 'swedish', 'Swedish', 'se', 'se.png', 'sv'),
                (15, 'dutch', 'Dutch', 'nl', 'nl.png', 'nl'),
                (16, 'catalan', 'Catalan', 'ca', 'ct.png', 'ca'),
                (17, 'bulgarian', 'Bulgarian', 'bg', 'bg.png', 'bg'),
                (18, 'greek', 'Greek', 'gr', 'gr.png', 'el'),
                (19, 'hungarian', 'Hungarian', 'hu', 'hu.png', 'hu'),
                (20, 'polish', 'Polish', 'pl', 'pl.png', 'pl'),
                (21, 'romanian', 'Romanian', 'ro', 'ro.png', 'ro'),
                (22, 'ukrainian', 'Ukrainian', 'ua', 'ua.png', 'uk'),
                (23, 'vietnamese', 'Vietnamese', 'vi', 'vi.png', 'vi'),
                (24, 'estonian', 'Estonian', 'et', 'ee.png', 'et');"
            );
        }
    }

    // Créer la table emails
    private function emails()
    {
        DB::query(
            'CREATE TABLE IF NOT EXISTS `' . $this->inputData['tablePrefix'] . 'emails` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
            `timestamp` INT(30) NOT null ,
            `subject` TEXT NOT null ,
            `body` TEXT NOT null ,
            `receivers` TEXT NOT null ,
            `status` VARCHAR(30) NOT NULL,
            PRIMARY KEY (`increment_id`)
            ) CHARSET=utf8;'
        );
    }

    // Créer la table automatic_del
    private function automatic_del()
    {
        DB::query(
            'CREATE TABLE IF NOT EXISTS `' . $this->inputData['tablePrefix'] . 'automatic_del` (
            `item_id` int(11) NOT NULL,
            `del_enabled` tinyint(1) NOT NULL,
            `del_type` tinyint(1) NOT NULL,
            `del_value` varchar(35) NOT NULL,
            PRIMARY KEY (`item_id`)
            ) CHARSET=utf8;'
        );
    }

    // Créer la table items_edition
    private function items_edition()
    {
        DB::query(
            'CREATE TABLE IF NOT EXISTS `' . $this->inputData['tablePrefix'] . 'items_edition` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
            `item_id` int(11) NOT NULL,
            `user_id` int(12) NOT NULL,
            `timestamp` varchar(50) NOT NULL,
            KEY `item_id_idx` (`item_id`),
            PRIMARY KEY (`increment_id`)
            ) CHARSET=utf8;'
        );
    }

    // Créer la table categories
    private function categories()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "categories` (
            `id` int(12) NOT NULL AUTO_INCREMENT,
            `parent_id` int(12) NOT NULL,
            `title` varchar(255) NOT NULL,
            `level` int(2) NOT NULL,
            `description` text NULL,
            `type` varchar(50) NULL default '',
            `masked` tinyint(1) NOT NULL default '0',
            `order` int(12) NOT NULL default '0',
            `encrypted_data` tinyint(1) NOT NULL default '1',
            `role_visibility` varchar(255) NOT NULL DEFAULT 'all',
            `is_mandatory` tinyint(1) NOT NULL DEFAULT '0',
            `regex` varchar(255) NULL default '',
            PRIMARY KEY (`id`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table categories_items
    private function categories_items()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "categories_items` (
            `id` int(12) NOT NULL AUTO_INCREMENT,
            `field_id` int(11) NOT NULL,
            `item_id` int(11) NOT NULL,
            `data` text NOT NULL,
            `data_iv` text NOT NULL,
            `encryption_type` VARCHAR(20) NOT NULL DEFAULT 'not_set',
            `is_mandatory` BOOLEAN NOT NULL DEFAULT FALSE ,
            PRIMARY KEY (`id`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table categories_folders
    private function categories_folders()
    {
        DB::query(
            'CREATE TABLE IF NOT EXISTS `' . $this->inputData['tablePrefix'] . 'categories_folders` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
            `id_category` int(12) NOT NULL,
            `id_folder` int(12) NOT NULL,
            PRIMARY KEY (`increment_id`)
            ) CHARSET=utf8;'
        );
    }

    // Créer la table api
    private function api()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "api` (
            `increment_id` int(20) NOT NULL AUTO_INCREMENT,
            `type` varchar(15) NOT NULL,
            `label` varchar(255) DEFAULT NULL,
            `value` text DEFAULT NULL,
            `timestamp` varchar(50) NOT NULL,
            `user_id` int(13) DEFAULT NULL,
            `allowed_folders` text NULL DEFAULT NULL,
            `enabled` int(1) NOT NULL DEFAULT '0',
            `allowed_to_create` int(1) NOT NULL DEFAULT '0',
            `allowed_to_read` int(1) NOT NULL DEFAULT '1',
            `allowed_to_update` int(1) NOT NULL DEFAULT '0',
            `allowed_to_delete` int(1) NOT NULL DEFAULT '0',
            PRIMARY KEY (`increment_id`),
            KEY `USER` (`user_id`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table otv
    private function otv()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "otv` (
            `id` int(10) NOT NULL AUTO_INCREMENT,
            `timestamp` text NOT NULL,
            `code` varchar(100) NOT NULL,
            `item_id` int(12) NOT NULL,
            `originator` int(12) NOT NULL,
            `encrypted` text NOT NULL,
            `views` INT(10) NOT NULL DEFAULT '0',
            `max_views` INT(10) NULL DEFAULT NULL,
            `time_limit` varchar(100) DEFAULT NULL,
            `shared_globaly` INT(1) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table suggestion
    private function suggestion()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "suggestion` (
            `id` tinyint(12) NOT NULL AUTO_INCREMENT,
            `label` varchar(255) NOT NULL,
            `pw` text NOT NULL,
            `pw_iv` text NOT NULL,
            `pw_len` int(5) NOT NULL,
            `description` text NOT NULL,
            `author_id` int(12) NOT NULL,
            `folder_id` int(12) NOT NULL,
            `comment` text NOT NULL,
            `suggestion_type` varchar(10) NOT NULL default 'new',
            `encryption_type` varchar(20) NOT NULL default 'not_set',
            PRIMARY KEY (`id`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table export
    private function export()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "export` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
            `export_tag` varchar(20) NOT NULL,
            `item_id` int(12) NOT NULL,
            `label` varchar(500) NOT NULL,
            `login` varchar(100) NOT NULL,
            `description` text NOT NULL,
            `pw` text NOT NULL,
            `path` varchar(500) NOT NULL,
            `email` varchar(500) NOT NULL default 'none',
            `url` varchar(500) NOT NULL default 'none',
            `kbs` varchar(500) NOT NULL default 'none',
            `tags` varchar(500) NOT NULL default 'none',
            `folder_id` varchar(10) NOT NULL,
            `perso` tinyint(1) NOT NULL default '0',
            `restricted_to` varchar(200) DEFAULT NULL,
            PRIMARY KEY (`increment_id`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table tokens
    private function tokens()
    {
        DB::query(
            'CREATE TABLE IF NOT EXISTS `' . $this->inputData['tablePrefix'] . 'tokens` (
            `id` int(12) NOT NULL AUTO_INCREMENT,
            `user_id` int(12) NOT NULL,
            `token` varchar(255) NOT NULL,
            `reason` varchar(255) NOT NULL,
            `creation_timestamp` varchar(50) NOT NULL,
            `end_timestamp` varchar(50) DEFAULT NULL,
            PRIMARY KEY (`id`)
            ) CHARSET=utf8;'
        );
    }

    // Créer la table items_change
    private function items_change()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "items_change` (
            `id` int(12) NOT NULL AUTO_INCREMENT,
            `item_id` int(12) NOT NULL,
            `label` varchar(255) NOT NULL DEFAULT 'none',
            `pw` text NOT NULL,
            `login` varchar(255) NOT NULL DEFAULT 'none',
            `email` varchar(255) NOT NULL DEFAULT 'none',
            `url` varchar(255) NOT NULL DEFAULT 'none',
            `description` text NOT NULL,
            `comment` text NOT NULL,
            `folder_id` tinyint(12) NOT NULL,
            `user_id` int(12) NOT NULL,
            `timestamp` varchar(50) NOT NULL DEFAULT 'none',
            PRIMARY KEY (`id`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table templates
    private function templates()
    {
        DB::query(
            'CREATE TABLE IF NOT EXISTS `' . $this->inputData['tablePrefix'] . 'templates` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
            `item_id` int(12) NOT NULL,
            `category_id` int(12) NOT NULL,
            PRIMARY KEY (`increment_id`)
            ) CHARSET=utf8;'
        );
    }

    // Créer la table cache_tree
    private function cache_tree()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "cache_tree` (
            `increment_id` smallint(32) NOT NULL AUTO_INCREMENT,
            `data` longtext DEFAULT NULL CHECK (json_valid(`data`)),
            `visible_folders` longtext NOT NULL,
            `timestamp` varchar(50) NOT NULL,
            `user_id` int(12) NOT NULL,
            `folders` longtext DEFAULT NULL,
            PRIMARY KEY (`increment_id`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table background_subtasks
    private function background_subtasks()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "background_subtasks` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
            `task_id` int(12) NOT NULL,
            `created_at` varchar(50) NOT NULL,
            `updated_at` varchar(50) DEFAULT NULL,
            `finished_at` varchar(50) DEFAULT NULL,
            `task` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`task`)),
            `process_id` varchar(100) NULL DEFAULT NULL,
            `is_in_progress` tinyint(1) NOT NULL DEFAULT 0,
            `sub_task_in_progress` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`increment_id`)
            ) CHARSET=utf8;"
        );

        // Vérifier si l'index existe dans la table
        $keyExists = DB::queryFirstField(
            "SELECT COUNT(1) 
            FROM information_schema.STATISTICS 
            WHERE table_schema = DATABASE() 
            AND table_name = %s 
            AND index_name IN ('task_id_idx')",
            $this->inputData['tablePrefix'] . 'background_subtasks'
        );

        // Si l'index n'existe pas, exécutez la requête ALTER TABLE
        if (intval($keyExists) === 0) {
            DB::query(
                "ALTER TABLE " . $this->inputData['tablePrefix'] . "background_subtasks ADD KEY `task_id_idx` (`task_id`)"
            );
        }
    }

    // Créer la table background_tasks
    private function background_tasks()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "background_tasks` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
            `created_at` varchar(50) NOT NULL,
            `started_at` varchar(50) DEFAULT NULL,
            `updated_at` varchar(50) DEFAULT NULL,
            `finished_at` varchar(50) DEFAULT NULL,
            `process_id` int(12) DEFAULT NULL,
            `process_type` varchar(100) NOT NULL,
            `output` text DEFAULT NULL,
            `arguments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`arguments`)),
            `is_in_progress` tinyint(1) NOT NULL DEFAULT 0,
            `item_id` INT(12) NULL,
            PRIMARY KEY (`increment_id`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table background_tasks_logs
    private function background_tasks_logs()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "background_tasks_logs` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
            `created_at` INT NOT NULL,
            `job` varchar(50) NOT NULL,
            `status` varchar(10) NOT NULL,
            `updated_at` INT DEFAULT NULL,
            `finished_at` INT DEFAULT NULL,
            `treated_objects` varchar(20) DEFAULT NULL,
            PRIMARY KEY (`increment_id`),
            INDEX idx_created_at (`created_at`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table ldap_groups_roles
    private function ldap_groups_roles()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "ldap_groups_roles` (
            `increment_id` INT(12) NOT NULL AUTO_INCREMENT,
            `role_id` INT(12) NOT NULL,
            `ldap_group_id` VARCHAR(500) NOT NULL,
            `ldap_group_label` VARCHAR(255) NOT NULL,
            PRIMARY KEY (`increment_id`),
            KEY `ROLE` (`role_id`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table items_otp
    private function items_otp()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "items_otp` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
            `item_id` int(12) NOT NULL,
            `secret` text NOT NULL,
            `timestamp` varchar(100) NOT NULL,
            `enabled` tinyint(1) NOT NULL DEFAULT 0,
            `phone_number` varchar(25) NOT NULL,
            PRIMARY KEY (`increment_id`),
            KEY `ITEM` (`item_id`)
            ) CHARSET=utf8;"
        );
    }

    // Créer la table auth_failures
    private function auth_failures()
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "auth_failures` (
            `id` int(12) NOT NULL AUTO_INCREMENT,
            `source` ENUM('login', 'remote_ip') NOT NULL,
            `value` VARCHAR(500) NOT NULL,
            `date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `unlock_at` TIMESTAMP NULL DEFAULT NULL,
            `unlock_code` VARCHAR(50) NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
            ) CHARSET=utf8;"
        );
    }
}


/*
    error_log(print_r($inputData, true));
    try {        
        if ($inputData['action'] === 'utf8') {
            //FORCE UTF8 DATABASE
            DB::query('ALTER DATABASE `' . $this->inputData['dbName'] . '` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci');

        } elseif ($inputData['action'] === 'defuse_passwords') {

            DB::query(
                'CREATE TABLE IF NOT EXISTS `' . $this->inputData['tablePrefix'] . 'defuse_passwords` (
                    `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                    `type` varchar(100) NOT NULL,
                    `object_id` int(12) NOT NULL,
                    `password` text NOT NULL,
                    PRIMARY KEY (`increment_id`)
                ) CHARSET=utf8;'
            );

        } elseif ($inputData['action'] === 'notification') {
            DB::query(
                'CREATE TABLE IF NOT EXISTS `' . $this->inputData['tablePrefix'] . 'notification` (
                    `increment_id` INT(12) NOT NULL AUTO_INCREMENT,
                    `item_id` INT(12) NOT NULL,
                    `user_id` INT(12) NOT NULL,
                    PRIMARY KEY (`increment_id`)
                ) CHARSET=utf8;'
            );

        } elseif ($inputData['action'] === 'sharekeys_items') {
            // Créer la table si elle n'existe pas
            
        } elseif ($inputData['action'] === 'sharekeys_logs') {
            // Créer la table si elle n'existe pas
                

        } elseif ($inputData['action'] === 'sharekeys_fields') {
            

        } elseif ($inputData['action'] === 'sharekeys_suggestions') {
            

        } elseif ($inputData['action'] === 'sharekeys_files') {
            

        } elseif ($inputData['action'] === 'items') {
            

        } elseif ($inputData['action'] === 'log_items') {
            

        } elseif ($inputData['action'] === 'misc') {
            

            // --

        } elseif ($inputData['action'] === 'nested_tree') {
            

        } elseif ($inputData['action'] === 'rights') {
            

        } elseif ($inputData['action'] === 'users') {
            

        } elseif ($inputData['action'] === 'tags') {
            

        } elseif ($inputData['action'] === 'log_system') {
            

        } elseif ($inputData['action'] === 'files') {
            

        } elseif ($inputData['action'] === 'cache') {
            

        } elseif ($inputData['action'] === 'roles_title') {
            

        } elseif ($inputData['action'] === 'roles_values') {
            

        } elseif ($inputData['action'] === 'kb') {
            

        } elseif ($inputData['action'] === 'kb_categories') {
            

        } elseif ($inputData['action'] === 'kb_items') {
            

        } elseif ($inputData['action'] == 'restriction_to_roles') {
            

        } elseif ($inputData['action'] === 'languages') {
            

        } elseif ($inputData['action'] === 'emails') {
            

        } elseif ($inputData['action'] === 'automatic_del') {
            

        } elseif ($inputData['action'] === 'items_edition') {
            

        } elseif ($inputData['action'] === 'categories') {
            

        } elseif ($inputData['action'] === 'categories_items') {
           

        } elseif ($inputData['action'] === 'categories_folders') {
            

        } elseif ($inputData['action'] === 'api') {
            

        } elseif ($inputData['action'] === 'otv') {
            

        } elseif ($inputData['action'] === 'suggestion') {
            

            

        } elseif ($inputData['action'] === 'tokens') {
            

        } elseif ($inputData['action'] === 'items_change') {
            

        } elseif ($inputData['action'] === 'templates') {
            

        } elseif ($inputData['action'] === 'cache_tree') {
            
        } else if ($inputData['action'] === 'background_subtasks') {
            

        } else if ($inputData['action'] === 'background_tasks') {
            
        } else if ($inputData['action'] === 'background_tasks_logs') {
            
        } else if ($inputData['action'] === 'ldap_groups_roles') {
            
        } else if ($inputData['action'] === 'items_otp') {
            
        } else if ($inputData['action'] === 'auth_failures') {
            DB::query(
                "CREATE TABLE IF NOT EXISTS `" . $this->inputData['tablePrefix'] . "auth_failures` (
                `id` int(12) NOT NULL AUTO_INCREMENT,
                `source` ENUM('login', 'remote_ip') NOT NULL,
                `value` VARCHAR(500) NOT NULL,
                `date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `unlock_at` TIMESTAMP NULL DEFAULT NULL,
                `unlock_code` VARCHAR(50) NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
                ) CHARSET=utf8;"
            );
        }
        // CARREFULL - WHEN ADDING NEW TABLE
        // Add the command inside install.js file
        // in task array at step 5


        return [
            'success' => true,
            'message' => 'Database has been successfully installed',
        ];

    } catch (Exception $e) {
        // Si la connexion échoue, afficher un message d'erreur
        return [
            'success' => false,
            'message' => 'Query error occurred: ' . $e->getMessage(),
        ];
    }
}
*/