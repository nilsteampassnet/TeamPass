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
 * @file      emails_templates.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

declare(strict_types=1);

/**
 * Catalog of the emails an administrator is allowed to customize.
 *
 * This file is the single source of truth for the email customization feature:
 *  - `Language::get()` uses it to know which language keys may be overridden by
 *    the `emails_templates` table (any key absent from this catalog is never
 *    looked up in the database);
 *  - the administration page uses it to build the list, the token chips and the
 *    save-time validation.
 *
 * It is deliberately hand-written and not derived from a `email_*` prefix scan:
 * several UI labels share that prefix (`email_debug_level`, `email_port`, ...),
 * several real templates do not use it (`new_item_email_body`,
 * `bruteforce_reset_mail_body`, ...), and neither the subject/body pairing nor
 * the token list can be inferred from the key names.
 *
 * Entry format, keyed by a stable template identifier:
 *
 *   'group'           string  UI grouping: users|auth|security|items|maintenance
 *   'subject_key'     ?string language key of the subject, null for a fragment
 *   'subject_prefix'  string  literal text the caller prepends to the subject
 *                             (hard-coded outside the language file, shown as
 *                             read-only context in the UI)
 *   'body_key'        string  language key of the body
 *   'tokens'          array   markers the call site actually substitutes
 *   'required_tokens' array   removing one of these breaks the email: save is refused
 *   'fragment'        bool    true when the body is inlined into another body
 *   'label'           string  language key of the UI title
 *   'description'     string  language key of the UI help text
 *   'trigger'         string  where the email is emitted (documentation only)
 *
 * Notes that apply to the whole catalog:
 *  - Several templates share a subject key (`login_credentials`,
 *    `email_subject_item_updated`, ...). Storage is per language key, so editing
 *    such a subject changes every email using it. The UI must warn about it.
 *  - `#password#` is NOT substituted when the email is queued: the background
 *    worker decrypts and replaces it at send time
 *    (`app/scripts/traits/EmailTrait.php`). A preview must therefore show a
 *    placeholder for it.
 *  - Keys present in `english.php` but referenced nowhere are intentionally
 *    absent from this catalog: `email_body_temporary_encryption_code`,
 *    `email_bodyalt_item_updated`, `email_subject`, `email_body3`.
 *
 * @return array<string, array<string, mixed>>
 */
