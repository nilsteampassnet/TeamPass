<?php
//JAPANESE
if (!isset($_SESSION['settings']['cpassman_url'])) {
	$TeamPass_url = '';
}else{
	$TeamPass_url = $_SESSION['settings']['cpassman_url'];
}


$txt['account_is_locked'] = "このアカウントはロックされています。";

$txt['activity'] = "Activity";
$txt['add_button'] = "Add";

$txt['add_new_group'] = "新しいフォルダを追加する";
$txt['add_role_tip'] = "Add a new role.";

$txt['admin'] = "Administration";

$txt['admin_action'] = "確認";

$txt['admin_actions_title'] = "メンテナンスアクション";

$txt['admin_action_change_salt_key'] = "Change the main SALT Key";
$txt['admin_action_change_salt_key_tip'] = "Before changing the SALT key, please be sure to do a full backup of the database, and to put the tool in maintenance in order to avoid any users being logged.";
$txt['admin_action_check_pf'] = "Actualize Personal Folders for all users (creates them if not existing)";

$txt['admin_action_db_backup'] = "データベースのバックアップを作成する。";

$txt['admin_action_db_backup_key_tip'] = "入力した暗号キーは忘れないようにどこかに記録してください。リストア時に求められます。(暗号化しない場合は空のまま実行してください。)";

$txt['admin_action_db_backup_start_tip'] = "スタート";

$txt['admin_action_db_backup_tip'] = "データベースのバックアップファイルを作成し、後でリストアすることができます。";

$txt['admin_action_db_clean_items'] = "データベースに残った削除済みアイテムを削除する。";

$txt['admin_action_db_clean_items_result'] = "個のアイテムが削除されました。";

$txt['admin_action_db_clean_items_tip'] = "フォルダが削除された際に、そのフォルダに関連付けられていたアイテムとログはデータベース上にのこっていますので、これらを削除します。実行前にバックアップを作成することをお勧めします。";

$txt['admin_action_db_optimize'] = "データベースを最適化する。";

$txt['admin_action_db_restore'] = "データベースをリストアする。";

$txt['admin_action_db_restore_key'] = "暗号化キーを入力してください。";

$txt['admin_action_db_restore_tip'] = "バックアップ機能で作成したSQLバックアップファイルを使用し、リストアします";

$txt['admin_action_purge_old_files'] = "古いキャッシュファイルを削除";

$txt['admin_action_purge_old_files_result'] = "files have been deleted.";

$txt['admin_action_purge_old_files_tip'] = "7日以上前の一時ファイルを削除します。";

$txt['admin_action_reload_cache_table'] = "キャッシュテーブルを再読み込みする";

$txt['admin_action_reload_cache_table_tip'] = "This permits to reload the full content of table Cache. Can be usefull to be done sometimes.";

$txt['admin_backups'] = "Backups";
$txt['admin_error_no_complexity'] = "(<a href='index.php?page=manage_groups'>Define?</a>)";
$txt['admin_error_no_visibility'] = "No one can see this item. (<a href='index.php?page=manage_roles'>Customize roles</a>)";
$txt['admin_functions'] = "ロール管理";

$txt['admin_groups'] = "フォルダ管理";

$txt['admin_help'] = "ヘルプ";

$txt['admin_info'] = "Some information concerning the tool";

$txt['admin_info_loading'] = "Loading data ... please wait";

$txt['admin_ldap_configuration'] = "LDAP認証設定";

$txt['admin_ldap_menu'] = "LDAP options";

$txt['admin_main'] = "インフォメーション";

$txt['admin_misc_cpassman_dir'] = "TeamPassへのフルパス";

$txt['admin_misc_cpassman_url'] = "TeamPassへのフルURL";

$txt['admin_misc_custom_login_text'] = "Custom Login text";
$txt['admin_misc_custom_logo'] = "Full url to Custom Login Logo";
$txt['admin_misc_favicon'] = "faviconファイルへのフルURL";

$txt['admin_misc_title'] = "カスタム設定";

