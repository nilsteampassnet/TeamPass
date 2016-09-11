<?php
$LANG = array (
		0 => array (
				'term' => 'user_ga_code',
				'definition' => 'Send GoogleAuthenticator to user by email',
				'context' => '' 
		),
		1 => array (
				'term' => 'send_ga_code',
				'definition' => 'Google Authenticator for user',
				'context' => '' 
		),
		2 => array (
				'term' => 'error_no_email',
				'definition' => 'This user has no email set!',
				'context' => '' 
		),
		3 => array (
				'term' => 'error_no_user',
				'definition' => 'No user found!',
				'context' => '' 
		),
		4 => array (
				'term' => 'email_ga_subject',
				'definition' => 'Your Google Authenticator flash code for Teampass',
				'context' => '' 
		),
		5 => array (
				'term' => 'email_ga_text',
				'definition' => 'Hello,<br><br>Please click this <a href=\'#link#\'>LINK</a> and flash it with GoogleAuthenticator application to get your OTP credentials for Teampass.<br /><br />Cheers',
				'context' => '' 
		),
		6 => array (
				'term' => 'settings_attachments_encryption',
				'definition' => 'Enable encryption of Items attachments',
				'context' => '' 
		),
		7 => array (
				'term' => 'settings_attachments_encryption_tip',
				'definition' => 'THIS OPTION COULD BREAK EXISTING ATTACHMENTS, please read carefully the next. If enabled, Items attachments are stored encrypted on the server. The ecryption uses the SALT defined for Teampass. This requieres more server ressources. WARNING: once you change strategy, it is mandatory to run the script to adapt existing attachments. See tab \'Specific Actions\'.',
				'context' => '' 
		),
		8 => array (
				'term' => 'admin_action_attachments_cryption',
				'definition' => 'Encrypt or Decrypt the Items attachments',
				'context' => '' 
		),
		9 => array (
				'term' => 'admin_action_attachments_cryption_tip',
				'definition' => 'WARNING: this action has ONLY to be performed after changing the associated option in Teampass settings. Please make a copy of the folder \'upload\' before doing any action, just in case ...',
				'context' => '' 
		),
		10 => array (
				'term' => 'encrypt',
				'definition' => 'Encrypt',
				'context' => '' 
		),
		11 => array (
				'term' => 'decrypt',
				'definition' => 'Decrypt',
				'context' => '' 
		),
		12 => array (
				'term' => 'admin_ga_website_name',
				'definition' => 'Name displayed Google Authenticator for Teampass',
				'context' => '' 
		),
		13 => array (
				'term' => 'admin_ga_website_name_tip',
				'definition' => 'This name is used for the identification code account in Google Authenticator.',
				'context' => '' 
		),
		14 => array (
				'term' => 'admin_action_pw_prefix_correct',
				'definition' => 'Correct passwords prefix',
				'context' => '' 
		),
		15 => array (
				'term' => 'admin_action_pw_prefix_correct_tip',
				'definition' => 'Before lauching this script, PLEASE be sure to make a dump of the database. This script will perform an update of passwords prefix. It SHALL only be used if you noticed that passwords are displayed with strange prefix.',
				'context' => '' 
		),
		16 => array (
				'term' => 'items_changed',
				'definition' => 'have been changed.',
				'context' => '' 
		),
		17 => array (
				'term' => 'ga_not_yet_synchronized',
				'definition' => 'Get identified with Google Authenticator',
				'context' => '' 
		),
		18 => array (
				'term' => 'ga_scan_url',
				'definition' => 'Please scan this flashcode with your mobile Google Authenticator application. Copy from it the identification code.',
				'context' => '' 
		),
		19 => array (
				'term' => 'ga_identification_code',
				'definition' => 'Identification code',
				'context' => '' 
		),
		20 => array (
				'term' => 'ga_enter_credentials',
				'definition' => 'You need to enter your login credentials',
				'context' => '' 
		),
		21 => array (
				'term' => 'ga_bad_code',
				'definition' => 'The Google Authenticator code is wrong',
				'context' => '' 
		),
		22 => array (
				'term' => 'settings_get_tp_info',
				'definition' => 'Automatically load information about Teampass',
				'context' => '' 
		),
		23 => array (
				'term' => 'settings_get_tp_info_tip',
				'definition' => 'This option permits the administration page to load information such as version and libraries usage from Teampass server.',
				'context' => '' 
		),
		24 => array (
				'term' => 'at_field',
				'definition' => 'Field',
				'context' => '' 
		),
		25 => array (
				'term' => 'category_in_folders_title',
				'definition' => 'Associated folders',
				'context' => '' 
		),
		26 => array (
				'term' => 'category_in_folders',
				'definition' => 'Edit Folders for this Category',
				'context' => '' 
		),
		27 => array (
				'term' => 'select_folders_for_category',
				'definition' => 'Select the Folders to associate to this Category of Fields',
				'context' => '' 
		),
		28 => array (
				'term' => 'offline_mode_warning',
				'definition' => 'Off-line mode permits you to export into an HTML file your Items, so that you can access them when not connected to Teampass server. The passwords are encrypted by a Key you are given.',
				'context' => '' 
		),
		29 => array (
				'term' => 'offline_menu_title',
				'definition' => 'Off-Line mode',
				'context' => '' 
		),
		30 => array (
				'term' => 'settings_offline_mode',
				'definition' => 'Activate Off-line mode',
				'context' => '' 
		),
		31 => array (
				'term' => 'settings_offline_mode_tip',
				'definition' => 'Off-line mode consists in exporting the Items in an HTML file. The Items in this page are encrypted with a key given by User.',
				'context' => '' 
		),
		32 => array (
				'term' => 'offline_mode_key_level',
				'definition' => 'Off-line encryption key minimum level',
				'context' => '' 
		),
		33 => array (
				'term' => 'categories',
				'definition' => 'Categories',
				'context' => '' 
		),
		34 => array (
				'term' => 'new_category_label',
				'definition' => 'Create a new Category - Enter label',
				'context' => '' 
		),
		35 => array (
				'term' => 'no_category_defined',
				'definition' => 'No category yet defined',
				'context' => '' 
		),
		36 => array (
				'term' => 'confirm_deletion',
				'definition' => 'You are going to delete... are you sure?',
				'context' => '' 
		),
		37 => array (
				'term' => 'confirm_rename',
				'definition' => 'Confirm renaming?',
				'context' => '' 
		),
		38 => array (
				'term' => 'new_field_title',
				'definition' => 'Enter the title of the new Field',
				'context' => '' 
		),
		39 => array (
				'term' => 'confirm_creation',
				'definition' => 'Confirm creation?',
				'context' => '' 
		),
		40 => array (
				'term' => 'confirm_moveto',
				'definition' => 'Confirm moving field?',
				'context' => '' 
		),
		41 => array (
				'term' => 'for_selected_items',
				'definition' => 'For selected Item',
				'context' => '' 
		),
		42 => array (
				'term' => 'move',
				'definition' => 'Move to',
				'context' => '' 
		),
		43 => array (
				'term' => 'field_add_in_category',
				'definition' => 'Add a new field in this category',
				'context' => '' 
		),
		44 => array (
				'term' => 'rename',
				'definition' => 'Rename',
				'context' => '' 
		),
		45 => array (
				'term' => 'settings_item_extra_fields',
				'definition' => 'Authorize Items to be completed with more Fields (by Categories)',
				'context' => '' 
		),
		46 => array (
				'term' => 'settings_item_extra_fields_tip',
				'definition' => 'This feature permits to enhance the Item definition with extra fields the administrator can define and organize by Categories. All data is encrypted. Notice that this feature consumes more SQL queries (around 5 more per Field during an Item update) and may require more time for actions to be performed. This is server dependant.',
				'context' => '' 
		),
		47 => array (
				'term' => 'html',
				'definition' => 'html',
				'context' => '' 
		),
		48 => array (
				'term' => 'more',
				'definition' => 'More',
				'context' => '' 
		),
		49 => array (
				'term' => 'save_categories_position',
				'definition' => 'Save Categories order',
				'context' => '' 
		),
		50 => array (
				'term' => 'reload_table',
				'definition' => 'Reload table',
				'context' => '' 
		),
		51 => array (
				'term' => 'settings_ldap_type',
				'definition' => 'LDAP server type',
				'context' => '' 
		),
		52 => array (
				'term' => 'use_md5_password_as_salt',
				'definition' => 'Use the login password as SALTkey',
				'context' => '' 
		),
		53 => array (
				'term' => 'server_time',
				'definition' => 'Server time',
				'context' => '' 
		),
		54 => array (
				'term' => 'settings_tree_counters',
				'definition' => 'Show more counters in folders tree',
				'context' => '' 
		),
		55 => array (
				'term' => 'settings_tree_counters_tip',
				'definition' => 'This will display for each folder 3 counters: number of items in folder; number of items in all subfolders; number of subfolders. This feature needs more SQL queries and may require more time to display the Tree.',
				'context' => '' 
		),
		56 => array (
				'term' => 'settings_encryptClientServer',
				'definition' => 'Client-Server exchanges are encrypted',
				'context' => '' 
		),
		57 => array (
				'term' => 'settings_encryptClientServer_tip',
				'definition' => 'AES-256 encryption is by-default enabled. This should be the case if no SSL certificat is used to securize data exchanges between client and server. If you are using an SSL protocol or if you are using Teampass in an Intranet, then you could deactivate this feature in order to speed up the data display in Teampass. /!\\ Remember that the safer and more securized solution is to use an SSL connection between Client and Server.',
				'context' => '' 
		),
		58 => array (
				'term' => 'error_group_noparent',
				'definition' => 'No parent has been selected!',
				'context' => '' 
		),
		59 => array (
				'term' => 'channel_encryption_no_iconv',
				'definition' => 'Extension ICONV is not loaded! Encryption can\'t be initiated!',
				'context' => '' 
		),
		60 => array (
				'term' => 'channel_encryption_no_bcmath',
				'definition' => 'Extension BCMATH is not loaded! Encryption can\'t be initiated!',
				'context' => '' 
		),
		61 => array (
				'term' => 'admin_action_check_pf',
				'definition' => 'Actualize Personal Folders for all users (creates them if not existing)',
				'context' => '' 
		),
		62 => array (
				'term' => 'admin_actions_title',
				'definition' => 'Specific Actions',
				'context' => '' 
		),
		63 => array (
				'term' => 'enable_personal_folder_feature_tip',
				'definition' => 'Once activated, you need to manually run a script that will create the personal folders for the existing users. Notice that this will only create personal folders for Users that do not have such a folder. The script \'".$txt[\'admin_action_check_pf\']."\' is available in tab \'".$txt[\'admin_actions_title\']."\'',
				'context' => '' 
		),
		64 => array (
				'term' => 'is_administrated_by_role',
				'definition' => 'User is administrated by',
				'context' => '' 
		),
		65 => array (
				'term' => 'administrators_only',
				'definition' => 'Administrators only',
				'context' => '' 
		),
		66 => array (
				'term' => 'managers_of',
				'definition' => 'Managers of role',
				'context' => '' 
		),
		67 => array (
				'term' => 'managed_by',
				'definition' => 'Managed by',
				'context' => '' 
		),
		68 => array (
				'term' => 'admin_small',
				'definition' => 'Admin',
				'context' => '' 
		),
		69 => array (
				'term' => 'setting_can_create_root_folder',
				'definition' => 'Authorize new folder to be created at Root level',
				'context' => '' 
		),
		70 => array (
				'term' => 'settings_enable_sts',
				'definition' => 'Enforce HTTPS Strict Transport Security -- Warning: Read ToolTip.',
				'context' => '' 
		),
		71 => array (
				'term' => 'settings_enable_sts_tip',
				'definition' => 'This will enforce HTTPS STS. STS helps stop SSL Man-in-the-Middle attacks. You MUST have a valid SSL certificate in order to use this option. If you have a self-signed certificate and enable this option it will break teampass!! You must have \'SSLOptions +ExportCertData\' in the Apache SSL configuration.',
				'context' => '' 
		),
		72 => array (
				'term' => 'channel_encryption_no_gmp',
				'definition' => 'Extension GMP is not loaded! Encryption can\'t be initiated!',
				'context' => '' 
		),
		73 => array (
				'term' => 'channel_encryption_no_openssl',
				'definition' => 'Extension OPENSSL is not loaded! Encryption can\'t be initiated!',
				'context' => '' 
		),
		74 => array (
				'term' => 'channel_encryption_no_file',
				'definition' => 'No encryption keys file was found!<br>Please launch upgrade process.',
				'context' => '' 
		),
		75 => array (
				'term' => 'admin_action_generate_encrypt_keys',
				'definition' => 'Generate new encryption keys set',
				'context' => '' 
		),
		76 => array (
				'term' => 'admin_action_generate_encrypt_keys_tip',
				'definition' => 'Encryption keys set is a very important aspect in the security of your TeamPass installation. Indeed those keys are used in order to encrypt the channel between Server and Client. Even if this file is securized outside the WWW zone of your server, it is recommanded to regenerate the keys time to time. Notice that this operation can take up to 1 minute.',
				'context' => '' 
		),
		77 => array (
				'term' => 'settings_anyone_can_modify_bydefault',
				'definition' => 'Activate \'<b><i>Anyone can modify</b></i>\' option by default',
				'context' => '' 
		),
		78 => array (
				'term' => 'channel_encryption_in_progress',
				'definition' => 'Encrypting channel ...',
				'context' => '' 
		),
		79 => array (
				'term' => 'channel_encryption_failed',
				'definition' => 'Authentication failed!',
				'context' => '' 
		),
		80 => array (
				'term' => 'purge_log',
				'definition' => 'Purge logs from',
				'context' => '' 
		),
		81 => array (
				'term' => 'to',
				'definition' => 'to',
				'context' => '' 
		),
		82 => array (
				'term' => 'purge_now',
				'definition' => 'Purge Now!',
				'context' => '' 
		),
		83 => array (
				'term' => 'purge_done',
				'definition' => 'Purge done! Number of elements deleted: ',
				'context' => '' 
		),
		84 => array (
				'term' => 'settings_upload_maxfilesize_tip',
				'definition' => 'Maximum file size you allow. It should be coherant with your server settings.',
				'context' => '' 
		),
		85 => array (
				'term' => 'settings_upload_docext_tip',
				'definition' => 'Document types. Indicate the file extensions allowed separated with a coma (,)',
				'context' => '' 
		),
		86 => array (
				'term' => 'settings_upload_imagesext_tip',
				'definition' => 'Image types. Indicate the file extensions allowed separated with a coma (,)',
				'context' => '' 
		),
		87 => array (
				'term' => 'settings_upload_pkgext_tip',
				'definition' => 'Package types. Indicate the file extensions allowed separated with a coma (,)',
				'context' => '' 
		),
		88 => array (
				'term' => 'settings_upload_otherext_tip',
				'definition' => 'Other types. Indicate the file extensions allowed separated with a coma (,)',
				'context' => '' 
		),
		89 => array (
				'term' => 'settings_upload_imageresize_options_tip',
				'definition' => 'When activated, this option resizes the Images to the format indicated just below.',
				'context' => '' 
		),
		90 => array (
				'term' => 'settings_upload_maxfilesize',
				'definition' => 'Max file size (in Mb)',
				'context' => '' 
		),
		91 => array (
				'term' => 'settings_upload_docext',
				'definition' => 'Allowed document extensions',
				'context' => '' 
		),
		92 => array (
				'term' => 'settings_upload_imagesext',
				'definition' => 'Allowed image extensions',
				'context' => '' 
		),
		93 => array (
				'term' => 'settings_upload_pkgext',
				'definition' => 'Allowed package extensions',
				'context' => '' 
		),
		94 => array (
				'term' => 'settings_upload_otherext',
				'definition' => 'Allowed other extensions',
				'context' => '' 
		),
		95 => array (
				'term' => 'settings_upload_imageresize_options',
				'definition' => 'Should Images being resized',
				'context' => '' 
		),
		96 => array (
				'term' => 'settings_upload_imageresize_options_w',
				'definition' => 'Resized image Width (in pixels)',
				'context' => '' 
		),
		97 => array (
				'term' => 'settings_upload_imageresize_options_h',
				'definition' => 'Resized image Height (in pixels)',
				'context' => '' 
		),
		98 => array (
				'term' => 'settings_upload_imageresize_options_q',
				'definition' => 'Resized image Quality',
				'context' => '' 
		),
		99 => array (
				'term' => 'admin_upload_title',
				'definition' => 'Uploads',
				'context' => '' 
		),
		100 => array (
				'term' => 'settings_importing',
				'definition' => 'Enable importing data from CVS/KeyPass files',
				'context' => '' 
		),
		101 => array (
				'term' => 'admin_proxy_ip',
				'definition' => 'Proxy IP used',
				'context' => '' 
		),
		102 => array (
				'term' => 'admin_proxy_ip_tip',
				'definition' => '<span style=\'font-size:11px;max-width:300px;\'>If your Internet connection goes through a proxy. Indicate here its IP.<br />Leave empty if no Proxy.</span>',
				'context' => '' 
		),
		103 => array (
				'term' => 'admin_proxy_port',
				'definition' => 'Proxy PORT used',
				'context' => '' 
		),
		104 => array (
				'term' => 'admin_proxy_port_tip',
				'definition' => '<span style=\'font-size:11px;max-width:300px;\'>If you have set an IP for the Proxy, now indicate its PORT. Could be 8080.<br />Leave empty if no Proxy.</span>',
				'context' => '' 
		),
		105 => array (
				'term' => 'settings_ldap_elusers',
				'definition' => ' Teampass local users only ',
				'context' => '' 
		),
		106 => array (
				'term' => 'settings_ldap_elusers_tip',
				'definition' => ' This feature allows users in the database to authenticate via LDAP. Disable this if you want to browse any LDAP directory. ',
				'context' => '' 
		),
		107 => array (
				'term' => 'error_role_complex_not_set',
				'definition' => 'The Role must have a minimum required passwords complexity level!',
				'context' => '' 
		),
		108 => array (
				'term' => 'item_updated_text',
				'definition' => 'This Item has been edited. You need to update it before you can change it.',
				'context' => '' 
		),
		109 => array (
				'term' => 'database_menu',
				'definition' => 'Database',
				'context' => '' 
		),
		110 => array (
				'term' => 'db_items_edited',
				'definition' => 'Items actually in edition',
				'context' => '' 
		),
		111 => array (
				'term' => 'item_edition_start_hour',
				'definition' => 'Edition started since',
				'context' => '' 
		),
		112 => array (
				'term' => 'settings_delay_for_item_edition',
				'definition' => 'After how long an Item edition is considered as failed (in minutes)',
				'context' => '' 
		),
		113 => array (
				'term' => 'settings_delay_for_item_edition_tip',
				'definition' => '<span style=\'font-size:11px;max-width:300px;\'>When editing an Item, the Item is locked so that no other parallel edition can be performed. A kind of token is reserved.<br />This setting permits to delete the token. If the value is set to 0 then the token will never be deleted (unless by Administrator)</span>',
				'context' => '' 
		),
		114 => array (
				'term' => 'db_users_logged',
				'definition' => 'Users actually logged',
				'context' => '' 
		),
		115 => array (
				'term' => 'action',
				'definition' => 'Action',
				'context' => '' 
		),
		116 => array (
				'term' => 'login_time',
				'definition' => 'Logged since',
				'context' => '' 
		),
		117 => array (
				'term' => 'lastname',
				'definition' => 'Last name',
				'context' => '' 
		),
		118 => array (
				'term' => 'user_login',
				'definition' => 'Login',
				'context' => '' 
		),
		119 => array (
				'term' => 'at_user_new_lastname',
				'definition' => 'User #user_login# lastname changed',
				'context' => '' 
		),
		120 => array (
				'term' => 'at_user_new_name',
				'definition' => 'User #user_login# name changed',
				'context' => '' 
		),
		121 => array (
				'term' => 'info_list_of_connected_users_approximation',
				'definition' => 'Note: This list may show more connected users than it is really the case.',
				'context' => '' 
		),
		122 => array (
				'term' => 'disconnect_all_users',
				'definition' => 'Disconnected all Users (except Administrators)',
				'context' => '' 
		),
		123 => array (
				'term' => 'role',
				'definition' => 'Role',
				'context' => '' 
		),
		124 => array (
				'term' => 'admin_2factors_authentication_setting',
				'definition' => 'Enable Google 2-Factors authentication',
				'context' => '' 
		),
		125 => array (
				'term' => 'admin_2factors_authentication_setting_tip',
				'definition' => '<span style=\'font-size:11px;max-width:300px;\'>Google 2-Factors autentication permits to add one more security level for user autentication. When user wants to login TeamPass, a QR code is generated. This QR code needs to be scanned by the user to get a one-time password.<br />WARNING: this extra needs an Internet connection and a scanner such as a smartphone.</span>',
				'context' => '' 
		),
		126 => array (
				'term' => '2factors_tile',
				'definition' => '2-Factors Autentication',
				'context' => '' 
		),
		127 => array (
				'term' => '2factors_image_text',
				'definition' => 'Please, scan the QR code',
				'context' => '' 
		),
		128 => array (
				'term' => '2factors_confirm_text',
				'definition' => 'Enter the one-time password',
				'context' => '' 
		),
		129 => array (
				'term' => 'bad_onetime_password',
				'definition' => 'Wrong One-Time password!',
				'context' => '' 
		),
		130 => array (
				'term' => 'error_string_not_utf8',
				'definition' => 'An error appears because string format is not UTF8!',
				'context' => '' 
		),
		131 => array (
				'term' => 'error_role_exist',
				'definition' => 'This Role already exists!',
				'context' => '' 
		),
		132 => array (
				'term' => 'error_no_edition_possible_locked',
				'definition' => 'Edition not possible. This Item is presently edited!',
				'context' => '' 
		),
		133 => array (
				'term' => 'error_mcrypt_not_loaded',
				'definition' => 'Extension \'mcrypt\' is actually not loaded in PHP module. This module is required for TeamPass to work. Please inform your administrator if you see this message.',
				'context' => '' 
		),
		134 => array (
				'term' => 'at_user_added',
				'definition' => 'User #user_login# added',
				'context' => '' 
		),
		135 => array (
				'term' => 'at_user_deleted',
				'definition' => 'User #user_login# deleted',
				'context' => '' 
		),
		136 => array (
				'term' => 'at_user_locked',
				'definition' => 'User #user_login# locked',
				'context' => '' 
		),
		137 => array (
				'term' => 'at_user_unlocked',
				'definition' => 'User #user_login# unlocked',
				'context' => '' 
		),
		138 => array (
				'term' => 'at_user_email_changed',
				'definition' => 'User #user_login# email changed',
				'context' => '' 
		),
		139 => array (
				'term' => 'at_user_pwd_changed',
				'definition' => 'User #user_login# password changed',
				'context' => '' 
		),
		140 => array (
				'term' => 'at_user_initial_pwd_changed',
				'definition' => 'User #user_login# initial password change',
				'context' => '' 
		),
		141 => array (
				'term' => 'user_mngt',
				'definition' => 'User Management',
				'context' => '' 
		),
		142 => array (
				'term' => 'select',
				'definition' => 'select',
				'context' => '' 
		),
		143 => array (
				'term' => 'user_activity',
				'definition' => 'User Activity',
				'context' => '' 
		),
		144 => array (
				'term' => 'items',
				'definition' => 'Items',
				'context' => '' 
		),
		145 => array (
				'term' => 'enable_personal_saltkey_cookie',
				'definition' => 'Enable personal SALTKey to be stored in a cookie',
				'context' => '' 
		),
		146 => array (
				'term' => 'personal_saltkey_cookie_duration',
				'definition' => 'Personal SALTKey cookie DAYS life time before expiration',
				'context' => '' 
		),
		147 => array (
				'term' => 'admin_emails',
				'definition' => 'Emails',
				'context' => '' 
		),
		148 => array (
				'term' => 'admin_emails_configuration',
				'definition' => 'Emails Configuration',
				'context' => '' 
		),
		149 => array (
				'term' => 'admin_emails_configuration_testing',
				'definition' => 'Configuration testing',
				'context' => '' 
		),
		150 => array (
				'term' => 'admin_email_smtp_server',
				'definition' => 'SMTP server',
				'context' => '' 
		),
		151 => array (
				'term' => 'admin_email_auth',
				'definition' => 'SMTP server needs authentification',
				'context' => '' 
		),
		152 => array (
				'term' => 'admin_email_auth_username',
				'definition' => 'Authentification Username',
				'context' => '' 
		),
		153 => array (
				'term' => 'admin_email_auth_pwd',
				'definition' => 'Authentification Password',
				'context' => '' 
		),
		154 => array (
				'term' => 'admin_email_port',
				'definition' => 'Server Port',
				'context' => '' 
		),
		155 => array (
				'term' => 'admin_email_from',
				'definition' => 'Sender Email (from Email)',
				'context' => '' 
		),
		156 => array (
				'term' => 'admin_email_from_name',
				'definition' => 'Sender Name (from Name)',
				'context' => '' 
		),
		157 => array (
				'term' => 'admin_email_test_configuration',
				'definition' => 'Test the Email configuration',
				'context' => '' 
		),
		158 => array (
				'term' => 'admin_email_test_configuration_tip',
				'definition' => 'This test should send an email to the address indicated. If you don\'t receive it, please check your credentials.',
				'context' => '' 
		),
		159 => array (
				'term' => 'admin_email_test_subject',
				'definition' => '[TeamPass] Test email',
				'context' => '' 
		),
		160 => array (
				'term' => 'admin_email_test_body',
				'definition' => 'Hi,<br><br>Email sent successfully.<br><br>Cheers.',
				'context' => '' 
		),
		161 => array (
				'term' => 'admin_email_result_ok',
				'definition' => 'Email sent to #email# ... check your inbox.',
				'context' => '' 
		),
		162 => array (
				'term' => 'admin_email_result_nok',
				'definition' => 'Email not sent ... check your configuration. See associated error: ',
				'context' => '' 
		),
		163 => array (
				'term' => 'email_subject_item_updated',
				'definition' => 'Password has been updated',
				'context' => '' 
		),
		164 => array (
				'term' => 'email_body_item_updated',
				'definition' => 'Hello,<br><br>Password for \'#item_label#\' has been updated.<br /><br />You can check it <a href=\'".@$_SESSION[\'settings\'][\'cpassman_url\']."/index.php?page=items&group=#item_category#&id=#item_id#\'>HERE</a><br /><br />Cheers',
				'context' => '' 
		),
		165 => array (
				'term' => 'email_bodyalt_item_updated',
				'definition' => 'Password for #item_label# has been updated.',
				'context' => '' 
		),
		166 => array (
				'term' => 'admin_email_send_backlog',
				'definition' => 'Send emails backlog (actually #nb_emails# emails)',
				'context' => '' 
		),
		167 => array (
				'term' => 'admin_email_send_backlog_tip',
				'definition' => 'This script permits to force the emails in the database to be sent.<br />This could take some time depending of the number of emails to send.',
				'context' => '' 
		),
		168 => array (
				'term' => 'please_wait',
				'definition' => 'Please wait!',
				'context' => '' 
		),
		169 => array (
				'term' => 'admin_url_to_files_folder',
				'definition' => 'URL to Files folder',
				'context' => '' 
		),
		170 => array (
				'term' => 'admin_path_to_files_folder',
				'definition' => 'Path to Files folder',
				'context' => '' 
		),
		171 => array (
				'term' => 'admin_path_to_files_folder_tip',
				'definition' => '<span style=\'font-size:11px;max-width:300px;\'>Files folder is used to store all generated files by TeamPass and also some uploaded files.<br />IMPORTANT: for security reason, this folder should not be in the WWW folder of your website. It should be set in a protected area with a specific redirection rule in your Server configuration.<br />IMPORTANT 2:It could be good to set a CRON task in order to clean up periodically this folder.</span>',
				'context' => '' 
		),
		172 => array (
				'term' => 'admin_path_to_upload_folder_tip',
				'definition' => '<span style=\'font-size:11px;max-width:300px;\'>Upload folder is used to store all uploaded files associated to Items.<br />IMPORTANT: for security reason, this folder should not be in the WWW folder of your website. It should be set in a protected area with a specific redirection rule in your Server configuration.<br />IMPORTANT 2:This folder should never be clean up! Those files are associated to the Items.</span>',
				'context' => '' 
		),
		173 => array (
				'term' => 'pdf_export',
				'definition' => 'PDF exports',
				'context' => '' 
		),
		174 => array (
				'term' => 'pdf_password',
				'definition' => 'PDF encryption key',
				'context' => '' 
		),
		175 => array (
				'term' => 'pdf_password_warning',
				'definition' => 'You must provide an encryption key!',
				'context' => '' 
		),
		176 => array (
				'term' => 'admin_pwd_maximum_length',
				'definition' => 'Maximum length for passwords',
				'context' => '' 
		),
		177 => array (
				'term' => 'admin_pwd_maximum_length_tip',
				'definition' => 'The default value for passwords length is set to 40. It is important to know that setting a high value length will have impact on performances. Indeed more long is this value, more time the server needs to encrypt and decrypt, and to display passwords.',
				'context' => '' 
		),
		178 => array (
				'term' => 'settings_insert_manual_entry_item_history',
				'definition' => 'Enable permitting manual insertions in Items History log',
				'context' => '' 
		),
		179 => array (
				'term' => 'settings_insert_manual_entry_item_history_tip',
				'definition' => 'For any reason you may need to add manually an entry in the history of the Item. By activating this feature, it is possible.',
				'context' => '' 
		),
		180 => array (
				'term' => 'add_history_entry',
				'definition' => 'Add entry in History log',
				'context' => '' 
		),
		181 => array (
				'term' => 'at_manual',
				'definition' => 'Manual action',
				'context' => '' 
		),
		182 => array (
				'term' => 'at_manual_add',
				'definition' => 'Added manually',
				'context' => '' 
		),
		183 => array (
				'term' => 'admin_path_to_upload_folder',
				'definition' => 'Path to Upload folder',
				'context' => '' 
		),
		184 => array (
				'term' => 'admin_url_to_upload_folder',
				'definition' => 'URL to Upload folder',
				'context' => '' 
		),
		185 => array (
				'term' => 'automatic_del_after_date_text',
				'definition' => 'or after date',
				'context' => '' 
		),
		186 => array (
				'term' => 'at_automatically_deleted',
				'definition' => 'Automatically deleted',
				'context' => '' 
		),
		187 => array (
				'term' => 'admin_setting_enable_delete_after_consultation',
				'definition' => 'Item consulted can be automatically deleted',
				'context' => '' 
		),
		188 => array (
				'term' => 'admin_setting_enable_delete_after_consultation_tip',
				'definition' => '<span style=\'font-size:11px;max-width:300px;\'>When enabled, the Item creator can decide that Item will be automatically deleted after being seen X times.</span>',
				'context' => '' 
		),
		189 => array (
				'term' => 'enable_delete_after_consultation',
				'definition' => 'Item will be automatically deleted after being seen',
				'context' => '' 
		),
		190 => array (
				'term' => 'times',
				'definition' => 'times.',
				'context' => '' 
		),
		191 => array (
				'term' => 'automatic_deletion_activated',
				'definition' => 'Automatic deletion engaged',
				'context' => '' 
		),
		192 => array (
				'term' => 'at_automatic_del',
				'definition' => 'automatic deletion',
				'context' => '' 
		),
		193 => array (
				'term' => 'error_times_before_deletion',
				'definition' => 'Number of consultation before deletion needs to be more than 0!',
				'context' => '' 
		),
		194 => array (
				'term' => 'enable_notify',
				'definition' => 'Enable notify',
				'context' => '' 
		),
		195 => array (
				'term' => 'disable_notify',
				'definition' => 'Disable notify',
				'context' => '' 
		),
		196 => array (
				'term' => 'notify_activated',
				'definition' => 'Notification enabled',
				'context' => '' 
		),
		197 => array (
				'term' => 'at_email',
				'definition' => 'email',
				'context' => '' 
		),
		198 => array (
				'term' => 'enable_email_notification_on_item_shown',
				'definition' => 'Send notification by email when Item is shown',
				'context' => '' 
		),
		199 => array (
				'term' => 'bad_email_format',
				'definition' => 'Email address has not the expected format!',
				'context' => '' 
		),
		200 => array (
				'term' => 'item_share_text',
				'definition' => 'In order to share by mail this Item, enter the email address and press SEND button.',
				'context' => '' 
		),
		201 => array (
				'term' => 'share',
				'definition' => 'Share this Item',
				'context' => '' 
		),
		202 => array (
				'term' => 'share_sent_ok',
				'definition' => 'Email has been sent',
				'context' => '' 
		),
		203 => array (
				'term' => 'email_share_item_subject',
				'definition' => '[TeamPass] An Item was shared with you',
				'context' => '' 
		),
		204 => array (
				'term' => 'email_share_item_mail',
				'definition' => 'Hello,<br><br><u>#tp_user#</u> has shared with you the item <b>#tp_item#</b><br>Click the <a href=\'#tp_link#\'>LINK</a> to access.<br><br>Best regards.',
				'context' => '' 
		),
		205 => array (
				'term' => 'see_item_title',
				'definition' => 'Item Details',
				'context' => '' 
		),
		206 => array (
				'term' => 'email_on_open_notification_subject',
				'definition' => '[TeamPass] Notification on Item open',
				'context' => '' 
		),
		207 => array (
				'term' => 'email_on_open_notification_mail',
				'definition' => 'Hello,<br><br>#tp_user# has opened and watched the Item \'#tp_item#\'.<br>Click the <a href=\'#tp_link#\'>LINK</a> to access.<br><br>Best regards.',
				'context' => '' 
		),
		208 => array (
				'term' => 'pdf',
				'definition' => 'PDF',
				'context' => '' 
		),
		209 => array (
				'term' => 'csv',
				'definition' => 'CSV',
				'context' => '' 
		),
		210 => array (
				'term' => 'user_admin_migrate_pw',
				'definition' => 'Migrate personal Items to a user account',
				'context' => '' 
		),
		211 => array (
				'term' => 'migrate_pf_select_to',
				'definition' => 'Migrate personal Items to user',
				'context' => '' 
		),
		212 => array (
				'term' => 'migrate_pf_user_salt',
				'definition' => 'Enter the SALT key for selected User',
				'context' => '' 
		),
		213 => array (
				'term' => 'migrate_pf_no_sk',
				'definition' => 'You have not entered your SALT Key',
				'context' => '' 
		),
		214 => array (
				'term' => 'migrate_pf_no_sk_user',
				'definition' => 'You must enter the User SALT Key',
				'context' => '' 
		),
		215 => array (
				'term' => 'migrate_pf_no_user_id',
				'definition' => 'You must select the User";',
				'context' => '' 
		),
		216 => array (
				'term' => 'email_subject_new_user',
				'definition' => '[TeamPass] Your account creation',
				'context' => '' 
		),
		217 => array (
				'term' => 'email_new_user_mail',
				'definition' => 'Hello,<br><br>An administrator has created your account for Teampass.<br>You can use the next credentials for being logged:<br>- Login: #tp_login#<br>- Password: #tp_pw#<br><br>Click the <a href=\'#tp_link#\'>LINK</a> to access.<br><br>Best regards.',
				'context' => '' 
		),
		218 => array (
				'term' => 'error_empty_data',
				'definition' => 'No data to proceed!',
				'context' => '' 
		),
		219 => array (
				'term' => 'error_not_allowed_to',
				'definition' => 'You are not allowed to do that!',
				'context' => '' 
		),
		220 => array (
				'term' => 'personal_saltkey_lost',
				'definition' => 'I\'ve lost it',
				'context' => '' 
		),
		221 => array (
				'term' => 'new_saltkey_warning_lost',
				'definition' => 'You have lost your saltkey? What a pitty, this one can\'t be recovered, so please be sure before continuing.<br>By reseting your saltkey, all your previous personal items will be deleted!',
				'context' => '' 
		),
		222 => array (
				'term' => 'previous_pw',
				'definition' => 'Previous passwords used:',
				'context' => '' 
		),
		223 => array (
				'term' => 'no_previous_pw',
				'definition' => 'No previous password',
				'context' => '' 
		),
		224 => array (
				'term' => 'request_access_ot_item',
				'definition' => 'Request an access to author',
				'context' => '' 
		),
		225 => array (
				'term' => 'email_request_access_subject',
				'definition' => '[TeamPass] Request an access to item',
				'context' => '' 
		),
		226 => array (
				'term' => 'email_request_access_mail',
				'definition' => 'Hello #tp_item_author#,<br><br>User #tp_user# has required an access to \'#tp_item#\'.<br><br>Be sure of the rights of this user before changing the restriction to the Item.<br><br>Regards.',
				'context' => '' 
		),
		227 => array (
				'term' => 'admin_action_change_salt_key',
				'definition' => 'Change the main SALT Key',
				'context' => '' 
		),
		228 => array (
				'term' => 'admin_action_change_salt_key_tip',
				'definition' => 'Before changing the SALT key, please be sure to do a full backup of the database, and to put the tool in maintenance in order to avoid any users being logged.',
				'context' => '' 
		),
		229 => array (
				'term' => 'block_admin_info',
				'definition' => 'Administrators Info',
				'context' => '' 
		),
		230 => array (
				'term' => 'admin_new1',
				'definition' => '<i><u>14FEB2012:</i></u><br>Administrator profile is no more allowed to see items. This profile is now only an Administrative account.<br />See <a href=\'http://www.teampass.net/how-to-handle-changes-on-administrator-profile\' target=\'_blank\'>TeamPass.net page</a> concerning the way to handle this change.',
				'context' => '' 
		),
		231 => array (
				'term' => 'nb_items_by_query',
				'definition' => 'Number of items to get at each query iterration',
				'context' => '' 
		),
		232 => array (
				'term' => 'nb_items_by_query_tip',
				'definition' => '<span style=\'font-size:11px;max-width:300px;\'>More items means more time to display the list.<br />Set to \'auto\' to let the tool to adapt this number depending on the size screen of the user.<br />Set to \'max\' to force to display the complet list in one time.<br />Set a number corresding to the number of items to get at each query iterration.</span>',
				'context' => '' 
		),
		233 => array (
				'term' => 'error_no_selected_folder',
				'definition' => 'You need to select a Folder',
				'context' => '' 
		),
		234 => array (
				'term' => 'open_url_link',
				'definition' => 'Open in new page',
				'context' => '' 
		),
		235 => array (
				'term' => 'error_pw_too_long',
				'definition' => 'Password is too long! maximum characters is 40.',
				'context' => '' 
		),
		236 => array (
				'term' => 'at_restriction',
				'definition' => 'Restriction',
				'context' => '' 
		),
		237 => array (
				'term' => 'pw_encryption_error',
				'definition' => 'Error encryption of the password!',
				'context' => '' 
		),
		238 => array (
				'term' => 'enable_send_email_on_user_login',
				'definition' => 'Send an email to Admins on User log in',
				'context' => '' 
		),
		239 => array (
				'term' => 'email_subject_on_user_login',
				'definition' => '[TeamPass] A user has get connected',
				'context' => '' 
		),
		240 => array (
				'term' => 'email_body_on_user_login',
				'definition' => 'Hello,<br><br>User #tp_user# has get connected to TeamPass the #tp_date# at #tp_time#.<br><br>Regards.',
				'context' => '' 
		),
		241 => array (
				'term' => 'account_is_locked',
				'definition' => 'This is account is locked',
				'context' => '' 
		),
		242 => array (
				'term' => 'activity',
				'definition' => 'Activity',
				'context' => '' 
		),
		243 => array (
				'term' => 'add_button',
				'definition' => 'Add',
				'context' => '' 
		),
		244 => array (
				'term' => 'add_new_group',
				'definition' => 'Add a new folder',
				'context' => '' 
		),
		245 => array (
				'term' => 'add_role_tip',
				'definition' => 'Add a new role.',
				'context' => '' 
		),
		246 => array (
				'term' => 'admin',
				'definition' => 'Administration',
				'context' => '' 
		),
		247 => array (
				'term' => 'admin_action',
				'definition' => 'Please validate your action',
				'context' => '' 
		),
		248 => array (
				'term' => 'admin_action_db_backup',
				'definition' => 'Create a backup of the database',
				'context' => '' 
		),
		249 => array (
				'term' => 'admin_action_db_backup_key_tip',
				'definition' => 'Please enter the encryption key. Save it somewhere, it will be asked when restoring. (leave empty to not encrypt)',
				'context' => '' 
		),
		250 => array (
				'term' => 'admin_action_db_backup_start_tip',
				'definition' => 'Start',
				'context' => '' 
		),
		251 => array (
				'term' => 'admin_action_db_backup_tip',
				'definition' => 'It is a good practice to create a backup that could be used to restore your database.',
				'context' => '' 
		),
		252 => array (
				'term' => 'admin_action_db_clean_items',
				'definition' => 'Remove orphan items from database',
				'context' => '' 
		),
		253 => array (
				'term' => 'admin_action_db_clean_items_result',
				'definition' => 'items have been deleted.',
				'context' => '' 
		),
		254 => array (
				'term' => 'admin_action_db_clean_items_tip',
				'definition' => 'This will only delete those items and associated logs that have not been deleted after the associated folder has been deleted. It is suggested to create a backup before.',
				'context' => '' 
		),
		255 => array (
				'term' => 'admin_action_db_optimize',
				'definition' => 'Optimize the database',
				'context' => '' 
		),
		256 => array (
				'term' => 'admin_action_db_restore',
				'definition' => 'Restore the database',
				'context' => '' 
		),
		257 => array (
				'term' => 'admin_action_db_restore_key',
				'definition' => 'Please enter the encryption key.',
				'context' => '' 
		),
		258 => array (
				'term' => 'admin_action_db_restore_tip',
				'definition' => 'It has to be done using an SQL backup file created by the backup functionality.',
				'context' => '' 
		),
		259 => array (
				'term' => 'admin_action_purge_old_files',
				'definition' => 'Purge old files',
				'context' => '' 
		),
		260 => array (
				'term' => 'admin_action_purge_old_files_result',
				'definition' => 'files have been deleted.',
				'context' => '' 
		),
		261 => array (
				'term' => 'admin_action_purge_old_files_tip',
				'definition' => 'This will delete all temporary files older than 7 days.',
				'context' => '' 
		),
		262 => array (
				'term' => 'admin_action_reload_cache_table',
				'definition' => 'Reload Cache table',
				'context' => '' 
		),
		263 => array (
				'term' => 'admin_action_reload_cache_table_tip',
				'definition' => 'This permits to reload the full content of table Cache. Can be usefull to be done sometimes.',
				'context' => '' 
		),
		264 => array (
				'term' => 'admin_backups',
				'definition' => 'Backups',
				'context' => '' 
		),
		265 => array (
				'term' => 'admin_error_no_complexity',
				'definition' => '(<a href=\'index.php?page=manage_groups\'>Define?</a>)',
				'context' => '' 
		),
		266 => array (
				'term' => 'admin_error_no_visibility',
				'definition' => 'No one can see this item. (<a href=\'index.php?page=manage_roles\'>Customize roles</a>)',
				'context' => '' 
		),
		267 => array (
				'term' => 'admin_functions',
				'definition' => 'Roles management',
				'context' => '' 
		),
		268 => array (
				'term' => 'admin_groups',
				'definition' => 'Folders management',
				'context' => '' 
		),
		269 => array (
				'term' => 'admin_help',
				'definition' => 'Help',
				'context' => '' 
		),
		270 => array (
				'term' => 'admin_info',
				'definition' => 'Some information concerning the tool',
				'context' => '' 
		),
		271 => array (
				'term' => 'admin_info_loading',
				'definition' => 'Loading data ... please wait',
				'context' => '' 
		),
		272 => array (
				'term' => 'admin_ldap_configuration',
				'definition' => 'LDAP-inställningar',
				'context' => '' 
		),
		273 => array (
				'term' => 'admin_ldap_menu',
				'definition' => 'LDAP-inställningar',
				'context' => '' 
		),
		274 => array (
				'term' => 'admin_main',
				'definition' => 'Information',
				'context' => '' 
		),
		275 => array (
				'term' => 'admin_misc_cpassman_dir',
				'definition' => 'Full path to TeamPass',
				'context' => '' 
		),
		276 => array (
				'term' => 'admin_misc_cpassman_url',
				'definition' => 'Full URL to TeamPass',
				'context' => '' 
		),
		277 => array (
				'term' => 'admin_misc_custom_login_text',
				'definition' => 'Custom Login text',
				'context' => '' 
		),
		278 => array (
				'term' => 'admin_misc_custom_logo',
				'definition' => 'Full url to Custom Login Logo',
				'context' => '' 
		),
		279 => array (
				'term' => 'admin_misc_favicon',
				'definition' => 'Full URL to favicon file',
				'context' => '' 
		),
		280 => array (
				'term' => 'admin_misc_title',
				'definition' => 'Customize',
				'context' => '' 
		),
		281 => array (
				'term' => 'admin_one_shot_backup',
				'definition' => 'One shot backup and restore',
				'context' => '' 
		),
		282 => array (
				'term' => 'admin_script_backups',
				'definition' => 'Settings for Backups script',
				'context' => '' 
		),
		283 => array (
				'term' => 'admin_script_backups_tip',
				'definition' => 'For more security, it is recommended to parameter a scheduled backup of the database.<br />Use your server to schedule a daily cron task by calling the file \'script.backup.php\' in \'backups\' folder.<br />You first need to set the 2 first paramteres and SAVE them.',
				'context' => '' 
		),
		284 => array (
				'term' => 'admin_script_backup_decrypt',
				'definition' => 'Name of the file you want to decrypt',
				'context' => '' 
		),
		285 => array (
				'term' => 'admin_script_backup_decrypt_tip',
				'definition' => 'In order to decrypt a backup file, just indicate the name of the backup file (no extension and no path).<br />The file will be decrypted in the same folder as the backup files are.',
				'context' => '' 
		),
		286 => array (
				'term' => 'admin_script_backup_encryption',
				'definition' => 'Encryption key (optional)',
				'context' => '' 
		),
		287 => array (
				'term' => 'admin_script_backup_encryption_tip',
				'definition' => 'If set, this key will be used to encrypted your file',
				'context' => '' 
		),
		288 => array (
				'term' => 'admin_script_backup_filename',
				'definition' => 'Backup file name',
				'context' => '' 
		),
		289 => array (
				'term' => 'admin_script_backup_filename_tip',
				'definition' => 'File name you want for your backups file',
				'context' => '' 
		),
		290 => array (
				'term' => 'admin_script_backup_path',
				'definition' => 'Path where backups have to be stored',
				'context' => '' 
		),
		291 => array (
				'term' => 'admin_script_backup_path_tip',
				'definition' => 'In what folder the backup files have to be stored',
				'context' => '' 
		),
		292 => array (
				'term' => 'admin_settings',
				'definition' => 'Settings',
				'context' => '' 
		),
		293 => array (
				'term' => 'admin_settings_title',
				'definition' => 'TeamPass Settings',
				'context' => '' 
		),
		294 => array (
				'term' => 'admin_setting_activate_expiration',
				'definition' => 'Enable passwords expiration',
				'context' => '' 
		),
		295 => array (
				'term' => 'admin_setting_activate_expiration_tip',
				'definition' => 'When enabled, items expired will not be displayed to users.',
				'context' => '' 
		),
		296 => array (
				'term' => 'admin_users',
				'definition' => 'Users management',
				'context' => '' 
		),
		297 => array (
				'term' => 'admin_views',
				'definition' => 'Views',
				'context' => '' 
		),
		298 => array (
				'term' => 'alert_message_done',
				'definition' => 'Klart!',
				'context' => '' 
		),
		299 => array (
				'term' => 'alert_message_personal_sk_missing',
				'definition' => 'Du måste skriva din personliga SALTKey',
				'context' => '' 
		),
		300 => array (
				'term' => 'all',
				'definition' => 'all',
				'context' => '' 
		),
		301 => array (
				'term' => 'anyone_can_modify',
				'definition' => 'Allow this item to be modified by anyone that can access it',
				'context' => '' 
		),
		302 => array (
				'term' => 'associated_role',
				'definition' => 'Roll att associera till denna mapp:',
				'context' => '' 
		),
		303 => array (
				'term' => 'associate_kb_to_items',
				'definition' => 'Select the items to associate to this KB',
				'context' => '' 
		),
		304 => array (
				'term' => 'assoc_authorized_groups',
				'definition' => 'Allowed Associated Folders',
				'context' => '' 
		),
		305 => array (
				'term' => 'assoc_forbidden_groups',
				'definition' => 'Forbidden Associated Folders',
				'context' => '' 
		),
		306 => array (
				'term' => 'at',
				'definition' => 'at',
				'context' => '' 
		),
		307 => array (
				'term' => 'at_add_file',
				'definition' => 'File added',
				'context' => '' 
		),
		308 => array (
				'term' => 'at_category',
				'definition' => 'Folder',
				'context' => '' 
		),
		309 => array (
				'term' => 'at_copy',
				'definition' => 'Copy done',
				'context' => '' 
		),
		310 => array (
				'term' => 'at_creation',
				'definition' => 'Creation',
				'context' => '' 
		),
		311 => array (
				'term' => 'at_delete',
				'definition' => 'Deletion',
				'context' => '' 
		),
		312 => array (
				'term' => 'at_del_file',
				'definition' => 'File deleted',
				'context' => '' 
		),
		313 => array (
				'term' => 'at_description',
				'definition' => 'Description.',
				'context' => '' 
		),
		314 => array (
				'term' => 'at_file',
				'definition' => 'File',
				'context' => '' 
		),
		315 => array (
				'term' => 'at_import',
				'definition' => 'Importation',
				'context' => '' 
		),
		316 => array (
				'term' => 'at_label',
				'definition' => 'Label',
				'context' => '' 
		),
		317 => array (
				'term' => 'at_login',
				'definition' => 'Login',
				'context' => '' 
		),
		318 => array (
				'term' => 'at_modification',
				'definition' => 'Modification',
				'context' => '' 
		),
		319 => array (
				'term' => 'at_moved',
				'definition' => 'Moved',
				'context' => '' 
		),
		320 => array (
				'term' => 'at_personnel',
				'definition' => 'Personal',
				'context' => '' 
		),
		321 => array (
				'term' => 'at_pw',
				'definition' => 'Password changed.',
				'context' => '' 
		),
		322 => array (
				'term' => 'at_restored',
				'definition' => 'Restored',
				'context' => '' 
		),
		323 => array (
				'term' => 'at_shown',
				'definition' => 'Accessed',
				'context' => '' 
		),
		324 => array (
				'term' => 'at_url',
				'definition' => 'URL',
				'context' => '' 
		),
		325 => array (
				'term' => 'auteur',
				'definition' => 'Author',
				'context' => '' 
		),
		326 => array (
				'term' => 'author',
				'definition' => 'Author',
				'context' => '' 
		),
		327 => array (
				'term' => 'authorized_groups',
				'definition' => 'Allowed Folders',
				'context' => '' 
		),
		328 => array (
				'term' => 'auth_creation_without_complexity',
				'definition' => 'Allow creating an item without respecting the required password complexity',
				'context' => '' 
		),
		329 => array (
				'term' => 'auth_modification_without_complexity',
				'definition' => 'Allow modifying an item without respecting the required password complexity',
				'context' => '' 
		),
		330 => array (
				'term' => 'auto_create_folder_role',
				'definition' => 'Create folder and role for ',
				'context' => '' 
		),
		331 => array (
				'term' => 'block_last_created',
				'definition' => 'Last created',
				'context' => '' 
		),
		332 => array (
				'term' => 'bugs_page',
				'definition' => 'If you discover a bug, you can directly post it in <a href=\'https://sourceforge.net/tracker/?group_id=280505&amp;atid=1190333\' target=\'_blank\'><u>Bugs Forum</u></a>.',
				'context' => '' 
		),
		333 => array (
				'term' => 'by',
				'definition' => 'by',
				'context' => '' 
		),
		334 => array (
				'term' => 'cancel',
				'definition' => 'Cancel',
				'context' => '' 
		),
		335 => array (
				'term' => 'cancel_button',
				'definition' => 'Cancel',
				'context' => '' 
		),
		336 => array (
				'term' => 'can_create_root_folder',
				'definition' => 'Can create a folder at root level',
				'context' => '' 
		),
		337 => array (
				'term' => 'changelog',
				'definition' => 'Latest news',
				'context' => '' 
		),
		338 => array (
				'term' => 'change_authorized_groups',
				'definition' => 'Change authorized folders',
				'context' => '' 
		),
		339 => array (
				'term' => 'change_forbidden_groups',
				'definition' => 'Change forbidden folders',
				'context' => '' 
		),
		340 => array (
				'term' => 'change_function',
				'definition' => 'Change roles',
				'context' => '' 
		),
		341 => array (
				'term' => 'change_group_autgroups_info',
				'definition' => 'Select the authorized folders this Role can see and use',
				'context' => '' 
		),
		342 => array (
				'term' => 'change_group_autgroups_title',
				'definition' => 'Customize the authorized folders',
				'context' => '' 
		),
		343 => array (
				'term' => 'change_group_forgroups_info',
				'definition' => 'Select the forbidden folders this Role can\'t see and use',
				'context' => '' 
		),
		344 => array (
				'term' => 'change_group_forgroups_title',
				'definition' => 'Customize the forbidden folders',
				'context' => '' 
		),
		345 => array (
				'term' => 'change_user_autgroups_info',
				'definition' => 'Select the authorized folders this account can see and use',
				'context' => '' 
		),
		346 => array (
				'term' => 'change_user_autgroups_title',
				'definition' => 'Customize the authorized folders',
				'context' => '' 
		),
		347 => array (
				'term' => 'change_user_forgroups_info',
				'definition' => 'Select the forbidden folders this account can\'t see nor use',
				'context' => '' 
		),
		348 => array (
				'term' => 'change_user_forgroups_title',
				'definition' => 'Customize the forbidden folders',
				'context' => '' 
		),
		349 => array (
				'term' => 'change_user_functions_info',
				'definition' => 'Select the functions associated to this account',
				'context' => '' 
		),
		350 => array (
				'term' => 'change_user_functions_title',
				'definition' => 'Customize associated functions',
				'context' => '' 
		),
		351 => array (
				'term' => 'check_all_text',
				'definition' => 'Markera alla',
				'context' => '' 
		),
		352 => array (
				'term' => 'close',
				'definition' => 'Close',
				'context' => '' 
		),
		353 => array (
				'term' => 'complexity',
				'definition' => 'Complexity',
				'context' => '' 
		),
		354 => array (
				'term' => 'complex_asked',
				'definition' => 'Required complexity',
				'context' => '' 
		),
		355 => array (
				'term' => 'complex_level0',
				'definition' => 'Very weak',
				'context' => '' 
		),
		356 => array (
				'term' => 'complex_level1',
				'definition' => 'Weak',
				'context' => '' 
		),
		357 => array (
				'term' => 'complex_level2',
				'definition' => 'Medium',
				'context' => '' 
		),
		358 => array (
				'term' => 'complex_level3',
				'definition' => 'Strong',
				'context' => '' 
		),
		359 => array (
				'term' => 'complex_level4',
				'definition' => 'Very strong',
				'context' => '' 
		),
		360 => array (
				'term' => 'complex_level5',
				'definition' => 'Heavy',
				'context' => '' 
		),
		361 => array (
				'term' => 'complex_level6',
				'definition' => 'Very heavy',
				'context' => '' 
		),
		362 => array (
				'term' => 'confirm',
				'definition' => 'Confirm',
				'context' => '' 
		),
		363 => array (
				'term' => 'confirm_delete_group',
				'definition' => 'You have decided to delete this Folder and all included Items ... are you sure?',
				'context' => '' 
		),
		364 => array (
				'term' => 'confirm_del_account',
				'definition' => 'You have decided to delete this Account. Are you sure?',
				'context' => '' 
		),
		365 => array (
				'term' => 'confirm_del_from_fav',
				'definition' => 'Please confirm deletion from Favourites',
				'context' => '' 
		),
		366 => array (
				'term' => 'confirm_del_role',
				'definition' => 'Please confirm the deletion of the next role:',
				'context' => '' 
		),
		367 => array (
				'term' => 'confirm_edit_role',
				'definition' => 'Please enter the name of the next role:',
				'context' => '' 
		),
		368 => array (
				'term' => 'confirm_lock_account',
				'definition' => 'You have decided to LOCK this Account. Are you sure?',
				'context' => '' 
		),
		369 => array (
				'term' => 'connection',
				'definition' => 'Connection',
				'context' => '' 
		),
		370 => array (
				'term' => 'connections',
				'definition' => 'connections',
				'context' => '' 
		),
		371 => array (
				'term' => 'copy',
				'definition' => 'Copy',
				'context' => '' 
		),
		372 => array (
				'term' => 'copy_to_clipboard_small_icons',
				'definition' => 'Enable copy to clipboard small icons in items page',
				'context' => '' 
		),
		373 => array (
				'term' => 'copy_to_clipboard_small_icons_tip',
				'definition' => '<span style=\'font-size:11px;max-width:300px;\'>This could help preventing memory usage if users have no recent computer.<br /> Indeed, the clipboard is not loaded with items informations. But no quick copy of password and login is possible.</spa',
				'context' => '' 
		),
		374 => array (
				'term' => 'creation_date',
				'definition' => 'Creation date',
				'context' => '' 
		),
		375 => array (
				'term' => 'csv_import_button_text',
				'definition' => 'Browse CSV file',
				'context' => '' 
		),
		376 => array (
				'term' => 'date',
				'definition' => 'Date',
				'context' => '' 
		),
		377 => array (
				'term' => 'date_format',
				'definition' => 'Date format',
				'context' => '' 
		),
		378 => array (
				'term' => 'days',
				'definition' => 'days',
				'context' => '' 
		),
		379 => array (
				'term' => 'definition',
				'definition' => 'Definition',
				'context' => '' 
		),
		380 => array (
				'term' => 'delete',
				'definition' => 'Delete',
				'context' => '' 
		),
		381 => array (
				'term' => 'deletion',
				'definition' => 'Deletions',
				'context' => '' 
		),
		382 => array (
				'term' => 'deletion_title',
				'definition' => 'List of Items deleted',
				'context' => '' 
		),
		383 => array (
				'term' => 'del_button',
				'definition' => 'Delete',
				'context' => '' 
		),
		384 => array (
				'term' => 'del_function',
				'definition' => 'Delete Roles',
				'context' => '' 
		),
		385 => array (
				'term' => 'del_group',
				'definition' => 'Delete Folder',
				'context' => '' 
		),
		386 => array (
				'term' => 'description',
				'definition' => 'Beskrivning',
				'context' => '' 
		),
		387 => array (
				'term' => 'disconnect',
				'definition' => 'Logout',
				'context' => '' 
		),
		388 => array (
				'term' => 'disconnection',
				'definition' => 'Logout',
				'context' => '' 
		),
		389 => array (
				'term' => 'div_dialog_message_title',
				'definition' => 'Information',
				'context' => '' 
		),
		390 => array (
				'term' => 'done',
				'definition' => 'Done',
				'context' => '' 
		),
		391 => array (
				'term' => 'drag_drop_helper',
				'definition' => 'Drag and drop item',
				'context' => '' 
		),
		392 => array (
				'term' => 'duplicate_folder',
				'definition' => 'Authorize to have several folders with the same name.',
				'context' => '' 
		),
		393 => array (
				'term' => 'duplicate_item',
				'definition' => 'Authorize to have several items with the same name.',
				'context' => '' 
		),
		394 => array (
				'term' => 'email',
				'definition' => 'Email',
				'context' => '' 
		),
		395 => array (
				'term' => 'email_altbody_1',
				'definition' => 'Item',
				'context' => '' 
		),
		396 => array (
				'term' => 'email_altbody_2',
				'definition' => 'has been created.',
				'context' => '' 
		),
		397 => array (
				'term' => 'email_announce',
				'definition' => 'Announce this Item by email',
				'context' => '' 
		),
		398 => array (
				'term' => 'email_body1',
				'definition' => 'Hi,<br><br>Item \'',
				'context' => '' 
		),
		399 => array (
				'term' => 'email_body2',
				'definition' => 'has been created.<br /><br />You may view it by clicking <a href=\'',
				'context' => '' 
		),
		400 => array (
				'term' => 'email_body3',
				'definition' => '>HERE</a><br /><br />Regards.',
				'context' => '' 
		),
		401 => array (
				'term' => 'email_change',
				'definition' => 'Change the account\'s email',
				'context' => '' 
		),
		402 => array (
				'term' => 'email_changed',
				'definition' => 'Email changed!',
				'context' => '' 
		),
		403 => array (
				'term' => 'email_select',
				'definition' => 'Select persons to inform',
				'context' => '' 
		),
		404 => array (
				'term' => 'email_subject',
				'definition' => 'Creating a new Item in Passwords Manager',
				'context' => '' 
		),
		405 => array (
				'term' => 'email_text_new_user',
				'definition' => 'Hi,<br /><br />Your account has been created in TeamPass.<br />You can now access $TeamPass_url using the next credentials:<br />',
				'context' => '' 
		),
		406 => array (
				'term' => 'enable_favourites',
				'definition' => 'Enable the Users to store Favourites',
				'context' => '' 
		),
		407 => array (
				'term' => 'enable_personal_folder',
				'definition' => 'Enable Personal folder',
				'context' => '' 
		),
		408 => array (
				'term' => 'enable_personal_folder_feature',
				'definition' => 'Enable Personal folder feature',
				'context' => '' 
		),
		409 => array (
				'term' => 'enable_user_can_create_folders',
				'definition' => 'Users are allowed to manage folders in allowed parent folders',
				'context' => '' 
		),
		410 => array (
				'term' => 'encrypt_key',
				'definition' => 'Encryption key',
				'context' => '' 
		),
		411 => array (
				'term' => 'errors',
				'definition' => 'errors',
				'context' => '' 
		),
		412 => array (
				'term' => 'error_complex_not_enought',
				'definition' => 'Password complexity is not fulfilled!',
				'context' => '' 
		),
		413 => array (
				'term' => 'error_confirm',
				'definition' => 'Password confirmation is not correct!',
				'context' => '' 
		),
		414 => array (
				'term' => 'error_cpassman_dir',
				'definition' => 'No path for TeamPass is set. Please select \'TeamPass settings\' tab in Admin Settings page.',
				'context' => '' 
		),
		415 => array (
				'term' => 'error_cpassman_url',
				'definition' => 'No URL for TeamPass is set. Please select \'TeamPass settings\' tab in Admin Settings page.',
				'context' => '' 
		),
		416 => array (
				'term' => 'error_fields_2',
				'definition' => 'The 2 fields are mandatory!',
				'context' => '' 
		),
		417 => array (
				'term' => 'error_group',
				'definition' => 'A folder is mandatory!',
				'context' => '' 
		),
		418 => array (
				'term' => 'error_group_complex',
				'definition' => 'The Folder must have a minimum required passwords complexity level!',
				'context' => '' 
		),
		419 => array (
				'term' => 'error_group_exist',
				'definition' => 'This folder already exists!',
				'context' => '' 
		),
		420 => array (
				'term' => 'error_group_label',
				'definition' => 'The Folder must be named!',
				'context' => '' 
		),
		421 => array (
				'term' => 'error_html_codes',
				'definition' => 'Some text contains HTML codes! This is not allowed.',
				'context' => '' 
		),
		422 => array (
				'term' => 'error_item_exists',
				'definition' => 'This Item already exists!',
				'context' => '' 
		),
		423 => array (
				'term' => 'error_label',
				'definition' => 'A label is mandatory!',
				'context' => '' 
		),
		424 => array (
				'term' => 'error_must_enter_all_fields',
				'definition' => 'You must fill in each fields!',
				'context' => '' 
		),
		425 => array (
				'term' => 'error_mysql',
				'definition' => 'MySQL Error!',
				'context' => '' 
		),
		426 => array (
				'term' => 'error_not_authorized',
				'definition' => 'You are not allowed to see this page.',
				'context' => '' 
		),
		427 => array (
				'term' => 'error_not_exists',
				'definition' => 'This page doesn\'t exist.',
				'context' => '' 
		),
		428 => array (
				'term' => 'error_no_folders',
				'definition' => 'You should start by creating some folders.',
				'context' => '' 
		),
		429 => array (
				'term' => 'error_no_password',
				'definition' => 'You need to enter your password!',
				'context' => '' 
		),
		430 => array (
				'term' => 'error_no_roles',
				'definition' => 'You should also create some roles and associate them to folders.',
				'context' => '' 
		),
		431 => array (
				'term' => 'error_password_confirmation',
				'definition' => 'Passwords should be the same',
				'context' => '' 
		),
		432 => array (
				'term' => 'error_pw',
				'definition' => 'A password is mandatory!',
				'context' => '' 
		),
		433 => array (
				'term' => 'error_renawal_period_not_integer',
				'definition' => 'Renewal period should be expressed in months!',
				'context' => '' 
		),
		434 => array (
				'term' => 'error_salt',
				'definition' => '<b>The SALT KEY is too long! Please don\'t use the tool until an Admin has modified the salt key.</b> In settings.php file, SALT should not be longer than 32 characters.',
				'context' => '' 
		),
		435 => array (
				'term' => 'error_tags',
				'definition' => 'No punctuation characters allowed in TAGS! Only space.',
				'context' => '' 
		),
		436 => array (
				'term' => 'error_user_exists',
				'definition' => 'Användaren finns redan',
				'context' => '' 
		),
		437 => array (
				'term' => 'expiration_date',
				'definition' => 'Expiration date',
				'context' => '' 
		),
		438 => array (
				'term' => 'expir_one_month',
				'definition' => '1 month',
				'context' => '' 
		),
		439 => array (
				'term' => 'expir_one_year',
				'definition' => '1 year',
				'context' => '' 
		),
		440 => array (
				'term' => 'expir_six_months',
				'definition' => '6 months',
				'context' => '' 
		),
		441 => array (
				'term' => 'expir_today',
				'definition' => 'today',
				'context' => '' 
		),
		442 => array (
				'term' => 'files_&_images',
				'definition' => 'Files &amp; Images',
				'context' => '' 
		),
		443 => array (
				'term' => 'find',
				'definition' => 'Find',
				'context' => '' 
		),
		444 => array (
				'term' => 'find_text',
				'definition' => 'Your search',
				'context' => '' 
		),
		445 => array (
				'term' => 'folders',
				'definition' => 'Folders',
				'context' => '' 
		),
		446 => array (
				'term' => 'forbidden_groups',
				'definition' => 'Forbidden Folders',
				'context' => '' 
		),
		447 => array (
				'term' => 'forgot_my_pw',
				'definition' => 'Forgot your password?',
				'context' => '' 
		),
		448 => array (
				'term' => 'forgot_my_pw_email_sent',
				'definition' => 'Email has been sent',
				'context' => '' 
		),
		449 => array (
				'term' => 'forgot_my_pw_error_email_not_exist',
				'definition' => 'This email doesn\'t exist!',
				'context' => '' 
		),
		450 => array (
				'term' => 'forgot_my_pw_text',
				'definition' => 'Your password will be sent to the email associated to your account.',
				'context' => '' 
		),
		451 => array (
				'term' => 'forgot_pw_email_altbody_1',
				'definition' => 'Hi, Your identification credentials for TeamPass are:',
				'context' => '' 
		),
		452 => array (
				'term' => 'forgot_pw_email_body',
				'definition' => 'Hi,<br /><br />Your new password for TeamPass is :',
				'context' => '' 
		),
		453 => array (
				'term' => 'forgot_pw_email_body_1',
				'definition' => 'Hi, <br /><br />Your identification credentials for TeamPass are:<br /><br />',
				'context' => '' 
		),
		454 => array (
				'term' => 'forgot_pw_email_subject',
				'definition' => 'TeamPass - Your password',
				'context' => '' 
		),
		455 => array (
				'term' => 'forgot_pw_email_subject_confirm',
				'definition' => '[TeamPass] Your password step 2',
				'context' => '' 
		),
		456 => array (
				'term' => 'functions',
				'definition' => 'Roles',
				'context' => '' 
		),
		457 => array (
				'term' => 'function_alarm_no_group',
				'definition' => 'This role is not associated to any Folder!',
				'context' => '' 
		),
		458 => array (
				'term' => 'generate_pdf',
				'definition' => 'Generate a PDF file',
				'context' => '' 
		),
		459 => array (
				'term' => 'generation_options',
				'definition' => 'Generation options',
				'context' => '' 
		),
		460 => array (
				'term' => 'gestionnaire',
				'definition' => 'Manager',
				'context' => '' 
		),
		461 => array (
				'term' => 'give_function_tip',
				'definition' => 'Add a new role',
				'context' => '' 
		),
		462 => array (
				'term' => 'give_function_title',
				'definition' => 'Add a new Role',
				'context' => '' 
		),
		463 => array (
				'term' => 'give_new_email',
				'definition' => 'Please enter new email for',
				'context' => '' 
		),
		464 => array (
				'term' => 'give_new_login',
				'definition' => 'Please select the account',
				'context' => '' 
		),
		465 => array (
				'term' => 'give_new_pw',
				'definition' => 'Please indicate the new password for',
				'context' => '' 
		),
		466 => array (
				'term' => 'god',
				'definition' => 'Administrator',
				'context' => '' 
		),
		467 => array (
				'term' => 'group',
				'definition' => 'Folder',
				'context' => '' 
		),
		468 => array (
				'term' => 'group_parent',
				'definition' => 'Parent Folder',
				'context' => '' 
		),
		469 => array (
				'term' => 'group_pw_duration',
				'definition' => 'Renewal period',
				'context' => '' 
		),
		470 => array (
				'term' => 'group_pw_duration_tip',
				'definition' => 'In months. Use 0 to disable.',
				'context' => '' 
		),
		471 => array (
				'term' => 'group_select',
				'definition' => 'Select folder',
				'context' => '' 
		),
		472 => array (
				'term' => 'group_title',
				'definition' => 'Folder label',
				'context' => '' 
		),
		473 => array (
				'term' => 'history',
				'definition' => 'History',
				'context' => '' 
		),
		474 => array (
				'term' => 'home',
				'definition' => 'Home',
				'context' => '' 
		),
		475 => array (
				'term' => 'home_personal_menu',
				'definition' => 'Personal Actions',
				'context' => '' 
		),
		476 => array (
				'term' => 'home_personal_saltkey',
				'definition' => 'Din personliga SALTKey',
				'context' => '' 
		),
		477 => array (
				'term' => 'home_personal_saltkey_button',
				'definition' => 'Spara!',
				'context' => '' 
		),
		478 => array (
				'term' => 'home_personal_saltkey_info',
				'definition' => 'You should enter your personal saltkey if you need to use your personal items.',
				'context' => '' 
		),
		479 => array (
				'term' => 'home_personal_saltkey_label',
				'definition' => 'Skriv din personliga SALTKey',
				'context' => '' 
		),
		480 => array (
				'term' => 'importing_details',
				'definition' => 'List of details',
				'context' => '' 
		),
		481 => array (
				'term' => 'importing_folders',
				'definition' => 'Importing folders',
				'context' => '' 
		),
		482 => array (
				'term' => 'importing_items',
				'definition' => 'Importing items',
				'context' => '' 
		),
		483 => array (
				'term' => 'import_button',
				'definition' => 'Import',
				'context' => '' 
		),
		484 => array (
				'term' => 'import_csv_anyone_can_modify_in_role_txt',
				'definition' => 'Set "anyone in the same role can modify" right on all imported items.',
				'context' => '' 
		),
		485 => array (
				'term' => 'import_csv_anyone_can_modify_txt',
				'definition' => 'Set "anyone can modify" right on all imported items.',
				'context' => '' 
		),
		486 => array (
				'term' => 'import_csv_dialog_info',
				'definition' => 'Information: import must be done using a CSV file. Typically a file exported from KeePass has the expected structure.<br />If you use a file generated by another tool, please check that CSV structure is as follows: `Account`,`Login Name`,`Password`,`Web Site`,`Comments`.',
				'context' => '' 
		),
		487 => array (
				'term' => 'import_csv_menu_title',
				'definition' => 'Import Items',
				'context' => '' 
		),
		488 => array (
				'term' => 'import_error_no_file',
				'definition' => 'You must select a file!',
				'context' => '' 
		),
		489 => array (
				'term' => 'import_error_no_read_possible',
				'definition' => 'Can\'t read the file!',
				'context' => '' 
		),
		490 => array (
				'term' => 'import_error_no_read_possible_kp',
				'definition' => 'Can\'t read the file! It must be a KeePass file.',
				'context' => '' 
		),
		491 => array (
				'term' => 'import_keepass_dialog_info',
				'definition' => 'Please use this to select an XML file generated by KeePass export functionality. Will only work with KeePass file! Notice that the import script will not import folders or elements that already exist at the same level of the tree structure.',
				'context' => '' 
		),
		492 => array (
				'term' => 'import_keepass_to_folder',
				'definition' => 'Select the destination folder',
				'context' => '' 
		),
		493 => array (
				'term' => 'import_kp_finished',
				'definition' => 'Import from KeePass is now finished !<br />By default, the complexity level for new folders have been set to `Medium`. Perhaps will you need to change it.',
				'context' => '' 
		),
		494 => array (
				'term' => 'import_to_folder',
				'definition' => 'Tick the items you want to import to folder:',
				'context' => '' 
		),
		495 => array (
				'term' => 'index_add_one_hour',
				'definition' => 'Extend session by 1 hour',
				'context' => '' 
		),
		496 => array (
				'term' => 'index_alarm',
				'definition' => 'ALARM!!!',
				'context' => '' 
		),
		497 => array (
				'term' => 'index_bas_pw',
				'definition' => 'Bad password for this account!',
				'context' => '' 
		),
		498 => array (
				'term' => 'index_change_pw',
				'definition' => 'Change your password',
				'context' => '' 
		),
		499 => array (
				'term' => 'index_change_pw_button',
				'definition' => 'Change',
				'context' => '' 
		),
		500 => array (
				'term' => 'index_change_pw_confirmation',
				'definition' => 'Confirm',
				'context' => '' 
		),
		501 => array (
				'term' => 'index_expiration_in',
				'definition' => 'session expiration in',
				'context' => '' 
		),
		502 => array (
				'term' => 'index_get_identified',
				'definition' => 'Please identify yourself',
				'context' => '' 
		),
		503 => array (
				'term' => 'index_identify_button',
				'definition' => 'Enter',
				'context' => '' 
		),
		504 => array (
				'term' => 'index_identify_you',
				'definition' => 'Please identify yourself',
				'context' => '' 
		),
		505 => array (
				'term' => 'index_last_pw_change',
				'definition' => 'Password changed the',
				'context' => '' 
		),
		506 => array (
				'term' => 'index_last_seen',
				'definition' => 'Last connection, the',
				'context' => '' 
		),
		507 => array (
				'term' => 'index_login',
				'definition' => 'Account',
				'context' => '' 
		),
		508 => array (
				'term' => 'index_maintenance_mode',
				'definition' => 'Maintenance mode has been activated. Only Administrators can log in.',
				'context' => '' 
		),
		509 => array (
				'term' => 'index_maintenance_mode_admin',
				'definition' => 'Maintenance mode is activated. Users currently can not access TeamPass.',
				'context' => '' 
		),
		510 => array (
				'term' => 'index_new_pw',
				'definition' => 'New password',
				'context' => '' 
		),
		511 => array (
				'term' => 'index_password',
				'definition' => 'Password',
				'context' => '' 
		),
		512 => array (
				'term' => 'index_pw_error_identical',
				'definition' => 'The passwords have to be identical!',
				'context' => '' 
		),
		513 => array (
				'term' => 'index_pw_expiration',
				'definition' => 'Actual password expiration in',
				'context' => '' 
		),
		514 => array (
				'term' => 'index_pw_level_txt',
				'definition' => 'Complexity',
				'context' => '' 
		),
		515 => array (
				'term' => 'index_refresh_page',
				'definition' => 'Refresh page',
				'context' => '' 
		),
		516 => array (
				'term' => 'index_session_duration',
				'definition' => 'Session duration',
				'context' => '' 
		),
		517 => array (
				'term' => 'index_session_ending',
				'definition' => 'Your session will end in less than 1 minute.',
				'context' => '' 
		),
		518 => array (
				'term' => 'index_session_expired',
				'definition' => 'Your session has expired or you are not correctly identified!',
				'context' => '' 
		),
		519 => array (
				'term' => 'index_welcome',
				'definition' => 'Welcome',
				'context' => '' 
		),
		520 => array (
				'term' => 'info',
				'definition' => 'Information',
				'context' => '' 
		),
		521 => array (
				'term' => 'info_click_to_edit',
				'definition' => 'Click on a cell to edit its value',
				'context' => '' 
		),
		522 => array (
				'term' => 'is_admin',
				'definition' => 'Is Admin',
				'context' => '' 
		),
		523 => array (
				'term' => 'is_manager',
				'definition' => 'Is Manager',
				'context' => '' 
		),
		524 => array (
				'term' => 'is_read_only',
				'definition' => 'Is Read Only',
				'context' => '' 
		),
		525 => array (
				'term' => 'items_browser_title',
				'definition' => 'Folders',
				'context' => '' 
		),
		526 => array (
				'term' => 'item_copy_to_folder',
				'definition' => 'Please select a folder in which the item has to be copied.',
				'context' => '' 
		),
		527 => array (
				'term' => 'item_menu_add_elem',
				'definition' => 'Add item',
				'context' => '' 
		),
		528 => array (
				'term' => 'item_menu_add_rep',
				'definition' => 'Add a Folder',
				'context' => '' 
		),
		529 => array (
				'term' => 'item_menu_add_to_fav',
				'definition' => 'Add to Favourites',
				'context' => '' 
		),
		530 => array (
				'term' => 'item_menu_collab_disable',
				'definition' => 'Edition is not allowed',
				'context' => '' 
		),
		531 => array (
				'term' => 'item_menu_collab_enable',
				'definition' => 'Edition is allowed',
				'context' => '' 
		),
		532 => array (
				'term' => 'item_menu_copy_elem',
				'definition' => 'Copy item',
				'context' => '' 
		),
		533 => array (
				'term' => 'item_menu_copy_login',
				'definition' => 'Copy login',
				'context' => '' 
		),
		534 => array (
				'term' => 'item_menu_copy_pw',
				'definition' => 'Copy password',
				'context' => '' 
		),
		535 => array (
				'term' => 'item_menu_del_elem',
				'definition' => 'Delete item',
				'context' => '' 
		),
		536 => array (
				'term' => 'item_menu_del_from_fav',
				'definition' => 'Delete from Favourites',
				'context' => '' 
		),
		537 => array (
				'term' => 'item_menu_del_rep',
				'definition' => 'Delete a Folder',
				'context' => '' 
		),
		538 => array (
				'term' => 'item_menu_edi_elem',
				'definition' => 'Edit item',
				'context' => '' 
		),
		539 => array (
				'term' => 'item_menu_edi_rep',
				'definition' => 'Edit a Folder',
				'context' => '' 
		),
		540 => array (
				'term' => 'item_menu_find',
				'definition' => 'Search',
				'context' => '' 
		),
		541 => array (
				'term' => 'item_menu_mask_pw',
				'definition' => 'Mask password',
				'context' => '' 
		),
		542 => array (
				'term' => 'item_menu_refresh',
				'definition' => 'Refresh page',
				'context' => '' 
		),
		543 => array (
				'term' => 'kbs',
				'definition' => 'KBs',
				'context' => '' 
		),
		544 => array (
				'term' => 'kb_menu',
				'definition' => 'Knowledge Base',
				'context' => '' 
		),
		545 => array (
				'term' => 'keepass_import_button_text',
				'definition' => 'Browse XML file and import entries',
				'context' => '' 
		),
		546 => array (
				'term' => 'label',
				'definition' => 'Label',
				'context' => '' 
		),
		547 => array (
				'term' => 'last_items_icon_title',
				'definition' => 'Show/Hide Last items seen',
				'context' => '' 
		),
		548 => array (
				'term' => 'last_items_title',
				'definition' => 'Last items seen',
				'context' => '' 
		),
		549 => array (
				'term' => 'ldap_extension_not_loaded',
				'definition' => 'The LDAP extension is not activated on the server.',
				'context' => '' 
		),
		550 => array (
				'term' => 'level',
				'definition' => 'Level',
				'context' => '' 
		),
		551 => array (
				'term' => 'link_copy',
				'definition' => 'Get a link to this item',
				'context' => '' 
		),
		552 => array (
				'term' => 'link_is_copied',
				'definition' => 'The link to this Item has been copied to clipboard.',
				'context' => '' 
		),
		553 => array (
				'term' => 'login',
				'definition' => 'Login (if needed)',
				'context' => '' 
		),
		554 => array (
				'term' => 'login_attempts_on',
				'definition' => ' login attempts on ',
				'context' => '' 
		),
		555 => array (
				'term' => 'login_copied_clipboard',
				'definition' => 'Login copied in clipboard',
				'context' => '' 
		),
		556 => array (
				'term' => 'login_copy',
				'definition' => 'Copy account to clipboard',
				'context' => '' 
		),
		557 => array (
				'term' => 'logs',
				'definition' => 'Logs',
				'context' => '' 
		),
		558 => array (
				'term' => 'logs_1',
				'definition' => 'Generate the log file for the passwords renewal done the',
				'context' => '' 
		),
		559 => array (
				'term' => 'logs_passwords',
				'definition' => 'Generate Passwords Log',
				'context' => '' 
		),
		560 => array (
				'term' => 'maj',
				'definition' => 'Uppercase letters',
				'context' => '' 
		),
		561 => array (
				'term' => 'mask_pw',
				'definition' => 'Mask/Display the password',
				'context' => '' 
		),
		562 => array (
				'term' => 'max_last_items',
				'definition' => 'Maximum number of last items seen by user (default is 10)',
				'context' => '' 
		),
		563 => array (
				'term' => 'menu_title_new_personal_saltkey',
				'definition' => 'Changing your Personal Saltkey',
				'context' => '' 
		),
		564 => array (
				'term' => 'minutes',
				'definition' => 'minuter',
				'context' => '' 
		),
		565 => array (
				'term' => 'modify_button',
				'definition' => 'Modify',
				'context' => '' 
		),
		566 => array (
				'term' => 'my_favourites',
				'definition' => 'My favourites',
				'context' => '' 
		),
		567 => array (
				'term' => 'name',
				'definition' => 'Name',
				'context' => '' 
		),
		568 => array (
				'term' => 'nb_false_login_attempts',
				'definition' => 'Number of false login attempts before account is disabled (0 to disable)',
				'context' => '' 
		),
		569 => array (
				'term' => 'nb_folders',
				'definition' => 'Number of Folders',
				'context' => '' 
		),
		570 => array (
				'term' => 'nb_items',
				'definition' => 'Number of Items',
				'context' => '' 
		),
		571 => array (
				'term' => 'nb_items_by_page',
				'definition' => 'Number of items by page',
				'context' => '' 
		),
		572 => array (
				'term' => 'new_label',
				'definition' => 'New label',
				'context' => '' 
		),
		573 => array (
				'term' => 'new_role_title',
				'definition' => 'New role title',
				'context' => '' 
		),
		574 => array (
				'term' => 'new_saltkey',
				'definition' => 'New Saltkey',
				'context' => '' 
		),
		575 => array (
				'term' => 'new_saltkey_warning',
				'definition' => 'Please be sure to use the original SaltKey, otherwize the new encryption will be corrupted. Before doing any change, please test your actual SaltKey!',
				'context' => '' 
		),
		576 => array (
				'term' => 'new_user_title',
				'definition' => 'Add a new user',
				'context' => '' 
		),
		577 => array (
				'term' => 'no',
				'definition' => 'No',
				'context' => '' 
		),
		578 => array (
				'term' => 'nom',
				'definition' => 'Name',
				'context' => '' 
		),
		579 => array (
				'term' => 'none',
				'definition' => 'None',
				'context' => '' 
		),
		580 => array (
				'term' => 'none_selected_text',
				'definition' => 'Ingen markerad',
				'context' => '' 
		),
		581 => array (
				'term' => 'not_allowed_to_see_pw',
				'definition' => 'You are not allowed to see that Item!',
				'context' => '' 
		),
		582 => array (
				'term' => 'not_allowed_to_see_pw_is_expired',
				'definition' => 'This item has expired!',
				'context' => '' 
		),
		583 => array (
				'term' => 'not_defined',
				'definition' => 'Not defined',
				'context' => '' 
		),
		584 => array (
				'term' => 'no_last_items',
				'definition' => 'No items seen',
				'context' => '' 
		),
		585 => array (
				'term' => 'no_restriction',
				'definition' => 'Inga begränsningar',
				'context' => '' 
		),
		586 => array (
				'term' => 'numbers',
				'definition' => 'Numbers',
				'context' => '' 
		),
		587 => array (
				'term' => 'number_of_used_pw',
				'definition' => 'Number of new passwords a user has to enter before reusing an old one.',
				'context' => '' 
		),
		588 => array (
				'term' => 'ok',
				'definition' => 'OK',
				'context' => '' 
		),
		589 => array (
				'term' => 'pages',
				'definition' => 'Pages',
				'context' => '' 
		),
		590 => array (
				'term' => 'pdf_del_date',
				'definition' => 'PDF generated the',
				'context' => '' 
		),
		591 => array (
				'term' => 'pdf_del_title',
				'definition' => 'Passwords renewal follow-up',
				'context' => '' 
		),
		592 => array (
				'term' => 'pdf_download',
				'definition' => 'Download file',
				'context' => '' 
		),
		593 => array (
				'term' => 'personal_folder',
				'definition' => 'Personal folder',
				'context' => '' 
		),
		594 => array (
				'term' => 'personal_saltkey_change_button',
				'definition' => 'Change it!',
				'context' => '' 
		),
		595 => array (
				'term' => 'personal_salt_key',
				'definition' => 'Your personal salt key',
				'context' => '' 
		),
		596 => array (
				'term' => 'personal_salt_key_empty',
				'definition' => 'Personal salt key has not been entered!',
				'context' => '' 
		),
		597 => array (
				'term' => 'personal_salt_key_info',
				'definition' => 'This salt key will be used to encrypt and decrypt your passwords.<br />It is not stored in database, you are the only person who knows it.<br />So don\'t loose it!',
				'context' => '' 
		),
		598 => array (
				'term' => 'please_update',
				'definition' => 'Please update the tool!',
				'context' => '' 
		),
		599 => array (
				'term' => 'print',
				'definition' => 'Print',
				'context' => '' 
		),
		600 => array (
				'term' => 'print_out_menu_title',
				'definition' => 'Export items',
				'context' => '' 
		),
		601 => array (
				'term' => 'print_out_pdf_title',
				'definition' => 'TeamPass - List of exported Items',
				'context' => '' 
		),
		602 => array (
				'term' => 'print_out_warning',
				'definition' => 'By writing the file containing unencrypted items/passwords, you are accepting the full responsibility for further protection of this list! Your PDF export will be logged.',
				'context' => '' 
		),
		603 => array (
				'term' => 'pw',
				'definition' => 'Password',
				'context' => '' 
		),
		604 => array (
				'term' => 'pw_change',
				'definition' => 'Change the account\'s password',
				'context' => '' 
		),
		605 => array (
				'term' => 'pw_changed',
				'definition' => 'Password changed!',
				'context' => '' 
		),
		606 => array (
				'term' => 'pw_copied_clipboard',
				'definition' => 'Password copied to clipboard',
				'context' => '' 
		),
		607 => array (
				'term' => 'pw_copy_clipboard',
				'definition' => 'Copy password to clipboard',
				'context' => '' 
		),
		608 => array (
				'term' => 'pw_generate',
				'definition' => 'Generate',
				'context' => '' 
		),
		609 => array (
				'term' => 'pw_is_expired_-_update_it',
				'definition' => 'This item has expired! You need to change its password.',
				'context' => '' 
		),
		610 => array (
				'term' => 'pw_life_duration',
				'definition' => 'Users\' password life duration before expiration (in days, 0 to disable)',
				'context' => '' 
		),
		611 => array (
				'term' => 'pw_recovery_asked',
				'definition' => 'You have asked for a password recovery',
				'context' => '' 
		),
		612 => array (
				'term' => 'pw_recovery_button',
				'definition' => 'Send me my new password',
				'context' => '' 
		),
		613 => array (
				'term' => 'pw_recovery_info',
				'definition' => 'By clicking on the next button, you will receive an email that contains the new password for your account.',
				'context' => '' 
		),
		614 => array (
				'term' => 'pw_used',
				'definition' => 'This password has already been used!',
				'context' => '' 
		),
		615 => array (
				'term' => 'readme_open',
				'definition' => 'Open full readme file',
				'context' => '' 
		),
		616 => array (
				'term' => 'read_only_account',
				'definition' => 'Read Only',
				'context' => '' 
		),
		617 => array (
				'term' => 'refresh_matrix',
				'definition' => 'Refresh Matrix',
				'context' => '' 
		),
		618 => array (
				'term' => 'renewal_menu',
				'definition' => 'Renewal follow-up',
				'context' => '' 
		),
		619 => array (
				'term' => 'renewal_needed_pdf_title',
				'definition' => 'List of Items that need to be renewed',
				'context' => '' 
		),
		620 => array (
				'term' => 'renewal_selection_text',
				'definition' => 'List all items that will expire:',
				'context' => '' 
		),
		621 => array (
				'term' => 'restore',
				'definition' => 'Restore',
				'context' => '' 
		),
		622 => array (
				'term' => 'restricted_to',
				'definition' => 'Restricted to',
				'context' => '' 
		),
		623 => array (
				'term' => 'restricted_to_roles',
				'definition' => 'Allow to restrict items to Users and Roles',
				'context' => '' 
		),
		624 => array (
				'term' => 'rights_matrix',
				'definition' => 'Users rights matrix',
				'context' => '' 
		),
		625 => array (
				'term' => 'roles',
				'definition' => 'Roles',
				'context' => '' 
		),
		626 => array (
				'term' => 'role_cannot_modify_all_seen_items',
				'definition' => 'Set this role not allowed to modify all accessible items (normal setting)',
				'context' => '' 
		),
		627 => array (
				'term' => 'role_can_modify_all_seen_items',
				'definition' => 'Set this role allowed to modify all accessible items (not secure setting)',
				'context' => '' 
		),
		628 => array (
				'term' => 'root',
				'definition' => 'Root',
				'context' => '' 
		),
		629 => array (
				'term' => 'save_button',
				'definition' => 'Save',
				'context' => '' 
		),
		630 => array (
				'term' => 'secure',
				'definition' => 'Secure',
				'context' => '' 
		),
		631 => array (
				'term' => 'see_logs',
				'definition' => 'See Logs',
				'context' => '' 
		),
		632 => array (
				'term' => 'select_folders',
				'definition' => 'Select folders',
				'context' => '' 
		),
		633 => array (
				'term' => 'select_language',
				'definition' => 'Select your language',
				'context' => '' 
		),
		634 => array (
				'term' => 'send',
				'definition' => 'Send',
				'context' => '' 
		),
		635 => array (
				'term' => 'settings_anyone_can_modify',
				'definition' => 'Activate an option for each item that allows anyone to modify it',
				'context' => '' 
		),
		636 => array (
				'term' => 'settings_anyone_can_modify_tip',
				'definition' => '<span style=\'font-size:11px;max-width:300px;\'>When activated, this will add a checkbox in the item form that permits the creator to allow the modification of this item by anyone.</span>',
				'context' => '' 
		),
		637 => array (
				'term' => 'settings_default_language',
				'definition' => 'Define the Default Language',
				'context' => '' 
		),
		638 => array (
				'term' => 'settings_kb',
				'definition' => 'Enable Knowledge Base (beta)',
				'context' => '' 
		),
		639 => array (
				'term' => 'settings_kb_tip',
				'definition' => '<span style=\'font-size:11px;max-width:300px;\'>When activated, this will add a page where you can build your knowledge base.</span>',
				'context' => '' 
		),
		640 => array (
				'term' => 'settings_ldap_domain',
				'definition' => 'LDAP kontosuffix för din domän',
				'context' => '' 
		),
		641 => array (
				'term' => 'settings_ldap_domain_controler',
				'definition' => 'LDAP array of domain controllers',
				'context' => '' 
		),
		642 => array (
				'term' => 'settings_ldap_domain_controler_tip',
				'definition' => '<span style=\'font-size:11px;max-width:300px;\'>Specifiy multiple controllers if you would like the class to balance the LDAP queries amongst multiple servers.<br />You must delimit the domains by a comma (,)!<br />By example: domain_1,domain_2,domain_3</span>',
				'context' => '' 
		),
		643 => array (
				'term' => 'settings_ldap_domain_dn',
				'definition' => 'LDAP base dn for your domain',
				'context' => '' 
		),
		644 => array (
				'term' => 'settings_ldap_mode',
				'definition' => 'Enable users authentification through LDAP server',
				'context' => '' 
		),
		645 => array (
				'term' => 'settings_ldap_mode_tip',
				'definition' => 'Enable only if you have an LDAP server and if you want to use it to authentify TeamPass users through it.',
				'context' => '' 
		),
		646 => array (
				'term' => 'settings_ldap_ssl',
				'definition' => 'Använd LDAP över SSL (LDAPS)',
				'context' => '' 
		),
		647 => array (
				'term' => 'settings_ldap_tls',
				'definition' => 'Används LDAP över TSL',
				'context' => '' 
		),
		648 => array (
				'term' => 'settings_log_accessed',
				'definition' => 'Enable logging who accessed the items',
				'context' => '' 
		),
		649 => array (
				'term' => 'settings_log_connections',
				'definition' => 'Enable logging all users connections into database.',
				'context' => '' 
		),
		650 => array (
				'term' => 'settings_maintenance_mode',
				'definition' => 'Set TeamPass in Maintenance mode',
				'context' => '' 
		),
		651 => array (
				'term' => 'settings_maintenance_mode_tip',
				'definition' => 'This mode will refuse any user connection except for Administrators.',
				'context' => '' 
		),
		652 => array (
				'term' => 'settings_manager_edit',
				'definition' => 'Managers can edit and delete Items they are allowed to see',
				'context' => '' 
		),
		653 => array (
				'term' => 'settings_printing',
				'definition' => 'Enable printing items to PDF file',
				'context' => '' 
		),
		654 => array (
				'term' => 'settings_printing_tip',
				'definition' => 'When enabled, a button will be added to user\'s home page that will permit him/her to write a listing of items to a PDF file he/she can view. Notice that the listed passwords will be uncrypted.',
				'context' => '' 
		),
		655 => array (
				'term' => 'settings_restricted_to',
				'definition' => 'Enable Restricted To functionality on Items',
				'context' => '' 
		),
		656 => array (
				'term' => 'settings_richtext',
				'definition' => 'Enable richtext for item description',
				'context' => '' 
		),
		657 => array (
				'term' => 'settings_richtext_tip',
				'definition' => '<span style=\'font-size:11px;max-width:300px;\'>This will activate a richtext with BBCodes in description field.</span>',
				'context' => '' 
		),
		658 => array (
				'term' => 'settings_send_stats',
				'definition' => 'Send monthly statistics to author for better understand your usage of TeamPass',
				'context' => '' 
		),
		659 => array (
				'term' => 'settings_send_stats_tip',
				'definition' => 'These statistics are entirely anonymous!<br /><span style=\'font-size:10px;max-width:300px;\'>Your IP is not sent, just the following data are transmitted: amount of Items, Folders, Users, TeamPass version, personal folders enabled, ldap enabled.<br />Many thanks if you enable those statistics. By this you help me further develop TeamPass.</span>',
				'context' => '' 
		),
		660 => array (
				'term' => 'settings_show_description',
				'definition' => 'Show Description in list of Items',
				'context' => '' 
		),
		661 => array (
				'term' => 'show',
				'definition' => 'Show',
				'context' => '' 
		),
		662 => array (
				'term' => 'show_help',
				'definition' => 'Show Help',
				'context' => '' 
		),
		663 => array (
				'term' => 'show_last_items',
				'definition' => 'Show last items block on main page',
				'context' => '' 
		),
		664 => array (
				'term' => 'size',
				'definition' => 'Size',
				'context' => '' 
		),
		665 => array (
				'term' => 'start_upload',
				'definition' => 'Start uploading files',
				'context' => '' 
		),
		666 => array (
				'term' => 'sub_group_of',
				'definition' => 'Dependent on',
				'context' => '' 
		),
		667 => array (
				'term' => 'support_page',
				'definition' => 'For any support, please use the <a href=\'https://sourceforge.net/projects/communitypasswo/forums\' target=\'_blank\'><u>Forum</u></a>.',
				'context' => '' 
		),
		668 => array (
				'term' => 'symbols',
				'definition' => 'Symbols',
				'context' => '' 
		),
		669 => array (
				'term' => 'tags',
				'definition' => 'Tags',
				'context' => '' 
		),
		670 => array (
				'term' => 'thku',
				'definition' => 'Thank you for using TeamPass!',
				'context' => '' 
		),
		671 => array (
				'term' => 'timezone_selection',
				'definition' => 'Timezone selection',
				'context' => '' 
		),
		672 => array (
				'term' => 'time_format',
				'definition' => 'Time format',
				'context' => '' 
		),
		673 => array (
				'term' => 'uncheck_all_text',
				'definition' => 'Avmarkera alla',
				'context' => '' 
		),
		674 => array (
				'term' => 'unlock_user',
				'definition' => 'User is locked. Do you want to unlock this account?',
				'context' => '' 
		),
		675 => array (
				'term' => 'update_needed_mode_admin',
				'definition' => 'It is recommended to update your TeamPass installation. Click <a href=\'install/upgrade.php\'>HERE</a>',
				'context' => '' 
		),
		676 => array (
				'term' => 'uploaded_files',
				'definition' => 'Existing Files',
				'context' => '' 
		),
		677 => array (
				'term' => 'upload_button_text',
				'definition' => 'Browse',
				'context' => '' 
		),
		678 => array (
				'term' => 'upload_files',
				'definition' => 'Upload New Files',
				'context' => '' 
		),
		679 => array (
				'term' => 'url',
				'definition' => 'URL',
				'context' => '' 
		),
		680 => array (
				'term' => 'url_copied',
				'definition' => 'URL has been copied!',
				'context' => '' 
		),
		681 => array (
				'term' => 'used_pw',
				'definition' => 'Used password',
				'context' => '' 
		),
		682 => array (
				'term' => 'user',
				'definition' => 'User',
				'context' => '' 
		),
		683 => array (
				'term' => 'users',
				'definition' => 'Users',
				'context' => '' 
		),
		684 => array (
				'term' => 'users_online',
				'definition' => 'users online',
				'context' => '' 
		),
		685 => array (
				'term' => 'user_action',
				'definition' => 'Action on a user',
				'context' => '' 
		),
		686 => array (
				'term' => 'user_alarm_no_function',
				'definition' => 'This user has no Roles!',
				'context' => '' 
		),
		687 => array (
				'term' => 'user_del',
				'definition' => 'Delete account',
				'context' => '' 
		),
		688 => array (
				'term' => 'user_lock',
				'definition' => 'Lock user',
				'context' => '' 
		),
		689 => array (
				'term' => 'version',
				'definition' => 'Current version',
				'context' => '' 
		),
		690 => array (
				'term' => 'views_confirm_items_deletion',
				'definition' => 'Do you really want to delete the selected items from database?',
				'context' => '' 
		),
		691 => array (
				'term' => 'views_confirm_restoration',
				'definition' => 'Please confirm the restoration of this Item',
				'context' => '' 
		),
		692 => array (
				'term' => 'visibility',
				'definition' => 'Visibility',
				'context' => '' 
		),
		693 => array (
				'term' => 'warning_screen_height',
				'definition' => 'WARNING: screen height is not enough for displaying items list!',
				'context' => '' 
		),
		694 => array (
				'term' => 'yes',
				'definition' => 'Yes',
				'context' => '' 
		),
		695 => array (
				'term' => 'your_version',
				'definition' => 'Your version',
				'context' => '' 
		),
		696 => array (
				'term' => 'disconnect_all_users_sure',
				'definition' => 'Etes-vous sur de vouloir déconnecter tous les utilisateurs ?',
				'context' => '' 
		),
		697 => array (
				'term' => 'Test the Email configuration',
				'definition' => 'Tester la configuration des Emails',
				'context' => '' 
		),
		698 => array (
				'term' => 'url_copied_clipboard',
				'definition' => 'URL copied in clipboard',
				'context' => '' 
		),
		699 => array (
				'term' => 'url_copy',
				'definition' => 'Copy URL in clipboard',
				'context' => '' 
		),
		700 => array (
				'term' => 'one_time_item_view',
				'definition' => 'One time view link',
				'context' => '' 
		),
		701 => array (
				'term' => 'one_time_view_item_url_box',
				'definition' => 'Share the One-Time URL with a person of Trust <br><br>#URL#<br><br>Remember that this link will only be visible one time until the #DAY#',
				'context' => '' 
		),
		702 => array (
				'term' => 'admin_api',
				'definition' => 'API',
				'context' => '' 
		),
		703 => array (
				'term' => 'settings_api',
				'definition' => 'Enable access to Teampass Items through API',
				'context' => '' 
		),
		704 => array (
				'term' => 'settings_api_tip',
				'definition' => 'API access permits to do access the Items from a third party application in JSON format.',
				'context' => '' 
		),
		705 => array (
				'term' => 'settings_api_keys_list',
				'definition' => 'List of Keys',
				'context' => '' 
		),
		706 => array (
				'term' => 'settings_api_keys_list_tip',
				'definition' => 'This is the keys that are allowed to access to Teampass. Without a valid Key, the access is not possible. You should share those Keys carrefuly!',
				'context' => '' 
		),
		707 => array (
				'term' => 'settings_api_generate_key',
				'definition' => 'Generate a key',
				'context' => '' 
		),
		708 => array (
				'term' => 'settings_api_delete_key',
				'definition' => 'Delete key',
				'context' => '' 
		),
		709 => array (
				'term' => 'settings_api_add_key',
				'definition' => 'Add new key',
				'context' => '' 
		),
		710 => array (
				'term' => 'settings_api_key',
				'definition' => 'Key',
				'context' => '' 
		),
		711 => array (
				'term' => 'settings_api_key_label',
				'definition' => 'Label',
				'context' => '' 
		),
		712 => array (
				'term' => 'settings_api_ip_whitelist',
				'definition' => 'White-list of authorized IP',
				'context' => '' 
		),
		713 => array (
				'term' => 'settings_api_ip_whitelist_tip',
				'definition' => 'If no IP is listed then any IP is authorized.',
				'context' => '' 
		),
		714 => array (
				'term' => 'settings_api_add_ip',
				'definition' => 'Add new IP',
				'context' => '' 
		),
		715 => array (
				'term' => 'settings_api_db_intro',
				'definition' => 'Give a label for this new Key (not mandatory but recommended)',
				'context' => '' 
		),
		716 => array (
				'term' => 'error_too_long',
				'definition' => 'Error - Too long string!',
				'context' => '' 
		),
		717 => array (
				'term' => 'settings_api_ip',
				'definition' => 'IP',
				'context' => '' 
		),
		718 => array (
				'term' => 'settings_api_db_intro_ip',
				'definition' => 'Give a label for this new IP',
				'context' => '' 
		),
		719 => array (
				'term' => 'settings_api_world_open',
				'definition' => 'No IP defined. The feature is totally open from any location (maybe unsecure).',
				'context' => '' 
		),
		720 => array (
				'term' => 'subfolder_rights_as_parent',
				'definition' => 'New sub-folder inherits rights from parent folder',
				'context' => '' 
		),
		721 => array (
				'term' => 'subfolder_rights_as_parent_tip',
				'definition' => 'When this feature is disabled, each new sub-folder inherits the rights associated to the Creator roles. If enabled, then each new sub-folder inherits the rights of the parent folder.',
				'context' => '' 
		),
		722 => array (
				'term' => 'show_only_accessible_folders_tip',
				'definition' => 'By default, the user see the complete path of the tree even if he doesn\'t have access to all of the folders. You may simplify this removing from the tree the folders he has no access to.',
				'context' => '' 
		),
		723 => array (
				'term' => 'show_only_accessible_folders',
				'definition' => 'Simplify the Items Tree by removing the Folders the user has no access to',
				'context' => '' 
		),
		724 => array (
				'term' => 'suggestion',
				'definition' => 'Items suggestion',
				'context' => '' 
		),
		725 => array (
				'term' => 'suggestion_add',
				'definition' => 'Add an item suggestion',
				'context' => '' 
		),
		726 => array (
				'term' => 'comment',
				'definition' => 'Comment',
				'context' => '' 
		),
		727 => array (
				'term' => 'suggestion_error_duplicate',
				'definition' => 'A similar suggestion already exists!',
				'context' => '' 
		),
		728 => array (
				'term' => 'suggestion_delete_confirm',
				'definition' => 'Please confirm Suggestion deletion',
				'context' => '' 
		),
		729 => array (
				'term' => 'suggestion_validate_confirm',
				'definition' => 'Please confirm Suggestion validation',
				'context' => '' 
		),
		730 => array (
				'term' => 'suggestion_validate',
				'definition' => 'You have decided to add this Suggestion to the Items list ... please confirm.',
				'context' => '' 
		),
		731 => array (
				'term' => 'suggestion_error_cannot_add',
				'definition' => 'ERROR - The suggestion could not be added as an Item!',
				'context' => '' 
		),
		732 => array (
				'term' => 'suggestion_is_duplicate',
				'definition' => 'CAUTION: this suggestion has a similar Item (with equal Label and Folder). If you click on ADD button, this Item will be updated with data from this Suggestion.',
				'context' => '' 
		),
		733 => array (
				'term' => 'suggestion_menu',
				'definition' => 'Suggestions',
				'context' => '' 
		),
		734 => array (
				'term' => 'settings_suggestion',
				'definition' => 'Enable item suggestion for Read-Only users',
				'context' => '' 
		),
		735 => array (
				'term' => 'settings_suggestion_tip',
				'definition' => 'Item suggestion permits the Read-Only users to propose new items or items modification. Those suggestions will be validated by Administrator or Manager users.',
				'context' => '' 
		),
		736 => array (
				'term' => 'imported_via_api',
				'definition' => 'API',
				'context' => '' 
		),
		737 => array (
				'term' => 'settings_ldap_bind_dn',
				'definition' => 'Ldap Bind Dn',
				'context' => '' 
		),
		738 => array (
				'term' => 'settings_ldap_bind_passwd',
				'definition' => 'Ldap Bind Passwd',
				'context' => '' 
		),
		739 => array (
				'term' => 'settings_ldap_search_base',
				'definition' => 'Ldap Search Base',
				'context' => '' 
		),
		740 => array (
				'term' => 'settings_ldap_bind_dn_tip',
				'definition' => 'A Bind dn which can bind and search users in the tree',
				'context' => '' 
		),
		741 => array (
				'term' => 'settings_ldap_bind_passwd_tip',
				'definition' => 'Password for the bind dn which can bind and search users in the tree',
				'context' => '' 
		),
		742 => array (
				'term' => 'settings_ldap_search_base_tip',
				'definition' => 'Search root dn for searches on the tree',
				'context' => '' 
		),
		743 => array (
				'term' => 'old_saltkey',
				'definition' => 'Old SALT key',
				'context' => '' 
		),
		744 => array (
				'term' => 'define_old_saltkey',
				'definition' => 'I want to specify the old SALT Key to use (optional)',
				'context' => '' 
		),
		745 => array (
				'term' => 'admin_email_server_url_tip',
				'definition' => 'Customize the URL to be used in links present in emails if you don\'t want the by-default one used.',
				'context' => '' 
		),
		746 => array (
				'term' => 'admin_email_server_url',
				'definition' => 'Server URL for links in emails',
				'context' => '' 
		),
		747 => array (
				'term' => 'generated_pw',
				'definition' => 'Generated password',
				'context' => '' 
		),
		748 => array (
				'term' => 'enable_email_notification_on_user_pw_change',
				'definition' => 'Send an email to User when his password has been changed',
				'context' => '' 
		),
		749 => array (
				'term' => 'settings_otv_expiration_period',
				'definition' => 'Delay before expiration of one time view (OTV) shared items (in days)',
				'context' => '' 
		),
		750 => array (
				'term' => 'change_right_access',
				'definition' => 'Define the access rights',
				'context' => '' 
		),
		751 => array (
				'term' => 'write',
				'definition' => 'Write',
				'context' => '' 
		),
		752 => array (
				'term' => 'read',
				'definition' => 'Read',
				'context' => '' 
		),
		753 => array (
				'term' => 'no_access',
				'definition' => 'No Access',
				'context' => '' 
		),
		754 => array (
				'term' => 'right_types_label',
				'definition' => 'Select the type of access on this folder for the selected group of users',
				'context' => '' 
		),
		755 => array (
				'term' => 'groups',
				'definition' => 'Folders',
				'context' => '' 
		),
		756 => array (
				'term' => 'duplicate',
				'definition' => 'Duplicate',
				'context' => '' 
		),
		757 => array (
				'term' => 'duplicate_title_in_same_folder',
				'definition' => 'A similar Item name exists in current Folder! Duplicates are not allowed!',
				'context' => '' 
		),
		758 => array (
				'term' => 'duplicate_item_in_folder',
				'definition' => 'Allow items with similar label in a common folder',
				'context' => '' 
		),
		759 => array (
				'term' => 'find_message',
				'definition' => '<i class="fa fa-info-circle"></i> %X% objects found',
				'context' => '' 
		),
		760 => array (
				'term' => 'settings_roles_allowed_to_print',
				'definition' => 'Define the roles allowed to print out the items',
				'context' => '' 
		),
		761 => array (
				'term' => 'settings_roles_allowed_to_print_tip',
				'definition' => 'The selected roles will be allowed to print out Items in a file.',
				'context' => '' 
		),
		762 => array (
				'term' => 'user_profile_dialogbox_menu',
				'definition' => 'Your Teampass informations',
				'context' => '' 
		),
		763 => array (
				'term' => 'admin_email_security',
				'definition' => 'SMTP security',
				'context' => '' 
		),
		764 => array (
				'term' => 'alert_page_will_reload',
				'definition' => 'The page will now be reloaded',
				'context' => '' 
		),
		765 => array (
				'term' => 'csv_import_items_selection',
				'definition' => 'Select the items to import',
				'context' => '' 
		),
		766 => array (
				'term' => 'csv_import_options',
				'definition' => 'Select import options',
				'context' => '' 
		),
		767 => array (
				'term' => 'file_protection_password',
				'definition' => 'Define file password',
				'context' => '' 
		),
		768 => array (
				'term' => 'button_export_file',
				'definition' => 'Export items',
				'context' => '' 
		),
		769 => array (
				'term' => 'error_export_format_not_selected',
				'definition' => 'A format for export file is required',
				'context' => '' 
		),
		770 => array (
				'term' => 'select_file_format',
				'definition' => 'Select file format',
				'context' => '' 
		),
		771 => array (
				'term' => 'button_offline_generate',
				'definition' => 'Generate Offline mode file',
				'context' => '' 
		),
		772 => array (
				'term' => 'upload_new_avatar',
				'definition' => 'Select avatar PNG file',
				'context' => '' 
		) 
);
?>