return [

    // ---------------------------------------------------------------- users

    'new_user_credentials' => [
        'group' => 'users',
        'subject_key' => 'temporary_encryption_code',
        'subject_prefix' => 'TEAMPASS - ',
        'body_key' => 'email_body_new_user',
        'tokens' => ['#login#', '#password#'],
        'required_tokens' => ['#password#'],
        'label' => 'email_tpl_new_user_credentials',
        'description' => 'email_tpl_new_user_credentials_desc',
        // The body is rendered by the page, sent to the browser and posted back.
        'trigger' => 'app/pages/users.js.php:462 -> main.queries.php (mail_me)',
    ],

    'user_keys_ready_credentials' => [
        'group' => 'users',
        'subject_key' => 'login_credentials',
        'subject_prefix' => 'TEAMPASS - ',
        'body_key' => 'email_body_user_config_1',
        'tokens' => ['#lastname#', '#firstname#', '#login#', '#password#'],
        'required_tokens' => ['#password#'],
        'label' => 'email_tpl_user_keys_ready_credentials',
        'description' => 'email_tpl_user_keys_ready_credentials_desc',
        'trigger' => 'app/scripts/traits/UserHandlerTrait.php:710 (default body)',
    ],

    'user_account_ready' => [
        'group' => 'users',
        'subject_key' => 'email_subject_account_ready',
        'subject_prefix' => 'TEAMPASS - ',
        'body_key' => 'email_body_user_config_2',
        'tokens' => ['#lastname#', '#firstname#', '#login#'],
        'required_tokens' => [],
        'label' => 'email_tpl_user_account_ready',
        'description' => 'email_tpl_user_account_ready_desc',
        // LDAP/OAuth2 account creation and admin reset of an encryption code.
        'trigger' => 'app/sources/identify.php:2091, identify.php:3692, app/core/load.js.php:1389',
    ],

    'user_new_password' => [
        'group' => 'users',
        'subject_key' => 'login_credentials',
        'subject_prefix' => 'TEAMPASS - ',
        'body_key' => 'email_body_user_config_3',
        'tokens' => ['#lastname#', '#firstname#', '#login#', '#password#'],
        'required_tokens' => ['#password#'],
        'label' => 'email_tpl_user_new_password',
        'description' => 'email_tpl_user_new_password_desc',
        'trigger' => 'app/core/load.js.php:1376 (administrator changes a user password)',
    ],

    'user_keys_ready' => [
        'group' => 'users',
        'subject_key' => 'login_credentials',
        'subject_prefix' => 'TEAMPASS - ',
        'body_key' => 'email_body_user_config_4',
        'tokens' => ['#lastname#', '#firstname#', '#login#'],
        'required_tokens' => [],
        'label' => 'email_tpl_user_keys_ready',
        'description' => 'email_tpl_user_keys_ready_desc',
        'trigger' => 'app/core/load.js.php:936, users.queries.php:4049, PasswordManager.php:189',
    ],

    'user_created_credentials' => [
        'group' => 'users',
        'subject_key' => 'login_credentials',
        'subject_prefix' => 'TEAMPASS - ',
        'body_key' => 'email_body_user_config_6',
        'tokens' => ['#lastname#', '#firstname#', '#login#', '#password#'],
        'required_tokens' => ['#password#'],
        'label' => 'email_tpl_user_created_credentials',
        'description' => 'email_tpl_user_created_credentials_desc',
        'trigger' => 'app/sources/users.queries.php:853 (administrator creates a user)',
    ],

    'user_created_from_ldap' => [
        'group' => 'users',
        'subject_key' => 'email_subject_new_user',
        'subject_prefix' => '',
        'body_key' => 'email_body_user_added_from_ldap_encryption_code',
        'tokens' => ['#tp_login#', '#enc_code#', '#tp_link#'],
        'required_tokens' => ['#enc_code#'],
        'label' => 'email_tpl_user_created_from_ldap',
        'description' => 'email_tpl_user_created_from_ldap_desc',
        'trigger' => 'app/sources/users.queries.php:3414',
    ],

    'user_temporary_password' => [
        'group' => 'users',
        'subject_key' => 'your_new_password',
        'subject_prefix' => '[Teampass] ',
        'body_key' => 'email_body_temporary_login_password',
        'tokens' => ['#enc_code#'],
        'required_tokens' => ['#enc_code#'],
        'label' => 'email_tpl_user_temporary_password',
        'description' => 'email_tpl_user_temporary_password_desc',
        // The body is rendered by the page, sent to the browser and posted back.
        'trigger' => 'app/core/load.js.php:1493 -> main.queries.php (mail_me)',
    ],

    'inactive_user_notice' => [
        'group' => 'users',
        'subject_key' => 'inactive_users_mgmt_email_subject',
        'subject_prefix' => '',
        'body_key' => 'inactive_users_mgmt_email_body',
        'tokens' => [
            '#login#', '#firstname#', '#lastname#',
            '#inactivity_days#', '#grace_days#', '#action#', '#url#',
        ],
        'required_tokens' => [],
        'label' => 'email_tpl_inactive_user_notice',
        'description' => 'email_tpl_inactive_user_notice_desc',
        'trigger' => 'app/scripts/background_tasks___worker.php:805',
    ],

    // ----------------------------------------------------------------- auth

    'mfa_code' => [
        'group' => 'auth',
        'subject_key' => 'email_ga_subject',
        'subject_prefix' => '',
        'body_key' => 'email_ga_text',
        'tokens' => ['#2FACode#'],
        'required_tokens' => ['#2FACode#'],
        'label' => 'email_tpl_mfa_code',
        'description' => 'email_tpl_mfa_code_desc',
        'trigger' => 'app/sources/identify.php:381, app/sources/main.queries.php:1575',
    ],

    'forgot_password_link' => [
        'group' => 'auth',
        'subject_key' => 'forgot_local_password_confirm_email_subject',
        'subject_prefix' => '',
        'body_key' => 'forgot_local_password_confirm_email_body',
        'tokens' => ['#name#', '#lastname#', '#login#', '#reset_url#'],
        'required_tokens' => ['#reset_url#'],
        'label' => 'email_tpl_forgot_password_link',
        'description' => 'email_tpl_forgot_password_link_desc',
        'trigger' => 'app/sources/identify.php:3950',
    ],

    'forgot_password_temporary' => [
        'group' => 'auth',
        'subject_key' => 'forgot_local_password_email_subject',
        'subject_prefix' => '',
        'body_key' => 'forgot_local_password_email_body',
        'tokens' => ['#name#', '#lastname#', '#login#', '#password#', '#tp_link#'],
        'required_tokens' => ['#password#'],
        'label' => 'email_tpl_forgot_password_temporary',
        'description' => 'email_tpl_forgot_password_temporary_desc',
        'trigger' => 'app/sources/reset-password.php:210',
    ],

    // ------------------------------------------------------------- security

    'user_login_notice' => [
        'group' => 'security',
        'subject_key' => 'email_subject_on_user_login',
        'subject_prefix' => '',
        'body_key' => 'email_body_on_user_login',
        'tokens' => ['#tp_user#', '#tp_date#', '#tp_time#'],
        'required_tokens' => [],
        'label' => 'email_tpl_user_login_notice',
        'description' => 'email_tpl_user_login_notice_desc',
        'trigger' => 'app/sources/identify.php:1272',
    ],

    'user_lock_notice' => [
        'group' => 'security',
        'subject_key' => 'email_subject_on_user_lock',
        'subject_prefix' => '',
        'body_key' => 'email_body_on_user_lock',
        'tokens' => [
            '#tp_user#', '#tp_name#', '#tp_email#', '#tp_ip#',
            '#tp_date#', '#tp_time#', '#tp_unlock_at#',
        ],
        'required_tokens' => [],
        'label' => 'email_tpl_user_lock_notice',
        'description' => 'email_tpl_user_lock_notice_desc',
        'trigger' => 'app/sources/main.functions.php:3522',
    ],

    'bruteforce_unlock' => [
        'group' => 'security',
        'subject_key' => 'bruteforce_reset_mail_subject',
        'subject_prefix' => '',
        'body_key' => 'bruteforce_reset_mail_body',
        'tokens' => ['#name#', '#reset_url#', '#unlock_at#'],
        'required_tokens' => ['#reset_url#'],
        'label' => 'email_tpl_bruteforce_unlock',
        'description' => 'email_tpl_bruteforce_unlock_desc',
        'trigger' => 'app/sources/main.functions.php:3336',
    ],

    'security_nudges_digest' => [
        'group' => 'security',
        'subject_key' => 'security_nudges_email_subject',
        'subject_prefix' => '',
        'body_key' => 'security_nudges_email_body',
        'tokens' => ['#breached#', '#weak#', '#reused#', '#overdue#', '#total#', '#url#'],
        'required_tokens' => [],
        'label' => 'email_tpl_security_nudges_digest',
        'description' => 'email_tpl_security_nudges_digest_desc',
        'trigger' => 'app/scripts/traits/SecurityNudgeTrait.php:126',
    ],

    // ---------------------------------------------------------------- items

    'item_created' => [
        'group' => 'items',
        'subject_key' => 'email_subject_item_updated',
        'subject_prefix' => '',
        'body_key' => 'new_item_email_body',
        // Historically the shipped templates used '#label' and '#link' without
        // their closing '#', and the call site substituted those malformed
        // forms. It now substitutes both, so only the canonical markers are
        // advertised here while untranslated language files keep working.
        'tokens' => ['#label#', '#link#'],
        'required_tokens' => [],
        'label' => 'email_tpl_item_created',
        'description' => 'email_tpl_item_created_desc',
        'trigger' => 'app/sources/items.queries.php:816',
    ],

    'item_updated' => [
        'group' => 'items',
        'subject_key' => 'email_subject_item_updated',
        'subject_prefix' => '',
        'body_key' => 'email_body_item_updated',
        // '#changes#' is only substituted by the main.functions.php emitter;
        // items.queries.php leaves it untouched, so it is not a safe default.
        'tokens' => [
            '#item_label#', '#item_category#', '#item_id#', '#url#',
            '#name#', '#lastname#', '#folder_name#', '#changes#',
        ],
        'required_tokens' => [],
        'label' => 'email_tpl_item_updated',
        'description' => 'email_tpl_item_updated_desc',
        'trigger' => 'app/sources/items.queries.php:2324, app/sources/main.functions.php:3060',
    ],

    'item_opened_notice' => [
        'group' => 'items',
        'subject_key' => 'email_on_open_notification_subject',
        'subject_prefix' => '',
        'body_key' => 'email_on_open_notification_mail',
        'tokens' => ['#tp_user#', '#tp_item#', '#tp_link#'],
        'required_tokens' => [],
        'label' => 'email_tpl_item_opened_notice',
        'description' => 'email_tpl_item_opened_notice_desc',
        'trigger' => 'app/sources/items.queries.php:4005',
    ],

    'item_access_request' => [
        'group' => 'items',
        'subject_key' => 'email_request_access_subject',
        'subject_prefix' => '',
        'body_key' => 'email_request_access_mail',
        // The shipped English body also contains '#tp_reason#', which the call
        // site never substitutes. It is not advertised here on purpose.
        'tokens' => ['#tp_item_author#', '#tp_user#', '#tp_item#'],
        'required_tokens' => [],
        'label' => 'email_tpl_item_access_request',
        'description' => 'email_tpl_item_access_request_desc',
        'trigger' => 'app/sources/items.queries.php:6541',
    ],

    'item_shared' => [
        'group' => 'items',
        'subject_key' => 'email_share_item_subject',
        'subject_prefix' => '',
        'body_key' => 'email_share_item_mail',
        'tokens' => ['#tp_link#', '#tp_user#', '#tp_item#'],
        'required_tokens' => ['#tp_link#'],
        'label' => 'email_tpl_item_shared',
        'description' => 'email_tpl_item_shared_desc',
        'trigger' => 'app/sources/items.queries.php:6577',
    ],

    // ---------------------------------------------------------- maintenance

    'scheduled_backup_report' => [
        'group' => 'maintenance',
        'subject_key' => 'email_subject_scheduled_backup_report',
        'subject_prefix' => '',
        'body_key' => 'email_body_scheduled_backup_report',
        'tokens' => [
            '#tp_status#', '#tp_datetime#', '#tp_message#', '#tp_file#',
            '#tp_size#', '#tp_output_dir#', '#tp_retention_days#',
            '#tp_purge_deleted#', '#tp_externalized_report#',
        ],
        // '#tp_status#' is the only token also substituted in the subject.
        'subject_tokens' => ['#tp_status#'],
        'required_tokens' => [],
        'label' => 'email_tpl_scheduled_backup_report',
        'description' => 'email_tpl_scheduled_backup_report_desc',
        'trigger' => 'app/scripts/background_tasks___worker.php:1159',
    ],

    'scheduled_backup_externalized_block' => [
        'group' => 'maintenance',
        'subject_key' => null,
        'subject_prefix' => '',
        'body_key' => 'email_body_scheduled_backup_externalized_report',
        'tokens' => [
            '#tp_externalized_status#', '#tp_externalized_message#',
            '#tp_externalized_destination#', '#tp_externalized_target#',
            '#tp_externalized_file#', '#tp_externalized_size#',
            '#tp_externalized_retention_days#', '#tp_externalized_retention_count#',
            '#tp_externalized_purge_deleted#', '#tp_externalized_retry#',
        ],
        'required_tokens' => [],
        // Inlined into 'scheduled_backup_report' through '#tp_externalized_report#'.
        'fragment' => true,
        'label' => 'email_tpl_scheduled_backup_externalized_block',
        'description' => 'email_tpl_scheduled_backup_externalized_block_desc',
        'trigger' => 'app/scripts/background_tasks___worker.php:1063',
    ],
];