$txt['admin_new1'] = "<i><u>14FEB2012:</i></u><br>Administrator profile is no more allowed to see items. This profile is now only an Administrative account.<br />See <a href='http://www.teampass.net/how-to-handle-changes-on-administrator-profile' target='_blank'>TeamPass.net page</a> concerning the way to handle this change.";
$txt['admin_one_shot_backup'] = "One shot backup and restore";
$txt['admin_script_backups'] = "Settings for Backups script";
$txt['admin_script_backups_tip'] = "For more security, it is recommended to parameter a scheduled backup of the database.<br />Use your server to schedule a daily cron task by calling the file 'script.backup.php' in 'backups' folder.<br />You first need to set the 2 first paramteres and SAVE them.";
$txt['admin_script_backup_decrypt'] = "Name of the file you want to decrypt";
$txt['admin_script_backup_decrypt_tip'] = "In order to decrypt a backup file, just indicate the name of the backup file (no extension and no path).<br />The file will be decrypted in the same folder as the backup files are.";
$txt['admin_script_backup_encryption'] = "Encryption key (optional)";
$txt['admin_script_backup_encryption_tip'] = "If set, this key will be used to encrypted your file";
$txt['admin_script_backup_filename'] = "Backup file name";
$txt['admin_script_backup_filename_tip'] = "File name you want for your backups file";
$txt['admin_script_backup_path'] = "Path where backups have to be stored";
$txt['admin_script_backup_path_tip'] = "In what folder the backup files have to be stored";
$txt['admin_settings'] = "設定";

$txt['admin_settings_title'] = "TeamPass設定";

$txt['admin_setting_activate_expiration'] = "Enable passwords expiration";

$txt['admin_setting_activate_expiration_tip'] = "When enabled, items expired will not be displayed to users.";

$txt['admin_users'] = "ユーザー管理";

$txt['admin_views'] = "Views";

$txt['alert_message_done'] = "Done!";

$txt['alert_message_personal_sk_missing'] = "You must enter your personal saltkey!";

$txt['all'] = "all";

$txt['anyone_can_modify'] = "Allow this item to be modified by anyone that can access it";

$txt['associated_role'] = "What role to associate this folder to :";

$txt['associate_kb_to_items'] = "Select the items to associate to this KB";

$txt['assoc_authorized_groups'] = "Allowed Associated Folders";

$txt['assoc_forbidden_groups'] = "Forbidden Associated Folders";

$txt['at'] = "at";

$txt['at_add_file'] = "File added";

$txt['at_category'] = "フォルダ";

$txt['at_copy'] = "Copy created";
$txt['at_copy'] = "Copy done";
$txt['at_creation'] = "Creation";

$txt['at_delete'] = "Deletion";

$txt['at_del_file'] = "File deleted";

$txt['at_description'] = "Description.";

$txt['at_file'] = "File";

$txt['at_import'] = "Importation";

$txt['at_label'] = "ラベル";

$txt['at_login'] = "ログイン";

$txt['at_modification'] = "Modification";

$txt['at_moved'] = "Moved";
$txt['at_personnel'] = "Personal";

$txt['at_pw'] = "Password changed.";

$txt['at_restored'] = "Restored";

$txt['at_restriction'] = "Restriction";
$txt['at_shown'] = "Accessed";
$txt['at_url'] = "URL";

$txt['auteur'] = "Author";

$txt['author'] = "Author";

$txt['authorized_groups'] = "許可されたフォルダ";

$txt['auth_creation_without_complexity'] = "必要とされるパスワードの強度を無視したアイテムの作成を許可する。";

$txt['auth_modification_without_complexity'] = "必要とされるパスワードの強度を無視したアイテムの修正を許可する。";

$txt['auto_create_folder_role'] = "Create folder and role for ";

$txt['block_admin_info'] = "Administrators Info";
$txt['block_last_created'] = "Last created";

$txt['bugs_page'] = "If you discover a bug, you can directly post it in <a href='https://github.com/nilsteampassnet/TeamPass/issues' target='_blank'><u>Bugs Forum</u></a>.";
$txt['by'] = "by";

$txt['cancel'] = "キャンセル";

$txt['cancel_button'] = "キャンセル";

$txt['can_create_root_folder'] = "Can create a folder at root level";

$txt['changelog'] = "Latest news";

$txt['change_authorized_groups'] = "Change authorized folders";

$txt['change_forbidden_groups'] = "Change forbidden folders";

$txt['change_function'] = "Change roles";

$txt['change_group_autgroups_info'] = "Select the authorized folders this Role can see and use";

$txt['change_group_autgroups_title'] = "Customize the authorized folders";

$txt['change_group_forgroups_info'] = "Select the forbidden folders this Role can't see and use";
$txt['change_group_forgroups_title'] = "Customize the forbidden folders";

$txt['change_user_autgroups_info'] = "Select the authorized folders this account can see and use";

$txt['change_user_autgroups_title'] = "Customize the authorized folders";

$txt['change_user_forgroups_info'] = "Select the forbidden folders this account can't see nor use";
$txt['change_user_forgroups_title'] = "Customize the forbidden folders";

$txt['change_user_functions_info'] = "Select the functions associated to this account";

$txt['change_user_functions_title'] = "Customize associated functions";

$txt['check_all_text'] = "Check all";

$txt['close'] = "Close";

$txt['complexity'] = "パスワードの強度";

$txt['complex_asked'] = "必要なパスワードの強度";

$txt['complex_asked'] = "Required complexity";
$txt['complex_level0'] = "とても弱い";

$txt['complex_level1'] = "弱い";

$txt['complex_level2'] = "普通";

$txt['complex_level3'] = "強い";

$txt['complex_level4'] = "とても強い";

$txt['complex_level5'] = "さらに強い";

$txt['complex_level6'] = "最強";

$txt['confirm'] = "パスワードを再度入力";

$txt['confirm_delete_group'] = "You have decided to delete this Folder and all included Items ... are you sure?";

$txt['confirm_deletion'] = "本当に削除してもよろしいですか?";

$txt['confirm_del_account'] = "You have decided to delete this Account. Are you sure?";

$txt['confirm_del_from_fav'] = "Please confirm deletion from Favourites";

$txt['confirm_del_role'] = "次のロールを削除します、よろしいですか？";

$txt['confirm_edit_role'] = "次のロールの名前を入力してください。:";

$txt['confirm_lock_account'] = "You have decided to LOCK this Account. Are you sure?";
$txt['connection'] = "Connection";

$txt['connections'] = "接続";

$txt['copy'] = "コピー";

$txt['copy_to_clipboard_small_icons'] = "Enable copy to clipboard small icons in items page";

$txt['copy_to_clipboard_small_icons_tip'] = "<span style='font-size:11px;max-width:300px;'>This could help preventing memory usage if users have no recent computer.<br /> Indeed, the clipboard is not loaded with items informations. But no quick copy of password and login is possible.</span>
";
$txt['creation_date'] = "作成日時";

$txt['csv_import_button_text'] = "CSVファイルを参照する";

$txt['date'] = "日時";

$txt['date'] = "Date";
$txt['date_format'] = "日付フォーマット";

$txt['days'] = "days";

$txt['definition'] = "定義";

$txt['delete'] = "Delete";

$txt['deletion'] = "削除ログ";

$txt['deletion_title'] = "削除したアイテムリスト";

$txt['del_button'] = "削除";

$txt['del_function'] = "ロールを削除";

$txt['del_group'] = "フォルダを削除";

$txt['description'] = "説明";

$txt['description'] = "Description";
$txt['disconnect'] = "ログアウト";

$txt['disconnection'] = "Disconnection";

$txt['div_dialog_message_title'] = "Information";

$txt['done'] = "Done";

$txt['drag_drop_helper'] = "Drag and drop item";

$txt['duplicate_folder'] = "Authorize to have several folders with the same name.";

$txt['duplicate_item'] = "Authorize to have several items with the same name.";

$txt['email'] = "メールアドレス";

$txt['email_altbody_1'] = "Item";

$txt['email_altbody_2'] = "has been created.";

$txt['email_announce'] = "このアイテムをメールで通知";

$txt['email_body1'] = "Hi,<br><br>Item '";
$txt['email_body2'] = "has been created.<br /><br />You may view it by clicking <a href='";
$txt['email_body3'] = "'>HERE</a><br /><br />Regards.";
$txt['email_body_on_user_login'] = "Hello,<br><br>User #tp_user# has get connected to TeamPass the #tp_date# at #tp_time#.<br><br>Regards.";
$txt['email_change'] = "アカウントのEメールを変更";

$txt['email_changed'] = "Email changed!";

$txt['email_new_user_mail'] = "Hello,<br><br>An administrator has created your account for TeampPass.<br>You can use the next credentials for being logged:<br>- Login: #tp_login#<br>- Password: #tp_pw#<br><br>Click the <a href='#tp_link#'>LINK</a> to access.<br><br>Best regards.";
$txt['email_request_access_mail'] = "Hello #tp_item_author#,<br><br>User #tp_user# has required an access to '#tp_item#'.<br><br>Be sure of the rights of this user before changing the restriction to the Item.<br><br>Regards.";
$txt['email_request_access_subject'] = "[TeamPass] Request an access to item";
$txt['email_select'] = "Select persons to inform";

$txt['email_subject'] = "Creating a new Item in Passwords Manager";

$txt['email_subject_new_user'] = "[TeamPass] Your account creation";

$txt['email_subject_new_user'] = "[TeamPass] Your new account";
$txt['email_subject_on_user_login'] = "[TeamPass] A user has get connected";
$txt['email_text_new_user'] = "Hi,<br /><br />Your account has been created in TeamPass.<br />You can now access $TeamPass_url using the next credentials:<br />";

$txt['enable_favourites'] = "Enable the Users to store Favourites";

$txt['enable_personal_folder'] = "Enable Personal folder";

$txt['enable_personal_folder_feature'] = "Enable Personal folder feature";

$txt['enable_send_email_on_user_login'] = "Send an email to Admins on User log in";
$txt['enable_user_can_create_folders'] = "Users are allowed to manage folders in allowed parent folders";

$txt['encrypt_key'] = "暗号キー";

$txt['errors'] = "エラー";

$txt['error_complex_not_enought'] = "Password complexity is not fulfilled!";

$txt['error_confirm'] = "Password confirmation is not correct!";

$txt['error_cpassman_dir'] = "No path for TeamPass is set. Please select 'TeamPass settings' tab in Admin Settings page.";
$txt['error_cpassman_url'] = "No URL for TeamPass is set. Please select 'TeamPass settings' tab in Admin Settings page.";
$txt['error_empty_data'] = "No data to proceed!";
$txt['error_fields_2'] = "The 2 fields are mandatory!";

$txt['error_group'] = "A folder is mandatory!";

$txt['error_group_complex'] = "The Folder must have a minimum required passwords complexity level!";

$txt['error_group_exist'] = "This folder already exists!";

$txt['error_group_label'] = "The Folder must be named!";

$txt['error_html_codes'] = "Some text contains HTML codes! This is not allowed.";

$txt['error_item_exists'] = "This Item already exists!";

$txt['error_label'] = "A label is mandatory!";

$txt['error_must_enter_all_fields'] = "You must fill in each fields!";

$txt['error_mysql'] = "MySQL Error!";

$txt['error_not_allowed_to'] = "You are not allowed to do that!";
$txt['error_not_authorized'] = "このページを閲覧する権限がありません。";
$txt['error_not_exists'] = "ページが存在しません。";
$txt['error_no_folders'] = "まずはフォルダを作成してください。";

$txt['error_no_password'] = "You need to enter your password!";

$txt['error_no_roles'] = "ロールも作成し、フォルダに関連付けをしてください。";

$txt['error_no_selected_folder'] = "You need to select a Folder";
$txt['error_password_confirmation'] = "Passwords should be the same";

$txt['error_pw'] = "A password is mandatory!";

$txt['error_pw_too_long'] = "Password is too long! maximum characters is 40.";
$txt['error_renawal_period_not_integer'] = "Renewal period should be expressed in months!";

$txt['error_salt'] = "<b>The SALT KEY is too long! Please don't use the tool until an Admin has modified the salt key.</b> In settings.php file, SALT should not be longer than 32 characters.";
$txt['error_tags'] = "No punctuation characters allowed in TAGS! Only space.";

$txt['error_user_exists'] = "User already exists";

$txt['expiration_date'] = "有効期限切れ日時";

$txt['expir_one_month'] = "1ヶ月";

$txt['expir_one_year'] = "1年";

$txt['expir_six_months'] = "6ヶ月";

$txt['expir_today'] = "今日";

$txt['files_&_images'] = "ファイル&amp; 画像";

$txt['find'] = "検索";

$txt['find_text'] = "Your search";

$txt['folders'] = "フォルダ";

$txt['forbidden_groups'] = "禁止されたフォルダ";

$txt['forgot_my_pw'] = "パスワードを忘れた";

$txt['forgot_my_pw_email_sent'] = "Email has been sent";

$txt['forgot_my_pw_error_email_not_exist'] = "This email doesn't exist!";
$txt['forgot_my_pw_text'] = "あなたのパスワードがアカウントに紐付いたメールアドレスに送信されます。";

$txt['forgot_pw_email_altbody_1'] = "Hi, Your identification credentials for TeamPass are:";

$txt['forgot_pw_email_body'] = "Hi,<br /><br />Your new password for TeamPass is :";

$txt['forgot_pw_email_body'] = "Hi,<br /><br />Your new password for TeamPass is :";
$txt['forgot_pw_email_body_1'] = "Hi, <br /><br />Your identification credentials for TeamPass are:<br /><br />";

$txt['forgot_pw_email_subject'] = "TeamPass - Your password";

$txt['forgot_pw_email_subject_confirm'] = "[TeamPass] Your password step 2";

$txt['functions'] = "ロール";

$txt['function_alarm_no_group'] = "This role is not associated to any Folder!";

$txt['generate_pdf'] = "PDFファイルを生成";

$txt['generation_options'] = "Generation options";

$txt['gestionnaire'] = "マネージャー";

$txt['give_function_tip'] = "新しいロールを追加する";

$txt['give_function_title'] = "新しいロールを追加する";

$txt['give_new_email'] = "Please enter new email for";

$txt['give_new_login'] = "Please select the account";

$txt['give_new_pw'] = "Please indicate the new password for";

$txt['god'] = "神";

$txt['group'] = "フォルダ";

$txt['group_parent'] = "親階層フォルダ";

$txt['group_pw_duration'] = "更新期間";

$txt['group_pw_duration_tip'] = "月単位。0を指定すると更新なし";

$txt['group_select'] = "Select folder";

$txt['group_title'] = "フォルダラベル";

$txt['history'] = "履歴";

$txt['home'] = "ホーム";

$txt['home_personal_menu'] = "Personal Actions";

$txt['home_personal_saltkey'] = "Your personal SALTKey";

$txt['home_personal_saltkey_button'] = "Store it!";

$txt['home_personal_saltkey_info'] = "You should enter your personal saltkey if you need to use your personal items.";

$txt['home_personal_saltkey_label'] = "Enter your personal salt key";

$txt['importing_details'] = "List of details";

$txt['importing_folders'] = "Importing folders";

$txt['importing_items'] = "Importing items";

$txt['import_button'] = "Import";

$txt['import_csv_anyone_can_modify_in_role_txt'] = "インポートしたアイテムすべてに\"同じ権限ならだれでも修正できる\" 権限を設定する。";

$txt['import_csv_anyone_can_modify_txt'] = "インポートしたアイテムすべてに\"誰でも修正できる\"権限を設定する。";

$txt['import_csv_dialog_info'] = "インフォメーション: インポートはCSV形式のファイルを使用して行われます。特に、KeePasからエクスポートされたファイルを想定した構成になってます。<br />もし他のツールを使用してファイルを生成した場合は、CSVの構造が、`アカウント`,`ログイン名`,`パスワード`,`ウェブサイト`,`コメント`となっているかを確認してください。";

$txt['import_csv_menu_title'] = "ファイルからアイテムをインポート(CSV/KeePass XML)";

$txt['import_error_no_file'] = "You must select a file!";

$txt['import_error_no_read_possible'] = "Can't read the file!";
$txt['import_error_no_read_possible_kp'] = "Can't read the file! It must be a KeePass file.";
$txt['import_keepass_dialog_info'] = "KeepPassのエキスポート機能で作成したXMLファイルを使用する場合にこの機能を使用します。KeePassファイルでのみ動作します。ツリー構造の同階層レベルにフォルダやエレメントが既にある場合は、インポートされません。";

$txt['import_keepass_to_folder'] = "インポート先フォルダを選択";

$txt['import_kp_finished'] = "KeePassからのインポートが終了しました。<br />デフォルトで新しくインポートされたフォルダに要求されるパスワードの複雑さは、”中”に設定されます。。必要に応じて設定を変更してください。";

$txt['import_to_folder'] = "Tick the items you want to import to folder:";

$txt['index_add_one_hour'] = "セッションを1時間延長する";

$txt['index_alarm'] = "ALARM!!!";

$txt['index_bas_pw'] = "アカウント又はパスワードが正しくありません。";

$txt['index_change_pw'] = "Your password must be changed!";

$txt['index_change_pw'] = "Change your password";
$txt['index_change_pw_button'] = "Change";

$txt['index_change_pw_confirmation'] = "パスワードを再度入力";

$txt['index_expiration_in'] = "セッション残り時間";

$txt['index_get_identified'] = "ログインしてください";

$txt['index_identify_button'] = "ログイン";

$txt['index_identify_you'] = "Please identify yourself";

$txt['index_last_pw_change'] = "パスワード変更日時";

$txt['index_last_seen'] = "最終アクセス時刻";

$txt['index_login'] = "アカウント";

$txt['index_maintenance_mode'] = "メンテナンスモードが有効になってます。管理者のみログインすることができます。";

$txt['index_maintenance_mode_admin'] = "メンテナンスモードが有効になってます。 一般ユーザーは現在TeamPassにアクセスできません。";

$txt['index_new_pw'] = "新しいパスワード";

$txt['index_password'] = "パスワード";

$txt['index_pw_error_identical'] = "The passwords have to be identical!";

$txt['index_pw_expiration'] = "Actual password expiration in";

$txt['index_pw_level_txt'] = "Complexity";

$txt['index_refresh_page'] = "Refresh page";

$txt['index_session_duration'] = "セッション期間";

$txt['index_session_ending'] = "Your session will end in less than 1 minute.";

$txt['index_session_expired'] = "ログアウトしました";

$txt['index_welcome'] = "ようこそ";

$txt['info'] = "Information";

$txt['info_click_to_edit'] = "この値を編集するにはセルをクリック";

$txt['is_admin'] = "Is Admin";

$txt['is_manager'] = "Is Manager";

$txt['is_read_only'] = "Is Read Only";
$txt['items_browser_title'] = "フォルダ名";

$txt['item_copy_to_folder'] = "Please select a folder in which the item has to be copied.";
$txt['item_menu_add_elem'] = "アイテムを追加";

$txt['item_menu_add_rep'] = "フォルダを追加";

$txt['item_menu_add_to_fav'] = "お気に入りに追加";

$txt['item_menu_collab_disable'] = "Edition is not allowed";

$txt['item_menu_collab_enable'] = "Edition is allowed";

$txt['item_menu_copy_elem'] = "アイテムをコピー";

$txt['item_menu_copy_login'] = "Copy login";

$txt['item_menu_copy_pw'] = "Copy password";

$txt['item_menu_del_elem'] = "アイテムを削除";

$txt['item_menu_del_from_fav'] = "Delete from Favourites";

$txt['item_menu_del_rep'] = "フォルダを削除";

$txt['item_menu_edi_elem'] = "アイテムを編集";

$txt['item_menu_edi_rep'] = "フォルダを編集";

$txt['item_menu_find'] = "検索";

$txt['item_menu_mask_pw'] = "Mask password";

$txt['item_menu_refresh'] = "更新";

$txt['kbs'] = "KBs";
$txt['kb_menu'] = "Knowledge Base";

$txt['keepass_import_button_text'] = "Browse XML file";

$txt['label'] = "ラベル";

$txt['last_items_icon_title'] = "最後に見たアイテムを 表示/隠す";

$txt['last_items_title'] = "最後に見たアイテム";

$txt['ldap_extension_not_loaded'] = "The LDAP extension is not activated on the server.";
$txt['level'] = "レベル";

$txt['link_copy'] = "このアイテムのURLをクリップボードへコピー";

$txt['link_is_copied'] = "このアイテムへのリンクをクリップボードにコピーしました。";

$txt['login'] = "ログイン (任意)";

$txt['login_attempts_on'] = " login attempts on ";

$txt['login_copied_clipboard'] = "Login copied in clipboard";

$txt['login_copy'] = "アカウントをクリップボードをコピー";

$txt['logs'] = "ログ";

$txt['logs_1'] = "Generate the log file for the passwords renewal done the";

$txt['logs_passwords'] = "パスワード生成ログ";

$txt['maj'] = "Uppercase letters";

$txt['mask_pw'] = "パスワードを表示する/隠す";

$txt['max_last_items'] = "Maximum number of last items seen by user (default is 10)";

$txt['menu_title_new_personal_saltkey'] = "Changing your Personal Saltkey";
$txt['minutes'] = "分";

$txt['modify_button'] = "Modify";

$txt['my_favourites'] = "お気に入り";

$txt['name'] = "Name";

$txt['nb_false_login_attempts'] = "アカウントがロックされるログイン失敗回数(0は無制限)";

$txt['nb_folders'] = "フォルダの数";

$txt['nb_items'] = "アイテムの数";

$txt['nb_items_by_page'] = "Number of items by page";
$txt['nb_items_by_query'] = "Number of items to get at each query iterration";
$txt['nb_items_by_query_tip'] = "<span style='font-size:11px;max-width:300px;'>More items means more time to display the list.<br />Set to 'auto' to let the tool to adapt this number depending on the size screen of the user.<br />Set to 'max' to force to display the complet list in one time.<br />Set a number corresding to the number of items to get at each query iterration.</span>";
$txt['new_label'] = "新しいラベル";

$txt['new_role_title'] = "新しいロールタイトル";

$txt['new_saltkey'] = "New Saltkey";
$txt['new_saltkey_warning'] = "Please be sure to use the original SaltKey, otherwize the new encryption will be corrupted. Before doing any change, please test your actual SaltKey!";
$txt['new_saltkey_warning_lost'] = "You have lost your saltkey? What a pitty, this one can't be recovered, so please be sure before continuing.<br>By reseting your saltkey, all your previous personal items will be deleted!";
$txt['new_user_title'] = "Add a new user";

$txt['no'] = "No";

$txt['nom'] = "Name";

$txt['none'] = "None";

$txt['none_selected_text'] = "None selected";

$txt['not_allowed_to_see_pw'] = "You are not allowed to see that Item!";

$txt['not_allowed_to_see_pw_is_expired'] = "This item has expired!";

$txt['not_defined'] = "Not defined";

$txt['no_last_items'] = "No items seen";

$txt['no_previous_pw'] = "No previous password";
$txt['no_restriction'] = "No restriction";

$txt['numbers'] = "Numbers";

$txt['number_of_used_pw'] = "古いパスワードを再利用する際にユーザーが入力しなければならないパスワードの回数";

$txt['ok'] = "OK";

$txt['open_url_link'] = "Open in new page";
$txt['pages'] = "Pages";

$txt['pdf_del_date'] = "PDF generated the";

$txt['pdf_del_title'] = "パスワードの更新フォロー";

$txt['pdf_download'] = "Download file";

$txt['personal_folder'] = "Personal folder";

$txt['personal_saltkey_change_button'] = "Change it!";
$txt['personal_saltkey_lost'] = "I've lost it";
$txt['personal_salt_key'] = "Your personal salt key";

$txt['personal_salt_key_empty'] = "Personal salt key has not been entered!";

$txt['personal_salt_key_info'] = "This salt key will be used to encrypt and decrypt your passwords.<br />It is not stored in database, you are the only person who knows it.<br />So don't loose it!";
$txt['please_update'] = "Please update the tool!";

$txt['previous_pw'] = "Previous passwords used:";
$txt['print'] = "Print";

$txt['print_out_menu_title'] = "Print out a listing of your items";

$txt['print_out_pdf_title'] = "TeamPass - List of exported Items";

$txt['print_out_warning'] = "All passwords and all confidential data will be written in this file without any encryption! By writing the file containing unencrypted items/passwords, you are accepting the full responsibility for further protection of this list!";

$txt['pw'] = "パスワード";

$txt['pw_change'] = "アカウントのパスワード変更";

$txt['pw_changed'] = "パスワードが変更されました!";

$txt['pw_copied_clipboard'] = "Password copied to clipboard";

$txt['pw_copy_clipboard'] = "パスワードをクリップボードにコピー";

$txt['pw_encryption_error'] = "Error encryption of the password!";
$txt['pw_generate'] = "生成";

$txt['pw_is_expired_-_update_it'] = "This item has expired! You need to change its password.";

$txt['pw_life_duration'] = "ユーザーのパスワード有効期限(日数指定, 0は無制限)";

$txt['pw_recovery_asked'] = "You have asked for a password recovery";

$txt['pw_recovery_button'] = "Send me my new password";

$txt['pw_recovery_info'] = "By clicking on the next button, you will receive an email that contains the new password for your account.";

$txt['pw_used'] = "This password has already been used!";

$txt['readme_open'] = "Open full readme file";

$txt['read_only_account'] = "Read Only";
$txt['refresh_matrix'] = "表をリフレッシュする";

$txt['renewal_menu'] = "Renewal follow-up";

$txt['renewal_needed_pdf_title'] = "List of Items that need to be renewed";

$txt['renewal_selection_text'] = "次の期間内に期限切れになるアイテムを表示:";

$txt['request_access_ot_item'] = "Request an access to author";
$txt['restore'] = "リストア";

$txt['restore'] = "Restore";
$txt['restricted_to'] = "利用者限定";

$txt['restricted_to_roles'] = "Allow to restrict items to Users and Roles";

$txt['rights_matrix'] = "Users rights matrix";

$txt['roles'] = "ロール";

$txt['role_cannot_modify_all_seen_items'] = "Set this role not allowed to modify all accessible items (normal setting)";

$txt['role_can_modify_all_seen_items'] = "Set this role allowed to modify all accessible items (not secure setting)";

$txt['root'] = "Root";

$txt['save_button'] = "保存";

$txt['secure'] = "Secure";

$txt['see_logs'] = "See Logs";
$txt['select'] = "select";

$txt['select_folders'] = "フォルダを選択";

$txt['select_language'] = "言語を選択";

$txt['send'] = "Send";

$txt['settings_anyone_can_modify'] = "Activate an option for each item that allows anyone to modify it";

$txt['settings_anyone_can_modify_tip'] = "<span style='font-size:11px;max-width:300px;'>When activated, this will add a checkbox in the item form that permits the creator to allow the modification of this item by anyone.</span>";
$txt['settings_default_language'] = "Define the Default Language";
$txt['settings_kb'] = "Enable Knowledge Base (beta)";

$txt['settings_kb_tip'] = "<span style='font-size:11px;max-width:300px;'>When activated, this will add a page where you can build your knowledge base.</span>";
$txt['settings_ldap_domain'] = "あなたのドメインのLDAPアカウントサフィックス";

$txt['settings_ldap_domain_controler'] = "ドメインコントローラーのLDAPアレイ";

$txt['settings_ldap_domain_controler_tip'] = "<span style='font-size:11px;max-width:300px;'>Specifiy multiple controllers if you would like the class to balance the LDAP queries amongst multiple servers.<br />You must delimit the domains by a comma ( , )!<br />By example: domain_1,domain_2,domain_3</span>";
$txt['settings_ldap_domain_dn'] = "あなたのドメインのLDAPベース dn";

$txt['settings_ldap_mode'] = "LDAPサーバーを使ったユーザー認証を有効にする。";

$txt['settings_ldap_mode_tip'] = "Enable only if you have an LDAP server and if you want to use it to authentify TeamPass users through it.";

$txt['settings_ldap_ssl'] = "SSL経由でLDAPを使用する(LDAPS)";

$txt['settings_ldap_tls'] = "TLS経由でLDAPを使用する";

$txt['settings_log_accessed'] = "Enable loggin who accessed the items";
$txt['settings_log_connections'] = "全てのユーザーのコネクションをDBにロギングする。";

$txt['settings_maintenance_mode'] = "メンテナンスモードに設定";

$txt['settings_maintenance_mode_tip'] = "このモードは管理者以外の全てのユーザーコネクションを拒絶します。";

$txt['settings_manager_edit'] = "Managers can edit and delete Items they are allowed to see";

$txt['settings_printing'] = "Enable printing items to PDF file";

$txt['settings_printing_tip'] = "When enabled, a button will be added to user's home page that will permit him/her to write a listing of items to a PDF file he/she can view. Notice that the listed passwords will be uncrypted.";
$txt['settings_restricted_to'] = "Enable Restricted To functionality on Items";
$txt['settings_richtext'] = "Enable richtext for item description";

$txt['settings_richtext_tip'] = "<span style='font-size:11px;max-width:300px;'>This will activate a richtext with BBCodes in description field.</span>";
$txt['settings_send_stats'] = "TeamPassの利用状況を伝えるために、月間統計情報を作者に送信する。";

$txt['settings_send_stats_tip'] = "These statistics are entirely anonymous!<br /><span style='font-size:10px;max-width:300px;'>Your IP is not sent, just the following data are transmitted: amount of Items, Folders, Users, TeamPass version, personal folders enabled, ldap enabled.<br />Many thanks if you enable those statistics. By this you help me further develop TeamPass.</span>";
$txt['settings_show_description'] = "Show Description in list of Items";
$txt['show'] = "Show";

$txt['show_help'] = "ヘルプを表示";

$txt['show_last_items'] = "Show last items block on main page";

$txt['size'] = "Size";

$txt['start_upload'] = "Start uploading files";

$txt['sub_group_of'] = "Dependent on";

$txt['support_page'] = "For any support, please use the <a href='https://github.com/nilsteampassnet/TeamPass/issues' target='_blank'><u>Forum</u></a>.";
$txt['symbols'] = "Symbols";

$txt['tags'] = "タグ";

$txt['thku'] = "Thank you for using TeamPass!";

$txt['timezone_selection'] = "タイムゾーン";

$txt['time_format'] = "時間フォーマット";

$txt['uncheck_all_text'] = "Uncheck all";

$txt['unlock_user'] = "User is locked. Do you want to unlock this account?";

$txt['update_needed_mode_admin'] = "It is recommended to update your TeamPass installation. Click <a href='install/upgrade.php'>HERE</a>";
$txt['uploaded_files'] = "Existing Files";

$txt['upload_button_text'] = "Browse";

$txt['upload_files'] = "Upload New Files";

$txt['url'] = "URL";

$txt['url_copied'] = "URLがコピーされました!";

$txt['used_pw'] = "使用されているパスワード";

$txt['user'] = "User";

$txt['users'] = "Users";

$txt['users_online'] = "users online";
$txt['user_action'] = "Action on a user";
$txt['user_alarm_no_function'] = "This user has no Roles!";

$txt['user_del'] = "アカウントを削除";

$txt['user_lock'] = "Lock user";
$txt['version'] = "Current version";

$txt['views_confirm_items_deletion'] = "Do you really want to delete the selected items from database?";

$txt['views_confirm_restoration'] = "Please confirm the restoration of this Item";

$txt['visibility'] = "Visibility";

$txt['warning_screen_height'] = "WARNING: screen height is not enough for displaying items list!";
$txt['yes'] = "Yes";

$txt['your_version'] = "Your version";

?>
