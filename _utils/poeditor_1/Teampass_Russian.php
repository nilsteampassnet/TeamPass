<?php 
$LANG = array (
  0 => 
  array (
    'term' => 'user_ga_code',
    'definition' => 'Отправить приложение Google Authenticator на email пользователю',
    'context' => '',
  ),
  1 => 
  array (
    'term' => 'send_ga_code',
    'definition' => 'Код Google Authenticator для пользователя',
    'context' => '',
  ),
  2 => 
  array (
    'term' => 'error_no_email',
    'definition' => 'У пользователя не задан email!',
    'context' => '',
  ),
  3 => 
  array (
    'term' => 'error_no_user',
    'definition' => 'Пользователь не найден!',
    'context' => '',
  ),
  4 => 
  array (
    'term' => 'email_ga_subject',
    'definition' => 'Ваш код  Google Authenticator для TeamPass',
    'context' => '',
  ),
  5 => 
  array (
    'term' => 'email_ga_text',
    'definition' => 'Здравствуйте,&lt;br&gt;&lt;br&gt;Пожалуйста, перейдите по &lt;a href=\'#link#\'&gt;ссылке&lt;/a&gt; и сфотографируйте страницу при помощи приложения Google Authenticator, чтобы получить учетные данные OTP для TeamPass',
    'context' => '',
  ),
  6 => 
  array (
    'term' => 'settings_attachments_encryption',
    'definition' => 'Включить шифрование вложений к элементам',
    'context' => '',
  ),
  7 => 
  array (
    'term' => 'settings_attachments_encryption_tip',
    'definition' => 'ЭТА НАСТРОЙКА МОЖЕТ ПОВРЕДИТЬ СУЩЕСТВУЮЩИЕ ВЛОЖЕНИЯ. Если настройка включена, то приложения к элементам хранятся на сервере зашифрованными с использованием ключа шифрования (Salt key) заданного в TeamPass. Это потребует больше ресурсов сервера. ВНИМАНИЕ: после изменения настройки необходимо запустить скрипт для конвертирования для уже созданных вложений. См. вкладку \'Скрипты\'.',
    'context' => '',
  ),
  8 => 
  array (
    'term' => 'admin_action_attachments_cryption',
    'definition' => 'Зашифровать или расшифровать вложения к элементам',
    'context' => '',
  ),
  9 => 
  array (
    'term' => 'admin_action_attachments_cryption_tip',
    'definition' => 'ВНИМАНИЕ: это действие можно выполнять ТОЛЬКО после изменения соответствующей настройки. На всякий случай заранее сделайте копию папки \'upload\' перед какими-либо действиями.',
    'context' => '',
  ),
  10 => 
  array (
    'term' => 'encrypt',
    'definition' => 'Зашифровать',
    'context' => '',
  ),
  11 => 
  array (
    'term' => 'decrypt',
    'definition' => 'Расшифровать',
    'context' => '',
  ),
  12 => 
  array (
    'term' => 'admin_ga_website_name',
    'definition' => 'Имя для Google Authenticator',
    'context' => '',
  ),
  13 => 
  array (
    'term' => 'admin_ga_website_name_tip',
    'definition' => 'Это имя используется для идентификационного кода в Google Authenticator.',
    'context' => '',
  ),
  14 => 
  array (
    'term' => 'admin_action_pw_prefix_correct',
    'definition' => 'Исправить префикс паролей',
    'context' => '',
  ),
  15 => 
  array (
    'term' => 'admin_action_pw_prefix_correct_tip',
    'definition' => 'Перед запуском этого скрипта убедитесь, что у Вас есть резервная копия БД. Этот скрипт обновляет префикс паролей. Используйте его только если у всех паролей появился странный префикс.',
    'context' => '',
  ),
  16 => 
  array (
    'term' => 'items_changed',
    'definition' => 'изменен(а).',
    'context' => '',
  ),
  17 => 
  array (
    'term' => 'ga_not_yet_synchronized',
    'definition' => 'Идентифицирован Google Authenticator',
    'context' => '',
  ),
  18 => 
  array (
    'term' => 'ga_scan_url',
    'definition' => 'Пожалуйста, отсканируйте этот код при помощи мобильного приложения Google Authenticator. Скопируйте из него идентификационный код.',
    'context' => '',
  ),
  19 => 
  array (
    'term' => 'ga_identification_code',
    'definition' => 'Идентификационный код',
    'context' => '',
  ),
  20 => 
  array (
    'term' => 'ga_enter_credentials',
    'definition' => 'Необходимо ввести учетные данные',
    'context' => '',
  ),
  21 => 
  array (
    'term' => 'ga_bad_code',
    'definition' => 'Неверный код Google Authenticator',
    'context' => '',
  ),
  22 => 
  array (
    'term' => 'settings_get_tp_info',
    'definition' => 'Автоматически загружать информацию о TeamPass на главную страницу',
    'context' => '',
  ),
  23 => 
  array (
    'term' => 'settings_get_tp_info_tip',
    'definition' => 'С этой настройкой на главной странице администратора отображается информация о последних версиях с сервера создателей TeamPass в Интернете.',
    'context' => '',
  ),
  24 => 
  array (
    'term' => 'at_field',
    'definition' => 'Поле',
    'context' => '',
  ),
  25 => 
  array (
    'term' => 'category_in_folders_title',
    'definition' => 'Связанные папки',
    'context' => '',
  ),
  26 => 
  array (
    'term' => 'category_in_folders',
    'definition' => 'Редактировать папки в этой Категории',
    'context' => '',
  ),
  27 => 
  array (
    'term' => 'select_folders_for_category',
    'definition' => 'Выберите папки для связи с Категорией',
    'context' => '',
  ),
  28 => 
  array (
    'term' => 'offline_mode_warning',
    'definition' => 'Автономный режим позволяет экспортировать элементы в HTML-файл, чтобы получать к ним доступ там, где нет связи с сервером TeamPass. Пароли шифруются ключом, который вы введете.',
    'context' => '',
  ),
  29 => 
  array (
    'term' => 'offline_menu_title',
    'definition' => 'Экспортировать элементы для автономного режима',
    'context' => '',
  ),
  30 => 
  array (
    'term' => 'settings_offline_mode',
    'definition' => 'Разрешить автономный режим',
    'context' => '',
  ),
  31 => 
  array (
    'term' => 'settings_offline_mode_tip',
    'definition' => 'Автономный режим позволяет экспортировать элементы в HTML-файл, чтобы получать к ним доступ там, где нет связи с сервером TeamPass. Пароли шифруются ключом, который вводит пользователь.',
    'context' => '',
  ),
  32 => 
  array (
    'term' => 'offline_mode_key_level',
    'definition' => 'Минимальная сложность ключа шифрования для автономного режима',
    'context' => '',
  ),
  33 => 
  array (
    'term' => 'categories',
    'definition' => 'Категории',
    'context' => '',
  ),
  34 => 
  array (
    'term' => 'new_category_label',
    'definition' => 'Создать категорию',
    'context' => '',
  ),
  35 => 
  array (
    'term' => 'no_category_defined',
    'definition' => 'Категории еще не созданы',
    'context' => '',
  ),
  36 => 
  array (
    'term' => 'confirm_deletion',
    'definition' => 'Вы уверены, что хотите удалить?',
    'context' => '',
  ),
  37 => 
  array (
    'term' => 'confirm_rename',
    'definition' => 'Подтверждаете переименование?',
    'context' => '',
  ),
  38 => 
  array (
    'term' => 'new_field_title',
    'definition' => 'Введите заголовок для нового поля',
    'context' => '',
  ),
  39 => 
  array (
    'term' => 'confirm_creation',
    'definition' => 'Подтверждаете создание?',
    'context' => '',
  ),
  40 => 
  array (
    'term' => 'confirm_moveto',
    'definition' => 'Подтверждаете перемещение поля?',
    'context' => '',
  ),
  41 => 
  array (
    'term' => 'for_selected_items',
    'definition' => 'Для выбранного элемента',
    'context' => '',
  ),
  42 => 
  array (
    'term' => 'move',
    'definition' => 'Переместить в',
    'context' => '',
  ),
  43 => 
  array (
    'term' => 'field_add_in_category',
    'definition' => 'Добавить новое поле',
    'context' => '',
  ),
  44 => 
  array (
    'term' => 'rename',
    'definition' => 'Переименовать',
    'context' => '',
  ),
  45 => 
  array (
    'term' => 'settings_item_extra_fields',
    'definition' => 'Разрешить добавлять к элементам дополнительные поля (см. Категории)',
    'context' => '',
  ),
  46 => 
  array (
    'term' => 'settings_item_extra_fields_tip',
    'definition' => 'Эта функция позволяет улучшить описание элементов путем добавления дополнительных полей, которыми можно управлять в Категориях. Все данные шифруются. На каждое поле требуется выполнить дополнительно примерно 5 SQL-запросов при обновлении элемента.',
    'context' => '',
  ),
  47 => 
  array (
    'term' => 'html',
    'definition' => 'html',
    'context' => '',
  ),
  48 => 
  array (
    'term' => 'more',
    'definition' => 'Еще',
    'context' => '',
  ),
  49 => 
  array (
    'term' => 'save_categories_position',
    'definition' => 'Сохранить сортировку категорий',
    'context' => '',
  ),
  50 => 
  array (
    'term' => 'reload_table',
    'definition' => 'Перечитать категории',
    'context' => '',
  ),
  51 => 
  array (
    'term' => 'settings_ldap_type',
    'definition' => 'Тип LDAP-сервера',
    'context' => '',
  ),
  52 => 
  array (
    'term' => 'use_md5_password_as_salt',
    'definition' => 'Использовать пароль пользователя в качестве личного ключа шифрования',
    'context' => '',
  ),
  53 => 
  array (
    'term' => 'server_time',
    'definition' => 'Время на сервере',
    'context' => '',
  ),
  54 => 
  array (
    'term' => 'settings_tree_counters',
    'definition' => 'Показывать больше счетчиков в дереве папок',
    'context' => '',
  ),
  55 => 
  array (
    'term' => 'settings_tree_counters_tip',
    'definition' => 'Показывает для каждой папки: число элементов в папке, число элементов в подпапках, число подпапок. Генерирует больше SQL-запросов.',
    'context' => '',
  ),
  56 => 
  array (
    'term' => 'settings_encryptClientServer',
    'definition' => 'Шифровать данные, передаваемые между клиентом и сервером',
    'context' => '',
  ),
  57 => 
  array (
    'term' => 'settings_encryptClientServer_tip',
    'definition' => 'AES-256 включено по умолчанию. Данная опция необходима, если не используется SSL-сертификат для шифрования обмена между клиентом и сервером. Если используется SSL или TeamPass работает во внутренней сети, то эту опцию можно отключить для ускорения отображения данных в Teampass. /!\\ Помните, что использование SSL-протокола является наиболее безопасным и защищенным вариантом.',
    'context' => '',
  ),
  58 => 
  array (
    'term' => 'error_group_noparent',
    'definition' => 'Не выбран родитель!',
    'context' => '',
  ),
  59 => 
  array (
    'term' => 'channel_encryption_no_iconv',
    'definition' => 'Не загружено расширение ICONV! Шифрование не может быть включено!',
    'context' => '',
  ),
  60 => 
  array (
    'term' => 'channel_encryption_no_bcmath',
    'definition' => 'Не загружено расширение BCMATH! Шифрование не может быть включено!',
    'context' => '',
  ),
  61 => 
  array (
    'term' => 'admin_action_check_pf',
    'definition' => 'Обновить личные папки для всех пользователей (создает, если их не существует)',
    'context' => '',
  ),
  62 => 
  array (
    'term' => 'admin_actions_title',
    'definition' => 'Скрипты',
    'context' => '',
  ),
  63 => 
  array (
    'term' => 'enable_personal_folder_feature_tip',
    'definition' => 'После включения данной опции, нужно вручную запустить скрипт, который создаст личные папки для существующих пользователей (скрипт создает папки только для тех, у кого их еще нет). Скрипт \'".$txt[\'admin_action_check_pf\']."\' находится на вкладке \'".$txt[\'admin_actions_title\']."\'',
    'context' => '',
  ),
  64 => 
  array (
    'term' => 'is_administrated_by_role',
    'definition' => 'Кто может управлять пользователем',
    'context' => '',
  ),
  65 => 
  array (
    'term' => 'administrators_only',
    'definition' => 'Только администраторы',
    'context' => '',
  ),
  66 => 
  array (
    'term' => 'managers_of',
    'definition' => 'Менеджеры роли',
    'context' => '',
  ),
  67 => 
  array (
    'term' => 'managed_by',
    'definition' => 'Управляется',
    'context' => '',
  ),
  68 => 
  array (
    'term' => 'admin_small',
    'definition' => 'Admin',
    'context' => '',
  ),
  69 => 
  array (
    'term' => 'setting_can_create_root_folder',
    'definition' => 'Разрешить создание папок на корневом уровне',
    'context' => '',
  ),
  70 => 
  array (
    'term' => 'settings_enable_sts',
    'definition' => 'Принудительно включить HTTPS Strict Transport Security -- Внимание: См. примечание.',
    'context' => '',
  ),
  71 => 
  array (
    'term' => 'settings_enable_sts_tip',
    'definition' => 'STS помогает предотвратить атаки SSL Man-in-the-Middle. Для использования этой опции Вам нужен действительный SSL сертификат. Если у Вас самоподписанный сертификат и Вы включите эту опцию, то TeamPass перестанет работать! В конфигурации Apache необходимо задать \'SSLOptions +ExportCertData\'.',
    'context' => '',
  ),
  72 => 
  array (
    'term' => 'channel_encryption_no_gmp',
    'definition' => 'Не загружено расширение GMP! Шифрование не может быть включено!',
    'context' => '',
  ),
  73 => 
  array (
    'term' => 'channel_encryption_no_openssl',
    'definition' => 'Не загружено расширение OPENSSL! Шифрование не может быть включено!',
    'context' => '',
  ),
  74 => 
  array (
    'term' => 'channel_encryption_no_file',
    'definition' => 'Не найден файл с ключами шифрования!&lt;br&gt;Пожалуйста, запустите процесс обновления.',
    'context' => '',
  ),
  75 => 
  array (
    'term' => 'admin_action_generate_encrypt_keys',
    'definition' => 'Создать новый набор ключей шифрования',
    'context' => '',
  ),
  76 => 
  array (
    'term' => 'admin_action_generate_encrypt_keys_tip',
    'definition' => 'Набор ключей шифрования очень важная часть системы безопасности TeamPass. Эти ключи используются для шифрования обмена между сервером и клиентом. Рекомендуется периодически по новой создавать набор ключей. Обратите внимание, что процесс может занимать до 1 минуты.',
    'context' => '',
  ),
  77 => 
  array (
    'term' => 'settings_anyone_can_modify_bydefault',
    'definition' => 'Включить по умолчанию \'&lt;b&gt;Разрешить редактирование для всех&lt;/b&gt;\'',
    'context' => '',
  ),
  78 => 
  array (
    'term' => 'channel_encryption_in_progress',
    'definition' => 'Шифрование канала...',
    'context' => '',
  ),
  79 => 
  array (
    'term' => 'channel_encryption_failed',
    'definition' => 'Аутентификация не прошла!',
    'context' => '',
  ),
  80 => 
  array (
    'term' => 'purge_log',
    'definition' => 'Очистить журнал с',
    'context' => '',
  ),
  81 => 
  array (
    'term' => 'to',
    'definition' => 'по',
    'context' => '',
  ),
  82 => 
  array (
    'term' => 'purge_now',
    'definition' => 'Очистить!',
    'context' => '',
  ),
  83 => 
  array (
    'term' => 'purge_done',
    'definition' => 'Очистка закончена! Число удаленных записей: ',
    'context' => '',
  ),
  84 => 
  array (
    'term' => 'settings_upload_maxfilesize_tip',
    'definition' => 'Максимальный разрешенный размер файла. Должен выбираться с учетом настроек сервера.',
    'context' => '',
  ),
  85 => 
  array (
    'term' => 'settings_upload_docext_tip',
    'definition' => 'Типы документов. Перечислите допустимые файловые расширения через запятую (,)',
    'context' => '',
  ),
  86 => 
  array (
    'term' => 'settings_upload_imagesext_tip',
    'definition' => 'Типы изображений. Перечислите допустимые файловые расширения через запятую (,)',
    'context' => '',
  ),
  87 => 
  array (
    'term' => 'settings_upload_pkgext_tip',
    'definition' => 'Типы архивов. Перечислите допустимые файловые расширения через запятую (,)',
    'context' => '',
  ),
  88 => 
  array (
    'term' => 'settings_upload_otherext_tip',
    'definition' => 'Другие типы. Перечислите допустимые файловые расширения через запятую (,)',
    'context' => '',
  ),
  89 => 
  array (
    'term' => 'settings_upload_imageresize_options_tip',
    'definition' => 'При включении этой опции, все изображения будут конвертироваться согласно настройкам ниже.',
    'context' => '',
  ),
  90 => 
  array (
    'term' => 'settings_upload_maxfilesize',
    'definition' => 'Максимальный размер файла (МБайт)',
    'context' => '',
  ),
  91 => 
  array (
    'term' => 'settings_upload_docext',
    'definition' => 'Допустимые расширения документов',
    'context' => '',
  ),
  92 => 
  array (
    'term' => 'settings_upload_imagesext',
    'definition' => 'Допустимые расширения изображений',
    'context' => '',
  ),
  93 => 
  array (
    'term' => 'settings_upload_pkgext',
    'definition' => 'Допустимые расширения архивов',
    'context' => '',
  ),
  94 => 
  array (
    'term' => 'settings_upload_otherext',
    'definition' => 'Другие допустимые расширения',
    'context' => '',
  ),
  95 => 
  array (
    'term' => 'settings_upload_imageresize_options',
    'definition' => 'Конвертировать изображения',
    'context' => '',
  ),
  96 => 
  array (
    'term' => 'settings_upload_imageresize_options_w',
    'definition' => 'Ширина (пикселей)',
    'context' => '',
  ),
  97 => 
  array (
    'term' => 'settings_upload_imageresize_options_h',
    'definition' => 'Высота (пикселей)',
    'context' => '',
  ),
  98 => 
  array (
    'term' => 'settings_upload_imageresize_options_q',
    'definition' => 'Качество',
    'context' => '',
  ),
  99 => 
  array (
    'term' => 'admin_upload_title',
    'definition' => 'Загрузки',
    'context' => '',
  ),
  100 => 
  array (
    'term' => 'settings_importing',
    'definition' => 'Разрешить возможность импорта из CVS/KeyPass-файлов',
    'context' => '',
  ),
  101 => 
  array (
    'term' => 'admin_proxy_ip',
    'definition' => 'IP-адрес прокси сервера',
    'context' => '',
  ),
  102 => 
  array (
    'term' => 'admin_proxy_ip_tip',
    'definition' => 'Если Вы выходите в Интернет через прокси-сервер, то здесь нужно указать его IP-адрес.&lt;br&gt;Если прокси-сервер не используется - оставьте пустым.',
    'context' => '',
  ),
  103 => 
  array (
    'term' => 'admin_proxy_port',
    'definition' => 'Порт прокси-сервера',
    'context' => '',
  ),
  104 => 
  array (
    'term' => 'admin_proxy_port_tip',
    'definition' => 'Если задан IP-адрес прокси-сервера, укажите и его порт (напр. 8080).&lt;br&gt;Если прокси-сервер не используется - оставьте пустым.',
    'context' => '',
  ),
  105 => 
  array (
    'term' => 'settings_ldap_elusers',
    'definition' => ' Только локальные пользователи Teampass ',
    'context' => '',
  ),
  106 => 
  array (
    'term' => 'settings_ldap_elusers_tip',
    'definition' => 'Эта функция позволяет пользователям из базы осуществлять аутентификацию через LDAP. Выключите, если хотите просмотреть каталог LDAP.',
    'context' => '',
  ),
  107 => 
  array (
    'term' => 'error_role_complex_not_set',
    'definition' => 'Для роли необходимо задать минимальную сложность пароля!',
    'context' => '',
  ),
  108 => 
  array (
    'term' => 'item_updated_text',
    'definition' => 'Этот элемент был изменен. Обновите его перед тем как редактировать.',
    'context' => '',
  ),
  109 => 
  array (
    'term' => 'database_menu',
    'definition' => 'БД',
    'context' => '',
  ),
  110 => 
  array (
    'term' => 'db_items_edited',
    'definition' => 'Сейчас редактируется элементов',
    'context' => '',
  ),
  111 => 
  array (
    'term' => 'item_edition_start_hour',
    'definition' => 'Редактирование начато в',
    'context' => '',
  ),
  112 => 
  array (
    'term' => 'settings_delay_for_item_edition',
    'definition' => 'Промежуток времени, по истечении которого редактирование может считаться неуспешным (минут)',
    'context' => '',
  ),
  113 => 
  array (
    'term' => 'settings_delay_for_item_edition_tip',
    'definition' => 'На время редактирования элемента он блокируется, чтобы не производилось параллельных правок. Это делается путем создания токена.&lt;br&gt;Эта настройка позволяет удалять токен. 0 - токен никогда не будет удален (только Администратором)',
    'context' => '',
  ),
  114 => 
  array (
    'term' => 'db_users_logged',
    'definition' => 'Пользователи, осуществившие вход',
    'context' => '',
  ),
  115 => 
  array (
    'term' => 'action',
    'definition' => 'Действие',
    'context' => '',
  ),
  116 => 
  array (
    'term' => 'login_time',
    'definition' => 'Вход осуществлен',
    'context' => '',
  ),
  117 => 
  array (
    'term' => 'lastname',
    'definition' => 'Фамилия',
    'context' => '',
  ),
  118 => 
  array (
    'term' => 'user_login',
    'definition' => 'Имя пользователя',
    'context' => '',
  ),
  119 => 
  array (
    'term' => 'at_user_new_lastname',
    'definition' => 'Фамилия пользователя #user_login# изменена',
    'context' => '',
  ),
  120 => 
  array (
    'term' => 'at_user_new_name',
    'definition' => 'Имя пользователя #user_login# изменено',
    'context' => '',
  ),
  121 => 
  array (
    'term' => 'info_list_of_connected_users_approximation',
    'definition' => 'Замечание: В этом списке может показываться больше пользователей осуществивших вход, чем есть на самом деле.',
    'context' => '',
  ),
  122 => 
  array (
    'term' => 'disconnect_all_users',
    'definition' => 'Отключить всех пользователей (кроме Администраторов)',
    'context' => '',
  ),
  123 => 
  array (
    'term' => 'role',
    'definition' => 'Роль',
    'context' => '',
  ),
  124 => 
  array (
    'term' => 'admin_2factors_authentication_setting',
    'definition' => 'Включить двухфакторную аутентификацию Google',
    'context' => '',
  ),
  125 => 
  array (
    'term' => 'admin_2factors_authentication_setting_tip',
    'definition' => 'Двухфакторная аутентификация Google добавляет еще один уровень безопасности. Когда пользователь пытается войти в TeamPass, создается QR-код, который пользователь должен отсканировать, чтобы получить одноразовый пароль.&lt;br&gt;Внимание: для использования этой функции необходимо Интернет-соединение и сканер QR-кодов.',
    'context' => '',
  ),
  126 => 
  array (
    'term' => '2factors_tile',
    'definition' => 'Двухфакторная аутентификация',
    'context' => '',
  ),
  127 => 
  array (
    'term' => '2factors_image_text',
    'definition' => 'Пожалуйста, отсканируйте QR-код',
    'context' => '',
  ),
  128 => 
  array (
    'term' => '2factors_confirm_text',
    'definition' => 'Введите одноразовый пароль',
    'context' => '',
  ),
  129 => 
  array (
    'term' => 'bad_onetime_password',
    'definition' => 'Неверный одноразовый пароль!',
    'context' => '',
  ),
  130 => 
  array (
    'term' => 'error_string_not_utf8',
    'definition' => 'Ошибка: строка не в UTF8!',
    'context' => '',
  ),
  131 => 
  array (
    'term' => 'error_role_exist',
    'definition' => 'Роль уже существует!',
    'context' => '',
  ),
  132 => 
  array (
    'term' => 'error_no_edition_possible_locked',
    'definition' => 'Невозможно изменить. Этот элемент уже редактируется!',
    'context' => '',
  ),
  133 => 
  array (
    'term' => 'error_mcrypt_not_loaded',
    'definition' => 'Не загружено расширение PHP \'mcrypt\'. Пожалуйста, сообщите администратору.',
    'context' => '',
  ),
  134 => 
  array (
    'term' => 'at_user_added',
    'definition' => 'Создан пользователь #user_login#',
    'context' => '',
  ),
  135 => 
  array (
    'term' => 'at_user_deleted',
    'definition' => 'Удален пользователь #user_login#',
    'context' => '',
  ),
  136 => 
  array (
    'term' => 'at_user_locked',
    'definition' => 'Заблокирован пользователь #user_login#',
    'context' => '',
  ),
  137 => 
  array (
    'term' => 'at_user_unlocked',
    'definition' => 'Разблокирован пользователь #user_login#',
    'context' => '',
  ),
  138 => 
  array (
    'term' => 'at_user_email_changed',
    'definition' => 'Изменен email пользователя #user_login#',
    'context' => '',
  ),
  139 => 
  array (
    'term' => 'at_user_pwd_changed',
    'definition' => 'Изменен пароль пользователя #user_login#',
    'context' => '',
  ),
  140 => 
  array (
    'term' => 'at_user_initial_pwd_changed',
    'definition' => 'Смена пароля пользователя #user_login#',
    'context' => '',
  ),
  141 => 
  array (
    'term' => 'user_mngt',
    'definition' => 'Управление пользователями',
    'context' => '',
  ),
  142 => 
  array (
    'term' => 'select',
    'definition' => 'Выбрать файл',
    'context' => '',
  ),
  143 => 
  array (
    'term' => 'user_activity',
    'definition' => 'Активность пользователей',
    'context' => '',
  ),
  144 => 
  array (
    'term' => 'items',
    'definition' => 'Элементы',
    'context' => '',
  ),
  145 => 
  array (
    'term' => 'enable_personal_saltkey_cookie',
    'definition' => 'Хранить личный ключ шифрования в cookie',
    'context' => '',
  ),
  146 => 
  array (
    'term' => 'personal_saltkey_cookie_duration',
    'definition' => 'Время жизни cookie личного ключа шифрования (дни)',
    'context' => '',
  ),
  147 => 
  array (
    'term' => 'admin_emails',
    'definition' => 'Email\'ы',
    'context' => '',
  ),
  148 => 
  array (
    'term' => 'admin_emails_configuration',
    'definition' => 'Настройки email\'ов',
    'context' => '',
  ),
  149 => 
  array (
    'term' => 'admin_emails_configuration_testing',
    'definition' => 'Проверка настроек',
    'context' => '',
  ),
  150 => 
  array (
    'term' => 'admin_email_smtp_server',
    'definition' => 'SMTP-сервер',
    'context' => '',
  ),
  151 => 
  array (
    'term' => 'admin_email_auth',
    'definition' => 'SMTP-сервер требует аутентификации',
    'context' => '',
  ),
  152 => 
  array (
    'term' => 'admin_email_auth_username',
    'definition' => 'Имя пользователя',
    'context' => '',
  ),
  153 => 
  array (
    'term' => 'admin_email_auth_pwd',
    'definition' => 'Пароль',
    'context' => '',
  ),
  154 => 
  array (
    'term' => 'admin_email_port',
    'definition' => 'Порт сервера',
    'context' => '',
  ),
  155 => 
  array (
    'term' => 'admin_email_from',
    'definition' => 'Email отправителя (поле from Email)',
    'context' => '',
  ),
  156 => 
  array (
    'term' => 'admin_email_from_name',
    'definition' => 'Имя отправителя (поле from Name)',
    'context' => '',
  ),
  157 => 
  array (
    'term' => 'admin_email_test_configuration',
    'definition' => 'Проверить настройки email',
    'context' => '',
  ),
  158 => 
  array (
    'term' => 'admin_email_test_configuration_tip',
    'definition' => 'Этот тест отправляет письмо на указанный адрес. Если Вы его не получили - проверьте настройки.',
    'context' => '',
  ),
  159 => 
  array (
    'term' => 'admin_email_test_subject',
    'definition' => '[TeamPass] Тестовое письмо',
    'context' => '',
  ),
  160 => 
  array (
    'term' => 'admin_email_test_body',
    'definition' => 'Здравствуйте,&lt;br&gt;&lt;br&gt;Письмо успешно отправлено.',
    'context' => '',
  ),
  161 => 
  array (
    'term' => 'admin_email_result_ok',
    'definition' => 'Письмо отправлено на адрес #email# ... проверьте входящие сообщения.',
    'context' => '',
  ),
  162 => 
  array (
    'term' => 'admin_email_result_nok',
    'definition' => 'Письмо не отправлено... проверьте параметры конфигурации. См. ошибки: ',
    'context' => '',
  ),
  163 => 
  array (
    'term' => 'email_subject_item_updated',
    'definition' => 'Пароль обновлен',
    'context' => '',
  ),
  164 => 
  array (
    'term' => 'email_body_item_updated',
    'definition' => 'Здравствуйте,&lt;br&gt;&lt;br&gt;Пароль для \'#item_label#\' был изменен.&lt;br&gt;&lt;br&gt;Можете проверить его &lt;a href=\\"".@$_SESSION[\'settings\'][\'cpassman_url\']."/index.php?page=items&group=#item_category#&id=#item_id#\\"&gt;по ссылке&lt;/a&gt;',
    'context' => '',
  ),
  165 => 
  array (
    'term' => 'email_bodyalt_item_updated',
    'definition' => 'Пароль для #item_label# обновлен.',
    'context' => '',
  ),
  166 => 
  array (
    'term' => 'admin_email_send_backlog',
    'definition' => 'Отослать скопившиеся в БД письма (#nb_emails# шт.)',
    'context' => '',
  ),
  167 => 
  array (
    'term' => 'admin_email_send_backlog_tip',
    'definition' => 'Этот скрипт принудительно совершает рассылку писем, скопившихся в БД.&lt;br&gt;Операция может занять время в зависимости от количества скопившихся писем.',
    'context' => '',
  ),
  168 => 
  array (
    'term' => 'please_wait',
    'definition' => 'Пожалуйста, подождите!',
    'context' => '',
  ),
  169 => 
  array (
    'term' => 'admin_url_to_files_folder',
    'definition' => 'URL к папке Files',
    'context' => '',
  ),
  170 => 
  array (
    'term' => 'admin_path_to_files_folder',
    'definition' => 'Путь к папке Files',
    'context' => '',
  ),
  171 => 
  array (
    'term' => 'admin_path_to_files_folder_tip',
    'definition' => 'Папка для создаваемых TeamPass и загружаемых файлов.&lt;br&gt;ВАЖНО: из соображений безопасности эта папка не должна находиться внутри WWW-папки сайта. Её необходимо расположить в защищенном месте и настроить правила перенаправления.&lt;br&gt;Рекомендуется настроить расписание (cron) по очистке этой папки',
    'context' => '',
  ),
  172 => 
  array (
    'term' => 'admin_path_to_upload_folder_tip',
    'definition' => 'Предназначена для хранения файлов, связанных с элементами.&lt;br&gt;ВАЖНО: из соображений безопасности эта папка не должна находиться внутри WWW-папки сайта. Её необходимо расположить в защищенном месте и настроить правила перенаправления.&lt;br&gt;Эту папку нельзя очищать! файлы в ней связаны с элементами',
    'context' => '',
  ),
  173 => 
  array (
    'term' => 'pdf_export',
    'definition' => 'Экспорт PDF',
    'context' => '',
  ),
  174 => 
  array (
    'term' => 'pdf_password',
    'definition' => 'Ключ шифрования PDF',
    'context' => '',
  ),
  175 => 
  array (
    'term' => 'pdf_password_warning',
    'definition' => 'Необходимо ввести ключ шифрования!',
    'context' => '',
  ),
  176 => 
  array (
    'term' => 'admin_pwd_maximum_length',
    'definition' => 'Максимальная длина пароля',
    'context' => '',
  ),
  177 => 
  array (
    'term' => 'admin_pwd_maximum_length_tip',
    'definition' => 'Длина пароля по умолчанию - 40 символов. Слишком большое значение может оказывать негативное влияние на производительность.',
    'context' => '',
  ),
  178 => 
  array (
    'term' => 'settings_insert_manual_entry_item_history',
    'definition' => 'Разрешить ручную вставку в историю элементов',
    'context' => '',
  ),
  179 => 
  array (
    'term' => 'settings_insert_manual_entry_item_history_tip',
    'definition' => 'По некоторым причинам Вам может понадобиться вручную добавлять записи в историю элемента. Включите эту настройку, для того чтобы это было возможно',
    'context' => '',
  ),
  180 => 
  array (
    'term' => 'add_history_entry',
    'definition' => 'Добавить запись в историю',
    'context' => '',
  ),
  181 => 
  array (
    'term' => 'at_manual',
    'definition' => 'Ручное действие',
    'context' => '',
  ),
  182 => 
  array (
    'term' => 'at_manual_add',
    'definition' => 'Добавлено вручную',
    'context' => '',
  ),
  183 => 
  array (
    'term' => 'admin_path_to_upload_folder',
    'definition' => 'Путь к папке Upload',
    'context' => '',
  ),
  184 => 
  array (
    'term' => 'admin_url_to_upload_folder',
    'definition' => 'URL к папке Upload',
    'context' => '',
  ),
  185 => 
  array (
    'term' => 'automatic_del_after_date_text',
    'definition' => 'или после даты',
    'context' => '',
  ),
  186 => 
  array (
    'term' => 'at_automatically_deleted',
    'definition' => 'Удалено автоматически',
    'context' => '',
  ),
  187 => 
  array (
    'term' => 'admin_setting_enable_delete_after_consultation',
    'definition' => 'Включить возможность автоматического удаления элементов по количеству просмотров',
    'context' => '',
  ),
  188 => 
  array (
    'term' => 'admin_setting_enable_delete_after_consultation_tip',
    'definition' => 'При создании элемента можно задать количество его просмотров, после которого элемент будет автоматически удален.',
    'context' => '',
  ),
  189 => 
  array (
    'term' => 'enable_delete_after_consultation',
    'definition' => 'Элемент будет удален после того как его просмотрят',
    'context' => '',
  ),
  190 => 
  array (
    'term' => 'times',
    'definition' => 'раз.',
    'context' => '',
  ),
  191 => 
  array (
    'term' => 'automatic_deletion_activated',
    'definition' => 'Автоматическое удаление включено',
    'context' => '',
  ),
  192 => 
  array (
    'term' => 'at_automatic_del',
    'definition' => 'автоматическое удаление',
    'context' => '',
  ),
  193 => 
  array (
    'term' => 'error_times_before_deletion',
    'definition' => 'Количество просмотров для удаления должно быть больше 0!',
    'context' => '',
  ),
  194 => 
  array (
    'term' => 'enable_notify',
    'definition' => 'Включить уведомления',
    'context' => '',
  ),
  195 => 
  array (
    'term' => 'disable_notify',
    'definition' => 'Отключить уведомления',
    'context' => '',
  ),
  196 => 
  array (
    'term' => 'notify_activated',
    'definition' => 'Уведомления включены',
    'context' => '',
  ),
  197 => 
  array (
    'term' => 'at_email',
    'definition' => 'email',
    'context' => '',
  ),
  198 => 
  array (
    'term' => 'enable_email_notification_on_item_shown',
    'definition' => 'Отправлять уведомление по почте при открытии элемента',
    'context' => '',
  ),
  199 => 
  array (
    'term' => 'bad_email_format',
    'definition' => 'Адрес почты не соответствует ожидаемому формату!',
    'context' => '',
  ),
  200 => 
  array (
    'term' => 'item_share_text',
    'definition' => 'Чтобы поделиться этим элементом по почте, введите email и нажмите `Отправить`.',
    'context' => '',
  ),
  201 => 
  array (
    'term' => 'share',
    'definition' => 'Поделиться элементом',
    'context' => '',
  ),
  202 => 
  array (
    'term' => 'share_sent_ok',
    'definition' => 'Письмо отправлено',
    'context' => '',
  ),
  203 => 
  array (
    'term' => 'email_share_item_subject',
    'definition' => '[TeamPass] С Вами поделились элементом',
    'context' => '',
  ),
  204 => 
  array (
    'term' => 'email_share_item_mail',
    'definition' => 'Здравствуйте,&lt;br&gt;&lt;br&gt;&lt;u&gt;#tp_user#&lt;/u&gt; поделился с Вами элементом &lt;b&gt;#tp_item#&lt;/b&gt;&lt;br&gt;Нажмите &lt;a href=\'#tp_link#\'&gt;ссылку&lt;/a&gt;, чтобы получить доступ.',
    'context' => '',
  ),
  205 => 
  array (
    'term' => 'see_item_title',
    'definition' => 'Подробности об элементе',
    'context' => '',
  ),
  206 => 
  array (
    'term' => 'email_on_open_notification_subject',
    'definition' => '[TeamPass] Уведомление об открытии элемента',
    'context' => '',
  ),
  207 => 
  array (
    'term' => 'email_on_open_notification_mail',
    'definition' => 'Здравствуйте,&lt;br&gt;&lt;br&gt;#tp_user# открыл и посмотрел элемент \\"#tp_item#\'\\".&lt;br&gt;Нажмите &lt;a href=\'#tp_link#\'&gt;ссылку&lt;/a&gt;, чтобы получить доступ.',
    'context' => '',
  ),
  208 => 
  array (
    'term' => 'pdf',
    'definition' => 'PDF',
    'context' => '',
  ),
  209 => 
  array (
    'term' => 'csv',
    'definition' => 'CSV',
    'context' => '',
  ),
  210 => 
  array (
    'term' => 'user_admin_migrate_pw',
    'definition' => 'Перенос личных элементов для учётной записи пользователя',
    'context' => '',
  ),
  211 => 
  array (
    'term' => 'migrate_pf_select_to',
    'definition' => 'Перенос личных элементов для пользователя',
    'context' => '',
  ),
  212 => 
  array (
    'term' => 'migrate_pf_user_salt',
    'definition' => 'Введите ключ шифрования для выбранного пользователя',
    'context' => '',
  ),
  213 => 
  array (
    'term' => 'migrate_pf_no_sk',
    'definition' => 'Вы не ввели ваш ключ шифрования',
    'context' => '',
  ),
  214 => 
  array (
    'term' => 'migrate_pf_no_sk_user',
    'definition' => 'Вы должны ввести ключ шифрования пользователя',
    'context' => '',
  ),
  215 => 
  array (
    'term' => 'migrate_pf_no_user_id',
    'definition' => 'Необходимо выбрать пользователя";',
    'context' => '',
  ),
  216 => 
  array (
    'term' => 'email_subject_new_user',
    'definition' => '[TeamPass] Создание Вашей учетной записи',
    'context' => '',
  ),
  217 => 
  array (
    'term' => 'email_new_user_mail',
    'definition' => 'Здравствуйте,&lt;br&gt;&lt;br&gt;Администратор создал Вашу учетную запись Teampass.&lt;br&gt;Используйте следующие учетные данные для входа:&lt;br&gt;- Имя пользователя: #tp_login#&lt;br&gt;- Пароль: #tp_pw#&lt;br&gt;&lt;br&gt;Нажмите &lt;a href=\'#tp_link#\'&gt;ссылку&lt;/a&gt;, чтобы получить доступ.',
    'context' => '',
  ),
  218 => 
  array (
    'term' => 'error_empty_data',
    'definition' => 'Недостаточно данных для продолжения!',
    'context' => '',
  ),
  219 => 
  array (
    'term' => 'error_not_allowed_to',
    'definition' => 'У Вас недостаточно прав для совершения этого действия!',
    'context' => '',
  ),
  220 => 
  array (
    'term' => 'personal_saltkey_lost',
    'definition' => 'Я его потерял',
    'context' => '',
  ),
  221 => 
  array (
    'term' => 'new_saltkey_warning_lost',
    'definition' => 'Вы потеряли свой ключ шифрования? Вот ведь незадача, эту потерю не восстановить.&lt;br&gt;ВНИМАНИЕ: все существующие элементы в личном разделе будут утеряны. Вы уверены в том, что его потеряли?',
    'context' => '',
  ),
  222 => 
  array (
    'term' => 'previous_pw',
    'definition' => 'Предыдущий использованный пароль:',
    'context' => '',
  ),
  223 => 
  array (
    'term' => 'no_previous_pw',
    'definition' => 'Отсутствуют предыдущие пароли',
    'context' => '',
  ),
  224 => 
  array (
    'term' => 'request_access_ot_item',
    'definition' => 'Отправить запрос создателю элемента',
    'context' => '',
  ),
  225 => 
  array (
    'term' => 'email_request_access_subject',
    'definition' => '[TeamPass] Запрос доступа к элементу',
    'context' => '',
  ),
  226 => 
  array (
    'term' => 'email_request_access_mail',
    'definition' => 'Здравствуйте, #tp_item_author#,&lt;br&gt;&lt;br&gt;Пользователь #tp_user# запросил доступ к элементу \'#tp_item#\'.&lt;br&gt;&lt;br&gt;Убедитесь, что пользователь имеет право на доступ к этому элементу.',
    'context' => '',
  ),
  227 => 
  array (
    'term' => 'admin_action_change_salt_key',
    'definition' => 'Сменить основной ключ шифрования',
    'context' => '',
  ),
  228 => 
  array (
    'term' => 'admin_action_change_salt_key_tip',
    'definition' => 'Перед изменением ключа шифрования, сделайте полную резервную копию БД и переведите TeamPass в режим обслуживания, чтобы исключить возможность входа других пользователей.',
    'context' => '',
  ),
  229 => 
  array (
    'term' => 'block_admin_info',
    'definition' => 'Информация об Администраторах',
    'context' => '',
  ),
  230 => 
  array (
    'term' => 'admin_new1',
    'definition' => '&lt;i&gt;&lt;u&gt;14FEB2012:&lt;/i&gt;&lt;/u&gt;&lt;br&gt;Администратор не может просматривать элементы. Эта учетная запись предназначена только для администрирования.&lt;br&gt;См. &lt;a href=\'http://www.teampass.net/how-to-handle-changes-on-administrator-profile\' target=\'_blank\'&gt;страницу на TeamPass.net&lt;/a&gt; с пояснениями по данному изменению.',
    'context' => '',
  ),
  231 => 
  array (
    'term' => 'nb_items_by_query',
    'definition' => 'Количество элементов, получаемых за один запрос',
    'context' => '',
  ),
  232 => 
  array (
    'term' => 'nb_items_by_query_tip',
    'definition' => 'Слишком большое количество затрудняет процесс отображения списка.&lt;br&gt;Значение \'auto\' позволяет автоматически подстраиваться под размер экрана пользователя.&lt;br&gt;Значение \'max\' принудительно заставляет показывать весь список сразу.&lt;br&gt;Также можно задать конкретное количество элементов, загружаемых за один запрос.',
    'context' => '',
  ),
  233 => 
  array (
    'term' => 'error_no_selected_folder',
    'definition' => 'Необходимо выбрать папку',
    'context' => '',
  ),
  234 => 
  array (
    'term' => 'open_url_link',
    'definition' => 'Открыть на новой странице',
    'context' => '',
  ),
  235 => 
  array (
    'term' => 'error_pw_too_long',
    'definition' => 'Пароль слишком большой! Максимальная длина - 40.',
    'context' => '',
  ),
  236 => 
  array (
    'term' => 'at_restriction',
    'definition' => 'Ограничение',
    'context' => '',
  ),
  237 => 
  array (
    'term' => 'pw_encryption_error',
    'definition' => 'Ошибка шифрования пароля!',
    'context' => '',
  ),
  238 => 
  array (
    'term' => 'enable_send_email_on_user_login',
    'definition' => 'Отправлять письмо администратору, когда пользователь осуществляет вход',
    'context' => '',
  ),
  239 => 
  array (
    'term' => 'email_subject_on_user_login',
    'definition' => '[TeamPass] Пользователь совершил вход',
    'context' => '',
  ),
  240 => 
  array (
    'term' => 'email_body_on_user_login',
    'definition' => 'Здравствуйте,&lt;br&gt;&lt;br&gt;Пользователь #tp_user# вошел в TeamPass #tp_date# в #tp_time#.',
    'context' => '',
  ),
  241 => 
  array (
    'term' => 'account_is_locked',
    'definition' => 'Учетная запись заблокирована',
    'context' => '',
  ),
  242 => 
  array (
    'term' => 'activity',
    'definition' => 'Активность',
    'context' => '',
  ),
  243 => 
  array (
    'term' => 'add_button',
    'definition' => 'Добавить',
    'context' => '',
  ),
  244 => 
  array (
    'term' => 'add_new_group',
    'definition' => 'Создать папку',
    'context' => '',
  ),
  245 => 
  array (
    'term' => 'add_role_tip',
    'definition' => 'Создать роль',
    'context' => '',
  ),
  246 => 
  array (
    'term' => 'admin',
    'definition' => 'Администрирование',
    'context' => '',
  ),
  247 => 
  array (
    'term' => 'admin_action',
    'definition' => 'Пожалуйста, подтвердите действие',
    'context' => '',
  ),
  248 => 
  array (
    'term' => 'admin_action_db_backup',
    'definition' => 'Создать резервную копию БД',
    'context' => '',
  ),
  249 => 
  array (
    'term' => 'admin_action_db_backup_key_tip',
    'definition' => 'Пожалуйста, введите ключ шифрования. Надежно сохраните его, он понадобится при восстановлении. (Оставьте пустым, чтобы не шифровать)',
    'context' => '',
  ),
  250 => 
  array (
    'term' => 'admin_action_db_backup_start_tip',
    'definition' => 'Начать',
    'context' => '',
  ),
  251 => 
  array (
    'term' => 'admin_action_db_backup_tip',
    'definition' => 'Создание резервных копий для обеспечения возможности восстановления БД является хорошей практикой.',
    'context' => '',
  ),
  252 => 
  array (
    'term' => 'admin_action_db_clean_items',
    'definition' => 'Удалить элементы-\'сироты\' из базы данных',
    'context' => '',
  ),
  253 => 
  array (
    'term' => 'admin_action_db_clean_items_result',
    'definition' => 'Элементы удалены',
    'context' => '',
  ),
  254 => 
  array (
    'term' => 'admin_action_db_clean_items_tip',
    'definition' => 'Это действие удаляет элементы, которые не быди удалены после удаления своих папок. Настоятельно рекомендуется перед этим создать резервную копию.',
    'context' => '',
  ),
  255 => 
  array (
    'term' => 'admin_action_db_optimize',
    'definition' => 'Оптимизировать БД',
    'context' => '',
  ),
  256 => 
  array (
    'term' => 'admin_action_db_restore',
    'definition' => 'Восстановить БД',
    'context' => '',
  ),
  257 => 
  array (
    'term' => 'admin_action_db_restore_key',
    'definition' => 'Пожалуйста, введите ключ шифрования.',
    'context' => '',
  ),
  258 => 
  array (
    'term' => 'admin_action_db_restore_tip',
    'definition' => 'Восстановление БД из файла резервной копии, сделанного при помощи опции резервного копирования.',
    'context' => '',
  ),
  259 => 
  array (
    'term' => 'admin_action_purge_old_files',
    'definition' => 'Очистить старые файлы',
    'context' => '',
  ),
  260 => 
  array (
    'term' => 'admin_action_purge_old_files_result',
    'definition' => 'Файлы удалены',
    'context' => '',
  ),
  261 => 
  array (
    'term' => 'admin_action_purge_old_files_tip',
    'definition' => 'Это удалит все временные файлы старше 7 дней.',
    'context' => '',
  ),
  262 => 
  array (
    'term' => 'admin_action_reload_cache_table',
    'definition' => 'Перезагрузить Cache таблицу',
    'context' => '',
  ),
  263 => 
  array (
    'term' => 'admin_action_reload_cache_table_tip',
    'definition' => 'Сбрасывает содержимое Cache таблицы. Рекомендуется запускать время от времени.',
    'context' => '',
  ),
  264 => 
  array (
    'term' => 'admin_backups',
    'definition' => 'Резервные копии',
    'context' => '',
  ),
  265 => 
  array (
    'term' => 'admin_error_no_complexity',
    'definition' => '(&lt;a href=\'index.php?page=manage_groups\'&gt;Определить?&lt;/a&gt;)',
    'context' => '',
  ),
  266 => 
  array (
    'term' => 'admin_error_no_visibility',
    'definition' => 'Никто не может видеть этот элемент. (&lt;a href=\'index.php?page=manage_roles\'&gt;Управление ролями&lt;/a&gt;)',
    'context' => '',
  ),
  267 => 
  array (
    'term' => 'admin_functions',
    'definition' => 'Управление ролями',
    'context' => '',
  ),
  268 => 
  array (
    'term' => 'admin_groups',
    'definition' => 'Управление папками',
    'context' => '',
  ),
  269 => 
  array (
    'term' => 'admin_help',
    'definition' => 'Справка',
    'context' => '',
  ),
  270 => 
  array (
    'term' => 'admin_info',
    'definition' => 'Некоторая информация о системе',
    'context' => '',
  ),
  271 => 
  array (
    'term' => 'admin_info_loading',
    'definition' => 'Загружаютcя данные... пожалуйста, подождите',
    'context' => '',
  ),
  272 => 
  array (
    'term' => 'admin_ldap_configuration',
    'definition' => 'Конфигурация LDAP',
    'context' => '',
  ),
  273 => 
  array (
    'term' => 'admin_ldap_menu',
    'definition' => 'LDAP',
    'context' => '',
  ),
  274 => 
  array (
    'term' => 'admin_main',
    'definition' => 'Информация',
    'context' => '',
  ),
  275 => 
  array (
    'term' => 'admin_misc_cpassman_dir',
    'definition' => 'Полный путь к TeamPass',
    'context' => '',
  ),
  276 => 
  array (
    'term' => 'admin_misc_cpassman_url',
    'definition' => 'Полный URL к TeamPass',
    'context' => '',
  ),
  277 => 
  array (
    'term' => 'admin_misc_custom_login_text',
    'definition' => 'Собственный текст входа',
    'context' => '',
  ),
  278 => 
  array (
    'term' => 'admin_misc_custom_logo',
    'definition' => 'Полный URL к собственному логотипу входа',
    'context' => '',
  ),
  279 => 
  array (
    'term' => 'admin_misc_favicon',
    'definition' => 'Полный URL к файлу favicon',
    'context' => '',
  ),
  280 => 
  array (
    'term' => 'admin_misc_title',
    'definition' => 'Доп. настройки',
    'context' => '',
  ),
  281 => 
  array (
    'term' => 'admin_one_shot_backup',
    'definition' => 'Однократное резервное копирование и восстановление',
    'context' => '',
  ),
  282 => 
  array (
    'term' => 'admin_script_backups',
    'definition' => 'Настройки скрипта резервного копирования',
    'context' => '',
  ),
  283 => 
  array (
    'term' => 'admin_script_backups_tip',
    'definition' => 'Для большей безопасности рекомендуется настроить периодическое резервное копирование БД.&lt;br&gt;Настройте на Вашем сервере задание по расписанию, которое будет выполнять скрипт \'script.backup.php\' из папки \'backups\'.&lt;br&gt;Обязательно задайте путь и имя резервной копии.',
    'context' => '',
  ),
  284 => 
  array (
    'term' => 'admin_script_backup_decrypt',
    'definition' => 'Имя расшифровываемого файла',
    'context' => '',
  ),
  285 => 
  array (
    'term' => 'admin_script_backup_decrypt_tip',
    'definition' => 'Для того, чтобы расшифровать файл резервной копии, укажите имя файла (без расширения и полного пути, только имя).&lt;br&gt;Расшифрованный файл будет сохранен в ту же папку, что и исходный.',
    'context' => '',
  ),
  286 => 
  array (
    'term' => 'admin_script_backup_encryption',
    'definition' => 'Ключ шифрования (необязательно)',
    'context' => '',
  ),
  287 => 
  array (
    'term' => 'admin_script_backup_encryption_tip',
    'definition' => 'Если задать ключ, то файл будет зашифрован с помощью этого ключа',
    'context' => '',
  ),
  288 => 
  array (
    'term' => 'admin_script_backup_filename',
    'definition' => 'Имя файла резервной копии',
    'context' => '',
  ),
  289 => 
  array (
    'term' => 'admin_script_backup_filename_tip',
    'definition' => 'Имя, которое Вы хотите для файлов своих резервных копий',
    'context' => '',
  ),
  290 => 
  array (
    'term' => 'admin_script_backup_path',
    'definition' => 'Путь сохранения резервных копий',
    'context' => '',
  ),
  291 => 
  array (
    'term' => 'admin_script_backup_path_tip',
    'definition' => 'Укажите папку, в которой Вы хотите хранить свои резервные копии',
    'context' => '',
  ),
  292 => 
  array (
    'term' => 'admin_settings',
    'definition' => 'Настройки',
    'context' => '',
  ),
  293 => 
  array (
    'term' => 'admin_settings_title',
    'definition' => 'Настройки',
    'context' => '',
  ),
  294 => 
  array (
    'term' => 'admin_setting_activate_expiration',
    'definition' => 'Включить устаревание паролей',
    'context' => '',
  ),
  295 => 
  array (
    'term' => 'admin_setting_activate_expiration_tip',
    'definition' => 'Когда включено, устаревшие объекты не будут показываться пользователям.',
    'context' => '',
  ),
  296 => 
  array (
    'term' => 'admin_users',
    'definition' => 'Управление пользователями',
    'context' => '',
  ),
  297 => 
  array (
    'term' => 'admin_views',
    'definition' => 'Просмотр',
    'context' => '',
  ),
  298 => 
  array (
    'term' => 'alert_message_done',
    'definition' => 'Готово!',
    'context' => '',
  ),
  299 => 
  array (
    'term' => 'alert_message_personal_sk_missing',
    'definition' => 'Вы должны ввести свой личный ключ шифрования!',
    'context' => '',
  ),
  300 => 
  array (
    'term' => 'all',
    'definition' => 'все',
    'context' => '',
  ),
  301 => 
  array (
    'term' => 'anyone_can_modify',
    'definition' => 'Разрешить измененение этого элемента любому, кто имеет к нему доступ',
    'context' => '',
  ),
  302 => 
  array (
    'term' => 'associated_role',
    'definition' => 'С какой ролью связать эту папку:',
    'context' => '',
  ),
  303 => 
  array (
    'term' => 'associate_kb_to_items',
    'definition' => 'Выберите элементы, которые Вы хотите связать с этой БЗ',
    'context' => '',
  ),
  304 => 
  array (
    'term' => 'assoc_authorized_groups',
    'definition' => 'Разрешенные связанные папки',
    'context' => '',
  ),
  305 => 
  array (
    'term' => 'assoc_forbidden_groups',
    'definition' => 'Запрещенные связанные папки',
    'context' => '',
  ),
  306 => 
  array (
    'term' => 'at',
    'definition' => 'в',
    'context' => '',
  ),
  307 => 
  array (
    'term' => 'at_add_file',
    'definition' => 'Добавлен файл',
    'context' => '',
  ),
  308 => 
  array (
    'term' => 'at_category',
    'definition' => 'Папка',
    'context' => '',
  ),
  309 => 
  array (
    'term' => 'at_copy',
    'definition' => 'Скопированные',
    'context' => '',
  ),
  310 => 
  array (
    'term' => 'at_creation',
    'definition' => 'Создание',
    'context' => '',
  ),
  311 => 
  array (
    'term' => 'at_delete',
    'definition' => 'Удаление',
    'context' => '',
  ),
  312 => 
  array (
    'term' => 'at_del_file',
    'definition' => 'Удален файл',
    'context' => '',
  ),
  313 => 
  array (
    'term' => 'at_description',
    'definition' => 'Описание',
    'context' => '',
  ),
  314 => 
  array (
    'term' => 'at_file',
    'definition' => 'Файлы',
    'context' => '',
  ),
  315 => 
  array (
    'term' => 'at_import',
    'definition' => 'Импортирование',
    'context' => '',
  ),
  316 => 
  array (
    'term' => 'at_label',
    'definition' => 'Метка',
    'context' => '',
  ),
  317 => 
  array (
    'term' => 'at_login',
    'definition' => 'Имя входа',
    'context' => '',
  ),
  318 => 
  array (
    'term' => 'at_modification',
    'definition' => 'Модификация',
    'context' => '',
  ),
  319 => 
  array (
    'term' => 'at_moved',
    'definition' => 'Перемещен',
    'context' => '',
  ),
  320 => 
  array (
    'term' => 'at_personnel',
    'definition' => 'Личный',
    'context' => '',
  ),
  321 => 
  array (
    'term' => 'at_pw',
    'definition' => 'Пароль изменён',
    'context' => '',
  ),
  322 => 
  array (
    'term' => 'at_restored',
    'definition' => 'Восстановлен',
    'context' => '',
  ),
  323 => 
  array (
    'term' => 'at_shown',
    'definition' => 'Просмотренные',
    'context' => '',
  ),
  324 => 
  array (
    'term' => 'at_url',
    'definition' => 'URL',
    'context' => '',
  ),
  325 => 
  array (
    'term' => 'auteur',
    'definition' => 'Автор',
    'context' => '',
  ),
  326 => 
  array (
    'term' => 'author',
    'definition' => 'Автор',
    'context' => '',
  ),
  327 => 
  array (
    'term' => 'authorized_groups',
    'definition' => 'Разрешенные папки',
    'context' => '',
  ),
  328 => 
  array (
    'term' => 'auth_creation_without_complexity',
    'definition' => 'Разрешить создание элементов без учета требований сложности',
    'context' => '',
  ),
  329 => 
  array (
    'term' => 'auth_modification_without_complexity',
    'definition' => 'Разрешить редактирование элементов без учета требований сложности',
    'context' => '',
  ),
  330 => 
  array (
    'term' => 'auto_create_folder_role',
    'definition' => 'Создать папку и роль для ',
    'context' => '',
  ),
  331 => 
  array (
    'term' => 'block_last_created',
    'definition' => 'Последние созданные',
    'context' => '',
  ),
  332 => 
  array (
    'term' => 'bugs_page',
    'definition' => 'Если вы обнаружили ошибку, вы можете сообщить о ней &lt;a href=\'https://sourceforge.net/tracker/?group_id=280505&amp;atid=1190333\' target=\'_blank\'&gt;&lt;u&gt;сюда&lt;/u&gt;&lt;/a&gt;.',
    'context' => '',
  ),
  333 => 
  array (
    'term' => 'by',
    'definition' => 'к',
    'context' => '',
  ),
  334 => 
  array (
    'term' => 'cancel',
    'definition' => 'Отмена',
    'context' => '',
  ),
  335 => 
  array (
    'term' => 'cancel_button',
    'definition' => 'Отмена',
    'context' => '',
  ),
  336 => 
  array (
    'term' => 'can_create_root_folder',
    'definition' => 'Может создавать папки верхнего уровня',
    'context' => '',
  ),
  337 => 
  array (
    'term' => 'changelog',
    'definition' => 'Последние новости',
    'context' => '',
  ),
  338 => 
  array (
    'term' => 'change_authorized_groups',
    'definition' => 'Изменить разрешенные папки',
    'context' => '',
  ),
  339 => 
  array (
    'term' => 'change_forbidden_groups',
    'definition' => 'Изменить запрещенные папки',
    'context' => '',
  ),
  340 => 
  array (
    'term' => 'change_function',
    'definition' => 'Изменить роли',
    'context' => '',
  ),
  341 => 
  array (
    'term' => 'change_group_autgroups_info',
    'definition' => 'Выбрать папки, которые можно просматривать и использовать в этой роли',
    'context' => '',
  ),
  342 => 
  array (
    'term' => 'change_group_autgroups_title',
    'definition' => 'Задать разрешенные папки',
    'context' => '',
  ),
  343 => 
  array (
    'term' => 'change_group_forgroups_info',
    'definition' => 'Выбрать папки, которые нельзя просматривать и использовать в этой роли',
    'context' => '',
  ),
  344 => 
  array (
    'term' => 'change_group_forgroups_title',
    'definition' => 'Задать запрещенные папки',
    'context' => '',
  ),
  345 => 
  array (
    'term' => 'change_user_autgroups_info',
    'definition' => 'Выбрать папки, которые можно просматривать и использовать этой учетной записи',
    'context' => '',
  ),
  346 => 
  array (
    'term' => 'change_user_autgroups_title',
    'definition' => 'Задать разрешенные папки',
    'context' => '',
  ),
  347 => 
  array (
    'term' => 'change_user_forgroups_info',
    'definition' => 'Выбрать папки, которые нельзя просматривать и использовать этой учетной записи',
    'context' => '',
  ),
  348 => 
  array (
    'term' => 'change_user_forgroups_title',
    'definition' => 'Задать запрещенные папки',
    'context' => '',
  ),
  349 => 
  array (
    'term' => 'change_user_functions_info',
    'definition' => 'Выбрать функции связанные с этой учетной записью',
    'context' => '',
  ),
  350 => 
  array (
    'term' => 'change_user_functions_title',
    'definition' => 'Задать связанные функции',
    'context' => '',
  ),
  351 => 
  array (
    'term' => 'check_all_text',
    'definition' => 'Выбрать все',
    'context' => '',
  ),
  352 => 
  array (
    'term' => 'close',
    'definition' => 'Закрыть',
    'context' => '',
  ),
  353 => 
  array (
    'term' => 'complexity',
    'definition' => 'Сложность',
    'context' => '',
  ),
  354 => 
  array (
    'term' => 'complex_asked',
    'definition' => 'Требуемая сложность',
    'context' => '',
  ),
  355 => 
  array (
    'term' => 'complex_level0',
    'definition' => 'Очень слабый',
    'context' => '',
  ),
  356 => 
  array (
    'term' => 'complex_level1',
    'definition' => 'Слабый',
    'context' => '',
  ),
  357 => 
  array (
    'term' => 'complex_level2',
    'definition' => 'Средний',
    'context' => '',
  ),
  358 => 
  array (
    'term' => 'complex_level3',
    'definition' => 'Стойкий',
    'context' => '',
  ),
  359 => 
  array (
    'term' => 'complex_level4',
    'definition' => 'Очень стойкий',
    'context' => '',
  ),
  360 => 
  array (
    'term' => 'complex_level5',
    'definition' => 'Тяжелый',
    'context' => '',
  ),
  361 => 
  array (
    'term' => 'complex_level6',
    'definition' => 'Очень тяжелый',
    'context' => '',
  ),
  362 => 
  array (
    'term' => 'confirm',
    'definition' => 'Подтвердить',
    'context' => '',
  ),
  363 => 
  array (
    'term' => 'confirm_delete_group',
    'definition' => 'Вы уверены, что хотите удалить эту папку и все вложенные в неё элементы?',
    'context' => '',
  ),
  364 => 
  array (
    'term' => 'confirm_del_account',
    'definition' => 'Вы уверены, что хотите удалить эту учетную запись?',
    'context' => '',
  ),
  365 => 
  array (
    'term' => 'confirm_del_from_fav',
    'definition' => 'Пожалуйста, подтвердите удаление из Избранного',
    'context' => '',
  ),
  366 => 
  array (
    'term' => 'confirm_del_role',
    'definition' => 'Пожалуйста, подтвердите удаление следующей роли:',
    'context' => '',
  ),
  367 => 
  array (
    'term' => 'confirm_edit_role',
    'definition' => 'Пожалуйста, введите новое имя для следующей роли:',
    'context' => '',
  ),
  368 => 
  array (
    'term' => 'confirm_lock_account',
    'definition' => 'Вы уверены, что хотите заблокировать эту учетную запись?',
    'context' => '',
  ),
  369 => 
  array (
    'term' => 'connection',
    'definition' => 'Соединение',
    'context' => '',
  ),
  370 => 
  array (
    'term' => 'connections',
    'definition' => 'Соединения',
    'context' => '',
  ),
  371 => 
  array (
    'term' => 'copy',
    'definition' => 'Копировать',
    'context' => '',
  ),
  372 => 
  array (
    'term' => 'copy_to_clipboard_small_icons',
    'definition' => 'Отображать значки копирования в буфер обмена для каждого элемента',
    'context' => '',
  ),
  373 => 
  array (
    'term' => 'copy_to_clipboard_small_icons_tip',
    'definition' => 'Отображает для каждого элемента значки копирования имени пользователя и пароля в буфер обмена. Отключите для уменьшения нагрузки на старые компьютеры.',
    'context' => '',
  ),
  374 => 
  array (
    'term' => 'creation_date',
    'definition' => 'Дата создания',
    'context' => '',
  ),
  375 => 
  array (
    'term' => 'csv_import_button_text',
    'definition' => 'Открыть CSV-файл',
    'context' => '',
  ),
  376 => 
  array (
    'term' => 'date',
    'definition' => 'Дата',
    'context' => '',
  ),
  377 => 
  array (
    'term' => 'date_format',
    'definition' => 'Формат даты',
    'context' => '',
  ),
  378 => 
  array (
    'term' => 'days',
    'definition' => 'дни',
    'context' => '',
  ),
  379 => 
  array (
    'term' => 'definition',
    'definition' => 'Описание',
    'context' => '',
  ),
  380 => 
  array (
    'term' => 'delete',
    'definition' => 'Удалить',
    'context' => '',
  ),
  381 => 
  array (
    'term' => 'deletion',
    'definition' => 'Удаления',
    'context' => '',
  ),
  382 => 
  array (
    'term' => 'deletion_title',
    'definition' => 'Список удаленных элементов',
    'context' => '',
  ),
  383 => 
  array (
    'term' => 'del_button',
    'definition' => 'Удалить',
    'context' => '',
  ),
  384 => 
  array (
    'term' => 'del_function',
    'definition' => 'Удалить роли',
    'context' => '',
  ),
  385 => 
  array (
    'term' => 'del_group',
    'definition' => 'Удалить папку',
    'context' => '',
  ),
  386 => 
  array (
    'term' => 'description',
    'definition' => 'Описание',
    'context' => '',
  ),
  387 => 
  array (
    'term' => 'disconnect',
    'definition' => 'Выход',
    'context' => '',
  ),
  388 => 
  array (
    'term' => 'disconnection',
    'definition' => 'Выход',
    'context' => '',
  ),
  389 => 
  array (
    'term' => 'div_dialog_message_title',
    'definition' => 'Информация',
    'context' => '',
  ),
  390 => 
  array (
    'term' => 'done',
    'definition' => 'Сделано',
    'context' => '',
  ),
  391 => 
  array (
    'term' => 'drag_drop_helper',
    'definition' => 'Кликните и перетащите элемент',
    'context' => '',
  ),
  392 => 
  array (
    'term' => 'duplicate_folder',
    'definition' => 'Разрешить использовать папки с одинаковыми именами',
    'context' => '',
  ),
  393 => 
  array (
    'term' => 'duplicate_item',
    'definition' => 'Разрешить использовать элементы с одинаковыми именами',
    'context' => '',
  ),
  394 => 
  array (
    'term' => 'email',
    'definition' => 'Email',
    'context' => '',
  ),
  395 => 
  array (
    'term' => 'email_altbody_1',
    'definition' => 'Элемент',
    'context' => '',
  ),
  396 => 
  array (
    'term' => 'email_altbody_2',
    'definition' => 'был создан.',
    'context' => '',
  ),
  397 => 
  array (
    'term' => 'email_announce',
    'definition' => 'Оповестить об этом элементе по email',
    'context' => '',
  ),
  398 => 
  array (
    'term' => 'email_body1',
    'definition' => 'Здравствуйте,&lt;br&gt;&lt;br&gt;Элемент \'',
    'context' => '',
  ),
  399 => 
  array (
    'term' => 'email_body2',
    'definition' => 'был создан.&lt;br&gt;&lt;br&gt;Вы можете просмотреть его, перейдя по ссылке &lt;a href=\'',
    'context' => '',
  ),
  400 => 
  array (
    'term' => 'email_body3',
    'definition' => '\'&gt;по ссылке&lt;/a&gt;.',
    'context' => '',
  ),
  401 => 
  array (
    'term' => 'email_change',
    'definition' => 'Изменить email учетной записи',
    'context' => '',
  ),
  402 => 
  array (
    'term' => 'email_changed',
    'definition' => 'Email изменен!',
    'context' => '',
  ),
  403 => 
  array (
    'term' => 'email_select',
    'definition' => 'Выберите получателей',
    'context' => '',
  ),
  404 => 
  array (
    'term' => 'email_subject',
    'definition' => 'Создание нового элемента в Менеджере паролей',
    'context' => '',
  ),
  405 => 
  array (
    'term' => 'email_text_new_user',
    'definition' => 'Здравствуйте,&lt;br&gt;&lt;br&gt;Ваша учетная запись была создана в TeamPass.&lt;br&gt;Вы можете войти по адресу $TeamPass_url, используя предоставленные учетные данные:&lt;br&gt;',
    'context' => '',
  ),
  406 => 
  array (
    'term' => 'enable_favourites',
    'definition' => 'Разрешить Пользователям сохранять Избранные записи',
    'context' => '',
  ),
  407 => 
  array (
    'term' => 'enable_personal_folder',
    'definition' => 'Разрешить Личную папку',
    'context' => '',
  ),
  408 => 
  array (
    'term' => 'enable_personal_folder_feature',
    'definition' => 'Разрешить функцию Личной папки',
    'context' => '',
  ),
  409 => 
  array (
    'term' => 'enable_user_can_create_folders',
    'definition' => 'Пользователь может управлять подпапками в разрешенных папках',
    'context' => '',
  ),
  410 => 
  array (
    'term' => 'encrypt_key',
    'definition' => 'Ключ шифрования',
    'context' => '',
  ),
  411 => 
  array (
    'term' => 'errors',
    'definition' => 'Ошибки',
    'context' => '',
  ),
  412 => 
  array (
    'term' => 'error_complex_not_enought',
    'definition' => 'Сложность пароля не соответствует требуемой!',
    'context' => '',
  ),
  413 => 
  array (
    'term' => 'error_confirm',
    'definition' => 'Подтверждение пароля не совпадает с паролем!',
    'context' => '',
  ),
  414 => 
  array (
    'term' => 'error_cpassman_dir',
    'definition' => 'Не указан путь к TeamPass. Укажите правильный путь во вкладке \'Настройки TeamPass\' на странице \'Настройки\'.',
    'context' => '',
  ),
  415 => 
  array (
    'term' => 'error_cpassman_url',
    'definition' => 'Не указан URL к TeamPass. Укажите правильный URL во вкладке \'Настройки TeamPass\' на странице \'Настройки\'.',
    'context' => '',
  ),
  416 => 
  array (
    'term' => 'error_fields_2',
    'definition' => 'Все поля обязательны к заполнению!',
    'context' => '',
  ),
  417 => 
  array (
    'term' => 'error_group',
    'definition' => 'Имя папки обязательно к заполнению!',
    'context' => '',
  ),
  418 => 
  array (
    'term' => 'error_group_complex',
    'definition' => 'Папке необходимо задать минимальную сложность пароля!',
    'context' => '',
  ),
  419 => 
  array (
    'term' => 'error_group_exist',
    'definition' => 'Такая папка уже существует!',
    'context' => '',
  ),
  420 => 
  array (
    'term' => 'error_group_label',
    'definition' => 'Папке нужно имя!',
    'context' => '',
  ),
  421 => 
  array (
    'term' => 'error_html_codes',
    'definition' => 'В тексте присутствуют HTML-теги! Это недопустимо.',
    'context' => '',
  ),
  422 => 
  array (
    'term' => 'error_item_exists',
    'definition' => 'Такой элемент уже существует!',
    'context' => '',
  ),
  423 => 
  array (
    'term' => 'error_label',
    'definition' => 'Поле \'Метка\' обязательно к заполнению!',
    'context' => '',
  ),
  424 => 
  array (
    'term' => 'error_must_enter_all_fields',
    'definition' => 'Необходимо заполнить все поля!',
    'context' => '',
  ),
  425 => 
  array (
    'term' => 'error_mysql',
    'definition' => 'Ошибка MySQL!',
    'context' => '',
  ),
  426 => 
  array (
    'term' => 'error_not_authorized',
    'definition' => 'У Вас не прав для просмотра этой страницы.',
    'context' => '',
  ),
  427 => 
  array (
    'term' => 'error_not_exists',
    'definition' => 'Эта страница не существует.',
    'context' => '',
  ),
  428 => 
  array (
    'term' => 'error_no_folders',
    'definition' => 'Начните с создания папок.',
    'context' => '',
  ),
  429 => 
  array (
    'term' => 'error_no_password',
    'definition' => 'Введите пароль!',
    'context' => '',
  ),
  430 => 
  array (
    'term' => 'error_no_roles',
    'definition' => 'Создайте роли и назначьте им папки.',
    'context' => '',
  ),
  431 => 
  array (
    'term' => 'error_password_confirmation',
    'definition' => 'Пароли не совпадают',
    'context' => '',
  ),
  432 => 
  array (
    'term' => 'error_pw',
    'definition' => 'Поле \'Пароль\' обязательно к заполнению!',
    'context' => '',
  ),
  433 => 
  array (
    'term' => 'error_renawal_period_not_integer',
    'definition' => 'Период обновления должен указываться в месяцах (целое число)!',
    'context' => '',
  ),
  434 => 
  array (
    'term' => 'error_salt',
    'definition' => '&lt;b&gt;Ключ шифрования слишком длинный! Пожалуйста, не пользуйтесь Teampass пока Администратор не изменит ключ шифрования.&lt;/b&gt; Ключ шифрования должен быть не длиннее 32 символов!',
    'context' => '',
  ),
  435 => 
  array (
    'term' => 'error_tags',
    'definition' => 'Не допускается использовать знаки пунтуации в тегах! Только пробелы.',
    'context' => '',
  ),
  436 => 
  array (
    'term' => 'error_user_exists',
    'definition' => 'Такой пользователь уже существует',
    'context' => '',
  ),
  437 => 
  array (
    'term' => 'expiration_date',
    'definition' => 'Дата окончания срока',
    'context' => '',
  ),
  438 => 
  array (
    'term' => 'expir_one_month',
    'definition' => '1 месяц',
    'context' => '',
  ),
  439 => 
  array (
    'term' => 'expir_one_year',
    'definition' => '1 год',
    'context' => '',
  ),
  440 => 
  array (
    'term' => 'expir_six_months',
    'definition' => '6 месяцев',
    'context' => '',
  ),
  441 => 
  array (
    'term' => 'expir_today',
    'definition' => 'сегодня',
    'context' => '',
  ),
  442 => 
  array (
    'term' => 'files_&_images',
    'definition' => 'Файлы и картинки',
    'context' => '',
  ),
  443 => 
  array (
    'term' => 'find',
    'definition' => 'Поиск',
    'context' => '',
  ),
  444 => 
  array (
    'term' => 'find_text',
    'definition' => 'Вы искали',
    'context' => '',
  ),
  445 => 
  array (
    'term' => 'folders',
    'definition' => 'Папки',
    'context' => '',
  ),
  446 => 
  array (
    'term' => 'forbidden_groups',
    'definition' => 'Запрещенные папки',
    'context' => '',
  ),
  447 => 
  array (
    'term' => 'forgot_my_pw',
    'definition' => 'Забыли пароль?',
    'context' => '',
  ),
  448 => 
  array (
    'term' => 'forgot_my_pw_email_sent',
    'definition' => 'Письмо отправлено',
    'context' => '',
  ),
  449 => 
  array (
    'term' => 'forgot_my_pw_error_email_not_exist',
    'definition' => 'Этот email не существует!',
    'context' => '',
  ),
  450 => 
  array (
    'term' => 'forgot_my_pw_text',
    'definition' => 'Ваш пароль будет отправлен на email, связанный с вашей учетной записью.',
    'context' => '',
  ),
  451 => 
  array (
    'term' => 'forgot_pw_email_altbody_1',
    'definition' => 'Здравствуйте, Ваши учетные данные идентификации для TeamPass:',
    'context' => '',
  ),
  452 => 
  array (
    'term' => 'forgot_pw_email_body',
    'definition' => 'Здравствуйте,&lt;br&gt;&lt;br&gt;Ваш новый пароль для TeamPass :',
    'context' => '',
  ),
  453 => 
  array (
    'term' => 'forgot_pw_email_body_1',
    'definition' => 'Здравствуйте,&lt;br&gt;&lt;br&gt;Ваши учетные данные идентификации для TeamPass:&lt;br&gt;&lt;br&gt;',
    'context' => '',
  ),
  454 => 
  array (
    'term' => 'forgot_pw_email_subject',
    'definition' => 'TeamPass - Ваш пароль',
    'context' => '',
  ),
  455 => 
  array (
    'term' => 'forgot_pw_email_subject_confirm',
    'definition' => '[TeamPass] Ваш пароль. Шаг 2',
    'context' => '',
  ),
  456 => 
  array (
    'term' => 'functions',
    'definition' => 'Роли',
    'context' => '',
  ),
  457 => 
  array (
    'term' => 'function_alarm_no_group',
    'definition' => 'Эта роль не привязана ни к одной из папок!',
    'context' => '',
  ),
  458 => 
  array (
    'term' => 'generate_pdf',
    'definition' => 'Создать PDF-файл',
    'context' => '',
  ),
  459 => 
  array (
    'term' => 'generation_options',
    'definition' => 'Настройки создания',
    'context' => '',
  ),
  460 => 
  array (
    'term' => 'gestionnaire',
    'definition' => 'Менеджер',
    'context' => '',
  ),
  461 => 
  array (
    'term' => 'give_function_tip',
    'definition' => 'Создать роль',
    'context' => '',
  ),
  462 => 
  array (
    'term' => 'give_function_title',
    'definition' => 'Создать роль',
    'context' => '',
  ),
  463 => 
  array (
    'term' => 'give_new_email',
    'definition' => 'Пожалуйста, введите новый email для',
    'context' => '',
  ),
  464 => 
  array (
    'term' => 'give_new_login',
    'definition' => 'Пожалуйста, выберите учетную запись',
    'context' => '',
  ),
  465 => 
  array (
    'term' => 'give_new_pw',
    'definition' => 'Пожалуйста, введите новый пароль для',
    'context' => '',
  ),
  466 => 
  array (
    'term' => 'god',
    'definition' => 'Администратор',
    'context' => '',
  ),
  467 => 
  array (
    'term' => 'group',
    'definition' => 'Папка',
    'context' => '',
  ),
  468 => 
  array (
    'term' => 'group_parent',
    'definition' => 'Родительская папка',
    'context' => '',
  ),
  469 => 
  array (
    'term' => 'group_pw_duration',
    'definition' => 'Период обновления',
    'context' => '',
  ),
  470 => 
  array (
    'term' => 'group_pw_duration_tip',
    'definition' => 'Период обновления пароля, в месяцах. 0 - отключить обновление.',
    'context' => '',
  ),
  471 => 
  array (
    'term' => 'group_select',
    'definition' => 'Выберите папку',
    'context' => '',
  ),
  472 => 
  array (
    'term' => 'group_title',
    'definition' => 'Имя папки',
    'context' => '',
  ),
  473 => 
  array (
    'term' => 'history',
    'definition' => 'История',
    'context' => '',
  ),
  474 => 
  array (
    'term' => 'home',
    'definition' => 'Домой',
    'context' => '',
  ),
  475 => 
  array (
    'term' => 'home_personal_menu',
    'definition' => 'Личные действия',
    'context' => '',
  ),
  476 => 
  array (
    'term' => 'home_personal_saltkey',
    'definition' => 'Ваш личный ключ шифрования',
    'context' => '',
  ),
  477 => 
  array (
    'term' => 'home_personal_saltkey_button',
    'definition' => 'Задать ключ шифрования',
    'context' => '',
  ),
  478 => 
  array (
    'term' => 'home_personal_saltkey_info',
    'definition' => 'Если вы хотите использовать личные папки и элементы, то вы должны указать свой личный ключ шифрования.',
    'context' => '',
  ),
  479 => 
  array (
    'term' => 'home_personal_saltkey_label',
    'definition' => 'Введите личный ключ шифрования',
    'context' => '',
  ),
  480 => 
  array (
    'term' => 'importing_details',
    'definition' => 'Подробности',
    'context' => '',
  ),
  481 => 
  array (
    'term' => 'importing_folders',
    'definition' => 'Импорт папок',
    'context' => '',
  ),
  482 => 
  array (
    'term' => 'importing_items',
    'definition' => 'Импорт элементов',
    'context' => '',
  ),
  483 => 
  array (
    'term' => 'import_button',
    'definition' => 'Импорт',
    'context' => '',
  ),
  484 => 
  array (
    'term' => 'import_csv_anyone_can_modify_in_role_txt',
    'definition' => 'Разрешить редактирование импортируемых элементов для всех с той же ролью.',
    'context' => '',
  ),
  485 => 
  array (
    'term' => 'import_csv_anyone_can_modify_txt',
    'definition' => 'Разрешить редактирование импортируемых элементов для всех.',
    'context' => '',
  ),
  486 => 
  array (
    'term' => 'import_csv_dialog_info',
    'definition' => 'Информация: импорт должен осуществляться только из файла CSV. Обычно, файл, экспортированный из KeePass, имеет подходящую структуру.&lt;br&gt;Если вы используете файл, созданный другой программой, имейте ввиду, что структура файла CSV должна быть следующей: `Учетная запись`,`Имя`,`Пароль`,`URL`,`Комментарии`.',
    'context' => '',
  ),
  487 => 
  array (
    'term' => 'import_csv_menu_title',
    'definition' => 'Импортировать объекты из файла (CSV/KeePass XML)',
    'context' => '',
  ),
  488 => 
  array (
    'term' => 'import_error_no_file',
    'definition' => 'Вы должны выбрать файл!',
    'context' => '',
  ),
  489 => 
  array (
    'term' => 'import_error_no_read_possible',
    'definition' => 'Не удалось прочесть файл!',
    'context' => '',
  ),
  490 => 
  array (
    'term' => 'import_error_no_read_possible_kp',
    'definition' => 'Не удалось прочесть файл! Вероятно, это не файл KeePass.',
    'context' => '',
  ),
  491 => 
  array (
    'term' => 'import_keepass_dialog_info',
    'definition' => 'Информация: импорт должен осуществляться только из файла XML, экспортированным из Keepass. Обратите внимание, что при импорте не будут добавлены папки или элементы, которые уже существуют на том же уровне древовидной структуры.',
    'context' => '',
  ),
  492 => 
  array (
    'term' => 'import_keepass_to_folder',
    'definition' => 'Выберите папку',
    'context' => '',
  ),
  493 => 
  array (
    'term' => 'import_kp_finished',
    'definition' => 'Импорт из файла KeePass завершен!&lt;br&gt;По умолчанию, уровень сложности для новых папок установлен в `Средний`. Измените его на требуемый в случае необходимости.',
    'context' => '',
  ),
  494 => 
  array (
    'term' => 'import_to_folder',
    'definition' => 'Отметьте пункты, которые Вы хотите импортировать в папку:',
    'context' => '',
  ),
  495 => 
  array (
    'term' => 'index_add_one_hour',
    'definition' => 'Продлить сессию на 1 час',
    'context' => '',
  ),
  496 => 
  array (
    'term' => 'index_alarm',
    'definition' => 'ТРЕВОГА!!!',
    'context' => '',
  ),
  497 => 
  array (
    'term' => 'index_bas_pw',
    'definition' => 'Неверный пароль для этой учетной записи!',
    'context' => '',
  ),
  498 => 
  array (
    'term' => 'index_change_pw',
    'definition' => 'Измените свой пароль',
    'context' => '',
  ),
  499 => 
  array (
    'term' => 'index_change_pw_button',
    'definition' => 'Изменение',
    'context' => '',
  ),
  500 => 
  array (
    'term' => 'index_change_pw_confirmation',
    'definition' => 'Подтверждение',
    'context' => '',
  ),
  501 => 
  array (
    'term' => 'index_expiration_in',
    'definition' => 'окончание сеанса в',
    'context' => '',
  ),
  502 => 
  array (
    'term' => 'index_get_identified',
    'definition' => 'Пожалуйста, представьтесь',
    'context' => '',
  ),
  503 => 
  array (
    'term' => 'index_identify_button',
    'definition' => 'Войти',
    'context' => '',
  ),
  504 => 
  array (
    'term' => 'index_identify_you',
    'definition' => 'Пожалуйста, представьтесь',
    'context' => '',
  ),
  505 => 
  array (
    'term' => 'index_last_pw_change',
    'definition' => 'Пароль был изменен',
    'context' => '',
  ),
  506 => 
  array (
    'term' => 'index_last_seen',
    'definition' => 'Последнее подключение было',
    'context' => '',
  ),
  507 => 
  array (
    'term' => 'index_login',
    'definition' => 'Учетная запись',
    'context' => '',
  ),
  508 => 
  array (
    'term' => 'index_maintenance_mode',
    'definition' => 'Включен режим обслуживания. Вход только для администраторов.',
    'context' => '',
  ),
  509 => 
  array (
    'term' => 'index_maintenance_mode_admin',
    'definition' => 'Включен режим обслуживания. Пользователи не могут войти в TeamPass.',
    'context' => '',
  ),
  510 => 
  array (
    'term' => 'index_new_pw',
    'definition' => 'Новый пароль',
    'context' => '',
  ),
  511 => 
  array (
    'term' => 'index_password',
    'definition' => 'Пароль',
    'context' => '',
  ),
  512 => 
  array (
    'term' => 'index_pw_error_identical',
    'definition' => 'Пароли должны совпадать!',
    'context' => '',
  ),
  513 => 
  array (
    'term' => 'index_pw_expiration',
    'definition' => 'Пароль истекает через',
    'context' => '',
  ),
  514 => 
  array (
    'term' => 'index_pw_level_txt',
    'definition' => 'Сложность',
    'context' => '',
  ),
  515 => 
  array (
    'term' => 'index_refresh_page',
    'definition' => 'Обновить страницу',
    'context' => '',
  ),
  516 => 
  array (
    'term' => 'index_session_duration',
    'definition' => 'Продолжительность сессии',
    'context' => '',
  ),
  517 => 
  array (
    'term' => 'index_session_ending',
    'definition' => 'Ваша сессия закончится менее чем через 1 минуту',
    'context' => '',
  ),
  518 => 
  array (
    'term' => 'index_session_expired',
    'definition' => 'Ваша сессия истекла или вы не неверно идентифицированы!',
    'context' => '',
  ),
  519 => 
  array (
    'term' => 'index_welcome',
    'definition' => 'Добро пожаловать',
    'context' => '',
  ),
  520 => 
  array (
    'term' => 'info',
    'definition' => 'Информация',
    'context' => '',
  ),
  521 => 
  array (
    'term' => 'info_click_to_edit',
    'definition' => 'Кликните на ячейку для изменения её содержимого',
    'context' => '',
  ),
  522 => 
  array (
    'term' => 'is_admin',
    'definition' => 'Администратор',
    'context' => '',
  ),
  523 => 
  array (
    'term' => 'is_manager',
    'definition' => 'Менеджер',
    'context' => '',
  ),
  524 => 
  array (
    'term' => 'is_read_only',
    'definition' => 'Только чтение',
    'context' => '',
  ),
  525 => 
  array (
    'term' => 'items_browser_title',
    'definition' => 'Папки',
    'context' => '',
  ),
  526 => 
  array (
    'term' => 'item_copy_to_folder',
    'definition' => 'Пожалуйста, выберите папку, в которую необходимо скопировать элемент',
    'context' => '',
  ),
  527 => 
  array (
    'term' => 'item_menu_add_elem',
    'definition' => 'Создать элемент',
    'context' => '',
  ),
  528 => 
  array (
    'term' => 'item_menu_add_rep',
    'definition' => 'Создать папку',
    'context' => '',
  ),
  529 => 
  array (
    'term' => 'item_menu_add_to_fav',
    'definition' => 'Добавить в Избранное',
    'context' => '',
  ),
  530 => 
  array (
    'term' => 'item_menu_collab_disable',
    'definition' => 'Редактирование запрещено',
    'context' => '',
  ),
  531 => 
  array (
    'term' => 'item_menu_collab_enable',
    'definition' => 'Редактирование разрешено',
    'context' => '',
  ),
  532 => 
  array (
    'term' => 'item_menu_copy_elem',
    'definition' => 'Копировать элемент',
    'context' => '',
  ),
  533 => 
  array (
    'term' => 'item_menu_copy_login',
    'definition' => 'Копировать имя пользователя',
    'context' => '',
  ),
  534 => 
  array (
    'term' => 'item_menu_copy_pw',
    'definition' => 'Копировать пароль',
    'context' => '',
  ),
  535 => 
  array (
    'term' => 'item_menu_del_elem',
    'definition' => 'Удалить элемент',
    'context' => '',
  ),
  536 => 
  array (
    'term' => 'item_menu_del_from_fav',
    'definition' => 'Удалить из Избранного',
    'context' => '',
  ),
  537 => 
  array (
    'term' => 'item_menu_del_rep',
    'definition' => 'Удалить папку',
    'context' => '',
  ),
  538 => 
  array (
    'term' => 'item_menu_edi_elem',
    'definition' => 'Редактировать элемент',
    'context' => '',
  ),
  539 => 
  array (
    'term' => 'item_menu_edi_rep',
    'definition' => 'Редактировать папку',
    'context' => '',
  ),
  540 => 
  array (
    'term' => 'item_menu_find',
    'definition' => 'Поиск',
    'context' => '',
  ),
  541 => 
  array (
    'term' => 'item_menu_mask_pw',
    'definition' => 'Скрыть пароль',
    'context' => '',
  ),
  542 => 
  array (
    'term' => 'item_menu_refresh',
    'definition' => 'Обновить страницу',
    'context' => '',
  ),
  543 => 
  array (
    'term' => 'kbs',
    'definition' => 'Базы знаний',
    'context' => '',
  ),
  544 => 
  array (
    'term' => 'kb_menu',
    'definition' => 'База знаний',
    'context' => '',
  ),
  545 => 
  array (
    'term' => 'keepass_import_button_text',
    'definition' => 'Выберите XML-файл',
    'context' => '',
  ),
  546 => 
  array (
    'term' => 'label',
    'definition' => 'Метка',
    'context' => '',
  ),
  547 => 
  array (
    'term' => 'last_items_icon_title',
    'definition' => 'Показать/скрыть последние просмотренные объекты',
    'context' => '',
  ),
  548 => 
  array (
    'term' => 'last_items_title',
    'definition' => 'Последние просмотренные элементы',
    'context' => '',
  ),
  549 => 
  array (
    'term' => 'ldap_extension_not_loaded',
    'definition' => 'На сервере не активировано расширение LDAP.',
    'context' => '',
  ),
  550 => 
  array (
    'term' => 'level',
    'definition' => 'Уровень',
    'context' => '',
  ),
  551 => 
  array (
    'term' => 'link_copy',
    'definition' => 'Скопировать ссылку на элемент в буфер обмена',
    'context' => '',
  ),
  552 => 
  array (
    'term' => 'link_is_copied',
    'definition' => 'Ссылка на элемент была скопирована в буфер обмена',
    'context' => '',
  ),
  553 => 
  array (
    'term' => 'login',
    'definition' => 'Имя пользователя (если нужно)',
    'context' => '',
  ),
  554 => 
  array (
    'term' => 'login_attempts_on',
    'definition' => ' неудачных попыток входа ',
    'context' => '',
  ),
  555 => 
  array (
    'term' => 'login_copied_clipboard',
    'definition' => 'Имя пользователя скопировано в буфер обмена',
    'context' => '',
  ),
  556 => 
  array (
    'term' => 'login_copy',
    'definition' => 'Копировать имя пользователя в буфер обмена',
    'context' => '',
  ),
  557 => 
  array (
    'term' => 'logs',
    'definition' => 'Журнал',
    'context' => '',
  ),
  558 => 
  array (
    'term' => 'logs_1',
    'definition' => 'Создать отчет истечения срока действия паролей на ',
    'context' => '',
  ),
  559 => 
  array (
    'term' => 'logs_passwords',
    'definition' => 'Истечение паролей',
    'context' => '',
  ),
  560 => 
  array (
    'term' => 'maj',
    'definition' => 'Буквы верхнего регистра',
    'context' => '',
  ),
  561 => 
  array (
    'term' => 'mask_pw',
    'definition' => 'Скрыть/показать пароль',
    'context' => '',
  ),
  562 => 
  array (
    'term' => 'max_last_items',
    'definition' => 'Ограничить число последних просмотренных элементов для пользователя (10 по умолчанию)',
    'context' => '',
  ),
  563 => 
  array (
    'term' => 'menu_title_new_personal_saltkey',
    'definition' => 'Изменение Вашего личного ключа шифрования',
    'context' => '',
  ),
  564 => 
  array (
    'term' => 'minutes',
    'definition' => 'минут',
    'context' => '',
  ),
  565 => 
  array (
    'term' => 'modify_button',
    'definition' => 'Изменить',
    'context' => '',
  ),
  566 => 
  array (
    'term' => 'my_favourites',
    'definition' => 'Избранное',
    'context' => '',
  ),
  567 => 
  array (
    'term' => 'name',
    'definition' => 'Имя',
    'context' => '',
  ),
  568 => 
  array (
    'term' => 'nb_false_login_attempts',
    'definition' => 'Количество неудачных попыток входа для блокировки учетной записи (0 - бесконечно)',
    'context' => '',
  ),
  569 => 
  array (
    'term' => 'nb_folders',
    'definition' => 'Число папок',
    'context' => '',
  ),
  570 => 
  array (
    'term' => 'nb_items',
    'definition' => 'Число элементов',
    'context' => '',
  ),
  571 => 
  array (
    'term' => 'nb_items_by_page',
    'definition' => 'Элементов на странице',
    'context' => '',
  ),
  572 => 
  array (
    'term' => 'new_label',
    'definition' => 'Новая метка',
    'context' => '',
  ),
  573 => 
  array (
    'term' => 'new_role_title',
    'definition' => 'Новое имя роли',
    'context' => '',
  ),
  574 => 
  array (
    'term' => 'new_saltkey',
    'definition' => 'Новый ключ шифрования',
    'context' => '',
  ),
  575 => 
  array (
    'term' => 'new_saltkey_warning',
    'definition' => 'Пожалуйста, убедитесь, что используете исходный ключ шифрования, иначе шифрование будет нарушено. Перед любыми изменениями проверьте свой ключ шифрования!',
    'context' => '',
  ),
  576 => 
  array (
    'term' => 'new_user_title',
    'definition' => 'Добавить нового пользователя',
    'context' => '',
  ),
  577 => 
  array (
    'term' => 'no',
    'definition' => 'Нет',
    'context' => '',
  ),
  578 => 
  array (
    'term' => 'nom',
    'definition' => 'Имя',
    'context' => '',
  ),
  579 => 
  array (
    'term' => 'none',
    'definition' => 'Нет',
    'context' => '',
  ),
  580 => 
  array (
    'term' => 'none_selected_text',
    'definition' => 'Ничего не выбрано',
    'context' => '',
  ),
  581 => 
  array (
    'term' => 'not_allowed_to_see_pw',
    'definition' => 'У вас нет прав для просмотра этого элемента!',
    'context' => '',
  ),
  582 => 
  array (
    'term' => 'not_allowed_to_see_pw_is_expired',
    'definition' => 'Этот элемент устарел!',
    'context' => '',
  ),
  583 => 
  array (
    'term' => 'not_defined',
    'definition' => 'Не определено',
    'context' => '',
  ),
  584 => 
  array (
    'term' => 'no_last_items',
    'definition' => 'Нет просмотренных элементов',
    'context' => '',
  ),
  585 => 
  array (
    'term' => 'no_restriction',
    'definition' => 'Без ограничений',
    'context' => '',
  ),
  586 => 
  array (
    'term' => 'numbers',
    'definition' => 'Количество',
    'context' => '',
  ),
  587 => 
  array (
    'term' => 'number_of_used_pw',
    'definition' => 'Количество последних паролей, на совпадение с которыми проверять новый пароль.',
    'context' => '',
  ),
  588 => 
  array (
    'term' => 'ok',
    'definition' => 'ОК',
    'context' => '',
  ),
  589 => 
  array (
    'term' => 'pages',
    'definition' => 'Страниц',
    'context' => '',
  ),
  590 => 
  array (
    'term' => 'pdf_del_date',
    'definition' => 'Время создания отчета ',
    'context' => '',
  ),
  591 => 
  array (
    'term' => 'pdf_del_title',
    'definition' => 'Проверка истечения паролей',
    'context' => '',
  ),
  592 => 
  array (
    'term' => 'pdf_download',
    'definition' => 'Скачать файл',
    'context' => '',
  ),
  593 => 
  array (
    'term' => 'personal_folder',
    'definition' => 'Личная папка',
    'context' => '',
  ),
  594 => 
  array (
    'term' => 'personal_saltkey_change_button',
    'definition' => 'Изменить!',
    'context' => '',
  ),
  595 => 
  array (
    'term' => 'personal_salt_key',
    'definition' => 'Ваш личный ключ шифрования',
    'context' => '',
  ),
  596 => 
  array (
    'term' => 'personal_salt_key_empty',
    'definition' => 'Не введен личный ключ шифрования!',
    'context' => '',
  ),
  597 => 
  array (
    'term' => 'personal_salt_key_info',
    'definition' => 'Этот ключ шифрования будет использоваться для шифрования и дешифрования ваших паролей.&lt;br&gt;Он не будет сохранён в базе данных, вы - единственный человек, кому он должен быть известен.&lt;br&gt;Не потеряйте его!',
    'context' => '',
  ),
  598 => 
  array (
    'term' => 'please_update',
    'definition' => 'Пожалуйста, обновитесь!',
    'context' => '',
  ),
  599 => 
  array (
    'term' => 'print',
    'definition' => 'Печать',
    'context' => '',
  ),
  600 => 
  array (
    'term' => 'print_out_menu_title',
    'definition' => 'Распечатать список ваших элементов',
    'context' => '',
  ),
  601 => 
  array (
    'term' => 'print_out_pdf_title',
    'definition' => 'TeamPass - Список экспортированных элементов',
    'context' => '',
  ),
  602 => 
  array (
    'term' => 'print_out_warning',
    'definition' => 'Все пароли и все конфиденциальные данные будут записаны в этот файл без какого-либо шифрования! Записав файл, содержащий незашифрованные элементы и пароли, вы берете на себя полную ответственность дальнейшей защиты этого списка! Информация об экспорте в PDF будет записана в лог.',
    'context' => '',
  ),
  603 => 
  array (
    'term' => 'pw',
    'definition' => 'Пароль',
    'context' => '',
  ),
  604 => 
  array (
    'term' => 'pw_change',
    'definition' => 'Изменить пароль',
    'context' => '',
  ),
  605 => 
  array (
    'term' => 'pw_changed',
    'definition' => 'Пароль изменен!',
    'context' => '',
  ),
  606 => 
  array (
    'term' => 'pw_copied_clipboard',
    'definition' => 'Пароль скопирован в буфер обмена',
    'context' => '',
  ),
  607 => 
  array (
    'term' => 'pw_copy_clipboard',
    'definition' => 'Копировать пароль в буфер обмена',
    'context' => '',
  ),
  608 => 
  array (
    'term' => 'pw_generate',
    'definition' => 'Создать',
    'context' => '',
  ),
  609 => 
  array (
    'term' => 'pw_is_expired_-_update_it',
    'definition' => 'Этот элемент устарел! Вы должны сменить его пароль.',
    'context' => '',
  ),
  610 => 
  array (
    'term' => 'pw_life_duration',
    'definition' => 'Время жизни пароля пользователя, дней (0 - бесконечно)',
    'context' => '',
  ),
  611 => 
  array (
    'term' => 'pw_recovery_asked',
    'definition' => 'Вы запросили восстановление пароля',
    'context' => '',
  ),
  612 => 
  array (
    'term' => 'pw_recovery_button',
    'definition' => 'Отправить мне новый пароль',
    'context' => '',
  ),
  613 => 
  array (
    'term' => 'pw_recovery_info',
    'definition' => 'После нажатия кнопки Далее Вам будет отправлено письмо с Вашим новым паролем.',
    'context' => '',
  ),
  614 => 
  array (
    'term' => 'pw_used',
    'definition' => 'Такой пароль уже использовался!',
    'context' => '',
  ),
  615 => 
  array (
    'term' => 'readme_open',
    'definition' => 'Смотреть файл полностью',
    'context' => '',
  ),
  616 => 
  array (
    'term' => 'read_only_account',
    'definition' => 'Только чтение',
    'context' => '',
  ),
  617 => 
  array (
    'term' => 'refresh_matrix',
    'definition' => 'Обновить матрицу',
    'context' => '',
  ),
  618 => 
  array (
    'term' => 'renewal_menu',
    'definition' => 'Устаревающие элементы',
    'context' => '',
  ),
  619 => 
  array (
    'term' => 'renewal_needed_pdf_title',
    'definition' => 'Список элементов, которые необходимо обновить',
    'context' => '',
  ),
  620 => 
  array (
    'term' => 'renewal_selection_text',
    'definition' => 'Список элементов, которые устаревают:',
    'context' => '',
  ),
  621 => 
  array (
    'term' => 'restore',
    'definition' => 'Восстановление',
    'context' => '',
  ),
  622 => 
  array (
    'term' => 'restricted_to',
    'definition' => 'Ограничено для',
    'context' => '',
  ),
  623 => 
  array (
    'term' => 'restricted_to_roles',
    'definition' => 'Разрешить назначать ограничения для ролей',
    'context' => '',
  ),
  624 => 
  array (
    'term' => 'rights_matrix',
    'definition' => 'Матрица прав пользователей',
    'context' => '',
  ),
  625 => 
  array (
    'term' => 'roles',
    'definition' => 'Роли',
    'context' => '',
  ),
  626 => 
  array (
    'term' => 'role_cannot_modify_all_seen_items',
    'definition' => 'Не разрешать этой роли изменять все доступные ей элементы (обычная настройка)',
    'context' => '',
  ),
  627 => 
  array (
    'term' => 'role_can_modify_all_seen_items',
    'definition' => 'Разрешать этой роли изменять все доступные ей элементы (небезопасная настройка)',
    'context' => '',
  ),
  628 => 
  array (
    'term' => 'root',
    'definition' => 'root',
    'context' => '',
  ),
  629 => 
  array (
    'term' => 'save_button',
    'definition' => 'Сохранить',
    'context' => '',
  ),
  630 => 
  array (
    'term' => 'secure',
    'definition' => 'Спецсимволы',
    'context' => '',
  ),
  631 => 
  array (
    'term' => 'see_logs',
    'definition' => 'Открыть журнал',
    'context' => '',
  ),
  632 => 
  array (
    'term' => 'select_folders',
    'definition' => 'Выберите папки',
    'context' => '',
  ),
  633 => 
  array (
    'term' => 'select_language',
    'definition' => 'Выберите язык',
    'context' => '',
  ),
  634 => 
  array (
    'term' => 'send',
    'definition' => 'Отправить',
    'context' => '',
  ),
  635 => 
  array (
    'term' => 'settings_anyone_can_modify',
    'definition' => 'Добавить для каждого элемета опцию, позволяющую любому пользователю изменять этот элемент',
    'context' => '',
  ),
  636 => 
  array (
    'term' => 'settings_anyone_can_modify_tip',
    'definition' => 'Когда опция активна, становится доступен флажок в окне редактирования элемента, который позволяет создателю элемента разрешить любому пользователю изменение данного элемента.',
    'context' => '',
  ),
  637 => 
  array (
    'term' => 'settings_default_language',
    'definition' => 'Язык по умолчанию',
    'context' => '',
  ),
  638 => 
  array (
    'term' => 'settings_kb',
    'definition' => 'Включить Базу Знаний (бета)',
    'context' => '',
  ),
  639 => 
  array (
    'term' => 'settings_kb_tip',
    'definition' => 'Добавляет страницу, где Вы можете создать Базу Знаний',
    'context' => '',
  ),
  640 => 
  array (
    'term' => 'settings_ldap_domain',
    'definition' => 'Суффикс LDAP учетных записей Вашего домена',
    'context' => '',
  ),
  641 => 
  array (
    'term' => 'settings_ldap_domain_controler',
    'definition' => 'Список контроллеров домена LDAP',
    'context' => '',
  ),
  642 => 
  array (
    'term' => 'settings_ldap_domain_controler_tip',
    'definition' => 'Укажите несколько контроллеров, если хотите, чтобы производилась балансировка LDAP-запросов.&lt;br&gt;Разделитель - запятая(,) Напр.: domain_1,domain_2,domain_3',
    'context' => '',
  ),
  643 => 
  array (
    'term' => 'settings_ldap_domain_dn',
    'definition' => 'LDAP base dn Вашего домена',
    'context' => '',
  ),
  644 => 
  array (
    'term' => 'settings_ldap_mode',
    'definition' => 'Включить LDAP-аутентификацию',
    'context' => '',
  ),
  645 => 
  array (
    'term' => 'settings_ldap_mode_tip',
    'definition' => 'Включайте только если у Вас есть LDAP-сервер и Вы хотите, чтобы пользователи осуществляли вход в TeamPass через них.',
    'context' => '',
  ),
  646 => 
  array (
    'term' => 'settings_ldap_ssl',
    'definition' => 'Использовать LDAP через SSL (LDAPS)',
    'context' => '',
  ),
  647 => 
  array (
    'term' => 'settings_ldap_tls',
    'definition' => 'Использовать LDAP через TLS',
    'context' => '',
  ),
  648 => 
  array (
    'term' => 'settings_log_accessed',
    'definition' => 'Включить протоколирование просмотра элементов',
    'context' => '',
  ),
  649 => 
  array (
    'term' => 'settings_log_connections',
    'definition' => 'Включить протоколирование всех подключений пользователей к базе данных',
    'context' => '',
  ),
  650 => 
  array (
    'term' => 'settings_maintenance_mode',
    'definition' => 'Перевести TeamPass в режим обслуживания',
    'context' => '',
  ),
  651 => 
  array (
    'term' => 'settings_maintenance_mode_tip',
    'definition' => 'В этом режиме вход разрешен только для Администраторов.',
    'context' => '',
  ),
  652 => 
  array (
    'term' => 'settings_manager_edit',
    'definition' => 'Менеджеры могут редактировать и удалять Элементы, разрешенные им к просмотру',
    'context' => '',
  ),
  653 => 
  array (
    'term' => 'settings_printing',
    'definition' => 'Разрешить экспорт в PDF-файл',
    'context' => '',
  ),
  654 => 
  array (
    'term' => 'settings_printing_tip',
    'definition' => 'Когда включено, на домашней странице пользователей появится кнопка, которая позволить экспортировать в PDF-файл элементы, которые он может видеть. Обратите внимание, что пароли будут сохранены в незашифрованном виде!',
    'context' => '',
  ),
  655 => 
  array (
    'term' => 'settings_restricted_to',
    'definition' => 'Включить функцию ограничения доступа на уровне элементов',
    'context' => '',
  ),
  656 => 
  array (
    'term' => 'settings_richtext',
    'definition' => 'Разрешить форматирование текста в описании элемента',
    'context' => '',
  ),
  657 => 
  array (
    'term' => 'settings_richtext_tip',
    'definition' => 'Разрешает использование BB-кода в поле описания элемента.',
    'context' => '',
  ),
  658 => 
  array (
    'term' => 'settings_send_stats',
    'definition' => 'Отправлять автору программы ежемесячную статистику использования TeamPass',
    'context' => '',
  ),
  659 => 
  array (
    'term' => 'settings_send_stats_tip',
    'definition' => 'Эти статистические данные являются полностью анонимными!&lt;br&gt;Ваш IP-адрес не передается, будут отправлены только следующие данные: количество элементов, папок, пользователей, версия TeamPass, включены ли личные папки, включен ли LDAP.&lt;br&gt;Большое спасибо, если Вы включите отправку статистических данных. Благодаря этому вы поможете дальнейшему развитию TeamPass.',
    'context' => '',
  ),
  660 => 
  array (
    'term' => 'settings_show_description',
    'definition' => 'Отображать описание элементов в общем списке',
    'context' => '',
  ),
  661 => 
  array (
    'term' => 'show',
    'definition' => 'Показать',
    'context' => '',
  ),
  662 => 
  array (
    'term' => 'show_help',
    'definition' => 'Показать справку',
    'context' => '',
  ),
  663 => 
  array (
    'term' => 'show_last_items',
    'definition' => 'Показывать блок последних просмотренных элементов на главной странице',
    'context' => '',
  ),
  664 => 
  array (
    'term' => 'size',
    'definition' => 'Размер',
    'context' => '',
  ),
  665 => 
  array (
    'term' => 'start_upload',
    'definition' => 'Начать загрузку файлов',
    'context' => '',
  ),
  666 => 
  array (
    'term' => 'sub_group_of',
    'definition' => 'Родительская папка',
    'context' => '',
  ),
  667 => 
  array (
    'term' => 'support_page',
    'definition' => 'По всем вопросам обращайтесь &lt;a href=\'https://sourceforge.net/projects/communitypasswo/forums\' target=\'_blank\'&gt;&lt;u&gt;по ссылке&lt;/u&gt;&lt;/a&gt;.',
    'context' => '',
  ),
  668 => 
  array (
    'term' => 'symbols',
    'definition' => 'Символы',
    'context' => '',
  ),
  669 => 
  array (
    'term' => 'tags',
    'definition' => 'Теги',
    'context' => '',
  ),
  670 => 
  array (
    'term' => 'thku',
    'definition' => 'Спасибо за использование TeamPass!',
    'context' => '',
  ),
  671 => 
  array (
    'term' => 'timezone_selection',
    'definition' => 'Часовой пояс',
    'context' => '',
  ),
  672 => 
  array (
    'term' => 'time_format',
    'definition' => 'Формат времени',
    'context' => '',
  ),
  673 => 
  array (
    'term' => 'uncheck_all_text',
    'definition' => 'Снять все',
    'context' => '',
  ),
  674 => 
  array (
    'term' => 'unlock_user',
    'definition' => 'Пользователь заблокирован. Разблокировать эту учетную запись?',
    'context' => '',
  ),
  675 => 
  array (
    'term' => 'update_needed_mode_admin',
    'definition' => 'Рекомендуется обновить вашу инсталляцию TeamPass. Для обновления кликнить &lt;a href=\'install/upgrade.php\'&gt;сюда&lt;/a&gt;',
    'context' => '',
  ),
  676 => 
  array (
    'term' => 'uploaded_files',
    'definition' => 'Существующие файлы',
    'context' => '',
  ),
  677 => 
  array (
    'term' => 'upload_button_text',
    'definition' => 'Обзор',
    'context' => '',
  ),
  678 => 
  array (
    'term' => 'upload_files',
    'definition' => 'Загрузить новые файлы',
    'context' => '',
  ),
  679 => 
  array (
    'term' => 'url',
    'definition' => 'URL',
    'context' => '',
  ),
  680 => 
  array (
    'term' => 'url_copied',
    'definition' => 'URL скопирован в буфер обмена!',
    'context' => '',
  ),
  681 => 
  array (
    'term' => 'used_pw',
    'definition' => 'Пароль',
    'context' => '',
  ),
  682 => 
  array (
    'term' => 'user',
    'definition' => 'Пользователь',
    'context' => '',
  ),
  683 => 
  array (
    'term' => 'users',
    'definition' => 'Пользователи',
    'context' => '',
  ),
  684 => 
  array (
    'term' => 'users_online',
    'definition' => 'пользователей в сети',
    'context' => '',
  ),
  685 => 
  array (
    'term' => 'user_action',
    'definition' => 'Действия с пользователем',
    'context' => '',
  ),
  686 => 
  array (
    'term' => 'user_alarm_no_function',
    'definition' => 'У этого пользователя нет ролей!',
    'context' => '',
  ),
  687 => 
  array (
    'term' => 'user_del',
    'definition' => 'Удалить учетную запись',
    'context' => '',
  ),
  688 => 
  array (
    'term' => 'user_lock',
    'definition' => 'Блокировать пользователя',
    'context' => '',
  ),
  689 => 
  array (
    'term' => 'version',
    'definition' => 'Текущая версия',
    'context' => '',
  ),
  690 => 
  array (
    'term' => 'views_confirm_items_deletion',
    'definition' => 'Вы действительно хотите удалить выбранные объекты из базы данных?',
    'context' => '',
  ),
  691 => 
  array (
    'term' => 'views_confirm_restoration',
    'definition' => 'Пожалуйста, подтвердите восстановление этого объекта',
    'context' => '',
  ),
  692 => 
  array (
    'term' => 'visibility',
    'definition' => 'Видимость',
    'context' => '',
  ),
  693 => 
  array (
    'term' => 'warning_screen_height',
    'definition' => 'ВНИМАНИЕ: высота экрана недостаточна для отображения списка элементов!',
    'context' => '',
  ),
  694 => 
  array (
    'term' => 'yes',
    'definition' => 'Да',
    'context' => '',
  ),
  695 => 
  array (
    'term' => 'your_version',
    'definition' => 'Ваша версия',
    'context' => '',
  ),
  696 => 
  array (
    'term' => 'disconnect_all_users_sure',
    'definition' => 'Вы выбрали отключение всех пользователей. Вы уверены?',
    'context' => '',
  ),
  697 => 
  array (
    'term' => 'Test the Email configuration',
    'definition' => 'Процедура тестирования отправит письмо на указанный адрес. Если вы не получите его, проверьте ваши аутентификацинные данные.',
    'context' => '',
  ),
  698 => 
  array (
    'term' => 'url_copied_clipboard',
    'definition' => 'URL скопирован в буфер обмена',
    'context' => '',
  ),
  699 => 
  array (
    'term' => 'url_copy',
    'definition' => 'Копировать URL в буфер обмена',
    'context' => '',
  ),
  700 => 
  array (
    'term' => 'one_time_item_view',
    'definition' => 'Одноразовая ссылка',
    'context' => '',
  ),
  701 => 
  array (
    'term' => 'one_time_view_item_url_box',
    'definition' => 'Поделиться одноразовой ссылкой с человеком, которому Вы доверяете:&lt;br&gt;&lt;br&gt;#URL#&lt;br&gt;&lt;br&gt;Помните, что эта ссылка будет доступна только один раз до #DAY#',
    'context' => '',
  ),
  702 => 
  array (
    'term' => 'admin_api',
    'definition' => 'API',
    'context' => '',
  ),
  703 => 
  array (
    'term' => 'settings_api',
    'definition' => 'Разрешить доступ к элементам через API TeamPass',
    'context' => '',
  ),
  704 => 
  array (
    'term' => 'settings_api_tip',
    'definition' => 'API позволяет обращаться к элементам из сторонних приложений в формате JSON.',
    'context' => '',
  ),
  705 => 
  array (
    'term' => 'settings_api_keys_list',
    'definition' => 'Список ключей',
    'context' => '',
  ),
  706 => 
  array (
    'term' => 'settings_api_keys_list_tip',
    'definition' => 'Ключи, с которыми разрешен доступ к TeamPass. Без действующего ключа доступ к TeamPass невозможен. Будьте очень осторожны при публикации этих ключей!',
    'context' => '',
  ),
  707 => 
  array (
    'term' => 'settings_api_generate_key',
    'definition' => 'Создать ключ',
    'context' => '',
  ),
  708 => 
  array (
    'term' => 'settings_api_delete_key',
    'definition' => 'Удалить ключ',
    'context' => '',
  ),
  709 => 
  array (
    'term' => 'settings_api_add_key',
    'definition' => 'Добавить ключ',
    'context' => '',
  ),
  710 => 
  array (
    'term' => 'settings_api_key',
    'definition' => 'Ключ',
    'context' => '',
  ),
  711 => 
  array (
    'term' => 'settings_api_key_label',
    'definition' => 'Метка',
    'context' => '',
  ),
  712 => 
  array (
    'term' => 'settings_api_ip_whitelist',
    'definition' => 'Белый список разрешенных IP-адресов',
    'context' => '',
  ),
  713 => 
  array (
    'term' => 'settings_api_ip_whitelist_tip',
    'definition' => 'Если список пустой, то все IP-адреса являются разрешенными',
    'context' => '',
  ),
  714 => 
  array (
    'term' => 'settings_api_add_ip',
    'definition' => 'Добавить IP-адрес',
    'context' => '',
  ),
  715 => 
  array (
    'term' => 'settings_api_db_intro',
    'definition' => 'Назначить метку для ключа (не обязательно, но рекомендуется)',
    'context' => '',
  ),
  716 => 
  array (
    'term' => 'error_too_long',
    'definition' => 'Ошибка: строка слишком длинная!',
    'context' => '',
  ),
  717 => 
  array (
    'term' => 'settings_api_ip',
    'definition' => 'IP',
    'context' => '',
  ),
  718 => 
  array (
    'term' => 'settings_api_db_intro_ip',
    'definition' => 'Назначить метку для нового IP-адреса',
    'context' => '',
  ),
  719 => 
  array (
    'term' => 'settings_api_world_open',
    'definition' => 'Не заданы IP-адреса. Доступ будет открыт для всех (может быть небезопасно).',
    'context' => '',
  ),
  720 => 
  array (
    'term' => 'subfolder_rights_as_parent',
    'definition' => 'Новые подпапки наследуют права доступа родительской папки',
    'context' => '',
  ),
  721 => 
  array (
    'term' => 'subfolder_rights_as_parent_tip',
    'definition' => 'Если эта опция отключена, то наследуются права доступа согласно ролям создателя. При включении опции права подпапок будут наследоваться от родительской папки.',
    'context' => '',
  ),
  722 => 
  array (
    'term' => 'show_only_accessible_folders_tip',
    'definition' => 'По умолчанию пользователь видит полное дерево папок, даже если он имеет доступ не ко всем папкам. Эта опция позволяет отключить показ папок, к которым пользователь не имеет доступа.',
    'context' => '',
  ),
  723 => 
  array (
    'term' => 'show_only_accessible_folders',
    'definition' => 'Показывать в дереве папок только те папки, к которым пользователь имеет доступ',
    'context' => '',
  ),
  724 => 
  array (
    'term' => 'suggestion',
    'definition' => 'Предложение элемента',
    'context' => '',
  ),
  725 => 
  array (
    'term' => 'suggestion_add',
    'definition' => 'Подать предложение для элемента',
    'context' => '',
  ),
  726 => 
  array (
    'term' => 'comment',
    'definition' => 'Комментарий',
    'context' => '',
  ),
  727 => 
  array (
    'term' => 'suggestion_error_duplicate',
    'definition' => 'Аналогичное предложение уже поступило!',
    'context' => '',
  ),
  728 => 
  array (
    'term' => 'suggestion_delete_confirm',
    'definition' => 'Пожалуйста, подтвердите удаление Предложения',
    'context' => '',
  ),
  729 => 
  array (
    'term' => 'suggestion_validate_confirm',
    'definition' => 'Пожалуйста, подтвердите одобрение Предложения',
    'context' => '',
  ),
  730 => 
  array (
    'term' => 'suggestion_validate',
    'definition' => 'Вы решили добавить это предложение в список элементов. Пожалуйста, подтвердите.',
    'context' => '',
  ),
  731 => 
  array (
    'term' => 'suggestion_error_cannot_add',
    'definition' => 'Ошибка: Предложение не может быть добавлено как элемент!',
    'context' => '',
  ),
  732 => 
  array (
    'term' => 'suggestion_is_duplicate',
    'definition' => 'Внимание: это предложение содержит существующий элемент (с такой же меткой и папкой). Если Вы нажмете "Добавить", существующий элемент будет обновлен данными из этого предложения.',
    'context' => '',
  ),
  733 => 
  array (
    'term' => 'suggestion_menu',
    'definition' => 'Предложения',
    'context' => '',
  ),
  734 => 
  array (
    'term' => 'settings_suggestion',
    'definition' => 'Разрешить вносить предложения пользователям с правами "только чтение"',
    'context' => '',
  ),
  735 => 
  array (
    'term' => 'settings_suggestion_tip',
    'definition' => 'Предложения позволяют пользователям имеющим права "только чтение" отправлять заявки на создание или изменение элементов. Предложения утверждаются Администраторами или Менеджерами.',
    'context' => '',
  ),
  736 => 
  array (
    'term' => 'imported_via_api',
    'definition' => 'API',
    'context' => '',
  ),
  737 => 
  array (
    'term' => 'settings_ldap_bind_dn',
    'definition' => 'DN для подключения',
    'context' => '',
  ),
  738 => 
  array (
    'term' => 'settings_ldap_bind_passwd',
    'definition' => 'Пароль для подключения',
    'context' => '',
  ),
  739 => 
  array (
    'term' => 'settings_ldap_search_base',
    'definition' => 'База поиска',
    'context' => '',
  ),
  740 => 
  array (
    'term' => 'settings_ldap_bind_dn_tip',
    'definition' => 'Укажите DN пользователя, который может подключаться к базе и осуществлять поиск',
    'context' => '',
  ),
  741 => 
  array (
    'term' => 'settings_ldap_bind_passwd_tip',
    'definition' => 'Укажите пароль пользователя, который может подключаться к базе и осуществлять поиск',
    'context' => '',
  ),
  742 => 
  array (
    'term' => 'settings_ldap_search_base_tip',
    'definition' => 'Ветка, от которой начинается поиск',
    'context' => '',
  ),
  743 => 
  array (
    'term' => 'old_saltkey',
    'definition' => 'Старый ключ шифрования',
    'context' => '',
  ),
  744 => 
  array (
    'term' => 'define_old_saltkey',
    'definition' => 'Я хочу указать старый ключ шифрования (необязательно)',
    'context' => '',
  ),
  745 => 
  array (
    'term' => 'admin_email_server_url_tip',
    'definition' => 'Укажите URL, который будет использоваться для ссылок в письмах, если не хотите, чтобы использовался URL по умолчанию.',
    'context' => '',
  ),
  746 => 
  array (
    'term' => 'admin_email_server_url',
    'definition' => 'URL сервера для ссылок в письмах',
    'context' => '',
  ),
  747 => 
  array (
    'term' => 'generated_pw',
    'definition' => 'Созданный пароль',
    'context' => '',
  ),
  748 => 
  array (
    'term' => 'enable_email_notification_on_user_pw_change',
    'definition' => 'Отправлять письмо пользователю при изменении его пароля',
    'context' => '',
  ),
  749 => 
  array (
    'term' => 'settings_otv_expiration_period',
    'definition' => 'Время истечения одноразовых элементов (дней)',
    'context' => '',
  ),
  750 => 
  array (
    'term' => 'change_right_access',
    'definition' => 'Назначить права доступа',
    'context' => '',
  ),
  751 => 
  array (
    'term' => 'write',
    'definition' => 'Запись',
    'context' => '',
  ),
  752 => 
  array (
    'term' => 'read',
    'definition' => 'Чтение',
    'context' => '',
  ),
  753 => 
  array (
    'term' => 'no_access',
    'definition' => 'Нет доступа',
    'context' => '',
  ),
  754 => 
  array (
    'term' => 'right_types_label',
    'definition' => 'Назначьте выбранной группе пользователей тип доступа для этой папки',
    'context' => '',
  ),
  755 => 
  array (
    'term' => 'groups',
    'definition' => 'Папки',
    'context' => '',
  ),
  756 => 
  array (
    'term' => 'duplicate',
    'definition' => 'Дублировать',
    'context' => '',
  ),
  757 => 
  array (
    'term' => 'duplicate_title_in_same_folder',
    'definition' => 'Элемент с аналогичным именем существуют в текущей папке! Одинаковые имена элементов запрещены!',
    'context' => '',
  ),
  758 => 
  array (
    'term' => 'duplicate_item_in_folder',
    'definition' => 'Разрешить элементы с аналогичными метками в общей папке',
    'context' => '',
  ),
  759 => 
  array (
    'term' => 'find_message',
    'definition' => '&lt;i class="fa fa-info-circle"&gt;&lt;/i&gt; %X% найдено объектов	
',
    'context' => '',
  ),
  760 => 
  array (
    'term' => 'settings_roles_allowed_to_print',
    'definition' => 'Определить роли которым разрешено печатать элементы',
    'context' => '',
  ),
  761 => 
  array (
    'term' => 'settings_roles_allowed_to_print_tip',
    'definition' => 'Выбранным ролям будет разрешено печатать элементы в файл',
    'context' => '',
  ),
  762 => 
  array (
    'term' => 'user_profile_dialogbox_menu',
    'definition' => 'Ваша TeamPass информация',
    'context' => '',
  ),
  763 => 
  array (
    'term' => 'admin_email_security',
    'definition' => 'SMTP безопасность',
    'context' => '',
  ),
  764 => 
  array (
    'term' => 'alert_page_will_reload',
    'definition' => 'Сейчас страница будет перезагружена',
    'context' => '',
  ),
  765 => 
  array (
    'term' => 'csv_import_items_selection',
    'definition' => 'Выберите элементы для импорта',
    'context' => '',
  ),
  766 => 
  array (
    'term' => 'csv_import_options',
    'definition' => 'Выберите параметры импорта',
    'context' => '',
  ),
  767 => 
  array (
    'term' => 'file_protection_password',
    'definition' => 'Указать файл паролей',
    'context' => '',
  ),
  768 => 
  array (
    'term' => 'button_export_file',
    'definition' => 'Экспорт элементов',
    'context' => '',
  ),
  769 => 
  array (
    'term' => 'error_export_format_not_selected',
    'definition' => 'Требуемый формат для экспорта файла',
    'context' => '',
  ),
  770 => 
  array (
    'term' => 'select_file_format',
    'definition' => 'Выберите формат файла',
    'context' => '',
  ),
  771 => 
  array (
    'term' => 'button_offline_generate',
    'definition' => 'Создание файла для работы в автономном режим',
    'context' => '',
  ),
  772 => 
  array (
    'term' => 'upload_new_avatar',
    'definition' => 'Выберите файл аватара в формате PNG',
    'context' => '',
  ),
  773 => 
  array (
    'term' => 'expand',
    'definition' => 'Развернуть',
    'context' => '',
  ),
  774 => 
  array (
    'term' => 'collapse',
    'definition' => 'Свернуть',
    'context' => '',
  ),
  775 => 
  array (
    'term' => 'error_file_is_missing',
    'definition' => 'Ошибка: файл не найден!',
    'context' => '',
  ),
  776 => 
  array (
    'term' => 'click_to_change',
    'definition' => 'Нажмите для изменения',
    'context' => '',
  ),
  777 => 
  array (
    'term' => 'settings_ldap_user_attribute',
    'definition' => 'Пользовательское поле поиска',
    'context' => '',
  ),
  778 => 
  array (
    'term' => 'settings_ldap_user_attribute_tip',
    'definition' => 'LDAP-поле для поиска имени пользователя',
    'context' => '',
  ),
  779 => 
  array (
    'term' => 'user_ga_code_sent_by_email',
    'definition' => 'Новый код Google Authenticator был отправлен на ваш email.',
    'context' => '',
  ),
  780 => 
  array (
    'term' => 'log_user_initial_pwd_changed',
    'definition' => 'Первоначальный пароль задан',
    'context' => '',
  ),
  781 => 
  array (
    'term' => 'log_user_email_changed',
    'definition' => 'Email пользователя изменен на ',
    'context' => '',
  ),
  782 => 
  array (
    'term' => 'log_user_created',
    'definition' => 'Учетная запись пользователя создана',
    'context' => '',
  ),
  783 => 
  array (
    'term' => 'log_user_locked',
    'definition' => 'Пользователь заблокирован',
    'context' => '',
  ),
  784 => 
  array (
    'term' => 'log_user_unlocked',
    'definition' => 'Пользователь разблокирован',
    'context' => '',
  ),
  785 => 
  array (
    'term' => 'log_user_pwd_changed',
    'definition' => 'Пароль пользователя изменен',
    'context' => '',
  ),
  786 => 
  array (
    'term' => 'edit_user',
    'definition' => 'Редактировать пользователя',
    'context' => '',
  ),
  787 => 
  array (
    'term' => 'pf_change_encryption',
    'definition' => 'Алгоритм шифрования изменился, и ваши личные пароли должны быть заново зашифрованы. Вам необходимо запустить этот процесс, чтобы использовать ваши пароли. Этот процесс может занять несколько минут в зависимости от количества Ваших элементов.',
    'context' => '',
  ),
  788 => 
  array (
    'term' => 'operation_encryption_done',
    'definition' => 'Шифрование выполнено. Вы можете закрыть этот окно.',
    'context' => '',
  ),
  789 => 
  array (
    'term' => 'show_password',
    'definition' => 'Показать пароль',
    'context' => '',
  ),
  790 => 
  array (
    'term' => 'change_password',
    'definition' => 'Изменить пароль',
    'context' => '',
  ),
  791 => 
  array (
    'term' => 'pf_sk_set',
    'definition' => 'Ваш личный ключ шифрования задан корректно, Вы можете нажать кнопку \'Начать\'',
    'context' => '',
  ),
  792 => 
  array (
    'term' => 'pf_sk_not_set',
    'definition' => 'Ваш личный ключ шифрования НЕ задан! Пожалуйста, введите его.',
    'context' => '',
  ),
  793 => 
  array (
    'term' => 'upgrade_needed',
    'definition' => 'Необходимо обновление',
    'context' => '',
  ),
  794 => 
  array (
    'term' => 'item_menu_mov_rep',
    'definition' => 'Переместить папку',
    'context' => '',
  ),
  795 => 
  array (
    'term' => 'settings_default_session_expiration_time',
    'definition' => 'По умолчанию задержка до окончания сессии',
    'context' => '',
  ),
  796 => 
  array (
    'term' => 'duo_message',
    'definition' => 'Проверка DUOSecurity закончена. Ваши учетные данные передаются в TeamPass.&lt;br&gt;Пожалуйста, подождите... страница будет перезагружена, как только аутентификация завершится.',
    'context' => 'DUO Security checks are now done. Sending your credentials to Teampass.<br />Please wait ... the page will be reloaded once authentication process will be done.',
  ),
  797 => 
  array (
    'term' => 'duo_loading_iframe',
    'definition' => 'Фрейм DUOSecurity аутентификации сейчас будет загружен. Пожалуйста, подождите.',
    'context' => '',
  ),
  798 => 
  array (
    'term' => 'settings_duo',
    'definition' => 'Использовать DUOSecurity для двухфакторной аутентификации пользователей',
    'context' => '',
  ),
  799 => 
  array (
    'term' => 'settings_duo_tip',
    'definition' => 'Двухфакторная аутентификация пользователей может быть обеспечена с помощью DUOSecurity.com. Эта библиотека обеспечивает высокий уровень безопасности, связанной с аутентификацией пользователя.',
    'context' => '',
  ),
  800 => 
  array (
    'term' => 'admin_duo_akey',
    'definition' => 'AKEY',
    'context' => '',
  ),
  801 => 
  array (
    'term' => 'admin_duo_ikey',
    'definition' => 'IKEY',
    'context' => '',
  ),
  802 => 
  array (
    'term' => 'admin_duo_skey',
    'definition' => 'SKEY',
    'context' => '',
  ),
  803 => 
  array (
    'term' => 'admin_duo_host',
    'definition' => 'HOST',
    'context' => '',
  ),
  804 => 
  array (
    'term' => 'generate_random_key',
    'definition' => 'Создать стойкий случайный ключ',
    'context' => '',
  ),
  805 => 
  array (
    'term' => 'duo_save_sk_file',
    'definition' => 'Сохранить данные в файле sk.php',
    'context' => '',
  ),
  806 => 
  array (
    'term' => 'settings_duo_explanation',
    'definition' => 'Эти учетные данные выдаются в веб-приложении, которое вы создаете для TeamPass со страницы администрирования DUOSecurity.&lt;br&gt;При нажатии на кнопку Сохранить они будут сохранены в файле sk.php.',
    'context' => '',
  ),
  807 => 
  array (
    'term' => 'admin_duo_intro',
    'definition' => 'Заполнить следующие поля ожидаемыми данными',
    'context' => '',
  ),
  808 => 
  array (
    'term' => 'admin_duo_stored',
    'definition' => 'Учетные данные успешно сохранены!',
    'context' => '',
  ),
  809 => 
  array (
    'term' => 'user_not_exists',
    'definition' => 'Пользователь не существует!',
    'context' => '',
  ),
  810 => 
  array (
    'term' => 'dialog_admin_user_edit_title',
    'definition' => 'Редактирование учетной записи пользователя',
    'context' => '',
  ),
  811 => 
  array (
    'term' => 'user_info_delete',
    'definition' => 'Пожалуйста, подтвердите удаление учетной записи.',
    'context' => '',
  ),
  812 => 
  array (
    'term' => 'user_info_delete_warning',
    'definition' => 'После нажатия кнопки Сохранить Вы удалите эту учетную запись из TeamPass.&lt;br&gt;Это действие необратимо!',
    'context' => '',
  ),
  813 => 
  array (
    'term' => 'edit',
    'definition' => 'Редактировать',
    'context' => '',
  ),
  814 => 
  array (
    'term' => 'user_info_locked',
    'definition' => 'Пользователь заблокирован',
    'context' => '',
  ),
  815 => 
  array (
    'term' => 'user_info_unlock_question',
    'definition' => 'Разблокировать учетную запись?',
    'context' => '',
  ),
  816 => 
  array (
    'term' => 'user_info_lock_question',
    'definition' => 'Заблокировать учетную запись?',
    'context' => '',
  ),
  817 => 
  array (
    'term' => 'user_info_delete_question',
    'definition' => 'Удалить учетную запись?',
    'context' => '',
  ),
  818 => 
  array (
    'term' => 'user_info_active',
    'definition' => 'Пользователь включен',
    'context' => '',
  ),
  819 => 
  array (
    'term' => 'settings_ldap_domain_posix',
    'definition' => 'Суффикс LDAP учетных записей Вашего домена',
    'context' => '',
  ),
  820 => 
  array (
    'term' => 'refresh',
    'definition' => 'Обновить',
    'context' => '',
  ),
  821 => 
  array (
    'term' => 'loading',
    'definition' => 'Загрузка',
    'context' => '',
  ),
  822 => 
  array (
    'term' => 'at_password_shown',
    'definition' => 'Пароль показан',
    'context' => '',
  ),
  823 => 
  array (
    'term' => 'at_password_copied',
    'definition' => 'Пароль скопирован',
    'context' => '',
  ),
  824 => 
  array (
    'term' => 'search_results',
    'definition' => 'Результаты поиска',
    'context' => '',
  ),
  825 => 
  array (
    'term' => 'searching',
    'definition' => 'Поиск...',
    'context' => '',
  ),
  826 => 
  array (
    'term' => 'search_tag_results',
    'definition' => 'Результаты поиска по тегу',
    'context' => '',
  ),
  827 => 
  array (
    'term' => 'searching_tag',
    'definition' => 'Поиск по тегу',
    'context' => '',
  ),
  828 => 
  array (
    'term' => 'list_items_with_tag',
    'definition' => 'Список элементов с этим тегом',
    'context' => '',
  ),
  829 => 
  array (
    'term' => 'no_item_to_display',
    'definition' => 'Нет элементов для отображения',
    'context' => '',
  ),
  830 => 
  array (
    'term' => 'opening_folder',
    'definition' => 'Чтение папки...',
    'context' => '',
  ),
  831 => 
  array (
    'term' => 'please_confirm',
    'definition' => 'Пожалуйста, подтвердите',
    'context' => '',
  ),
  832 => 
  array (
    'term' => 'suggestion_notify_subject',
    'definition' => '[TeamPass] Поступило новое предложение.',
    'context' => '',
  ),
  833 => 
  array (
    'term' => 'suggestion_notify_body',
    'definition' => 'Здравствуйте,&lt;br&gt;&lt;br&gt;Поступило новое предложение. Вам необходимо его проверить, прежде чем другие пользователи смогут его использовать.&lt;br&gt;Информация о предложении:&lt;br&gt;- Метка: #tp_label#&lt;br&gt;- Папка: #tp_folder#&lt;br&gt;- Пользователь: #tp_user#&lt;br&gt;&lt;br&gt;Данное сообщение было отправлено всем менеджерам.',
    'context' => '',
  ),
  834 => 
  array (
    'term' => 'error_unknown',
    'definition' => 'Произошла непредвиденная ошибка!',
    'context' => '',
  ),
  835 => 
  array (
    'term' => 'no_edit_no_delete',
    'definition' => 'Запись, но не редактирование и не удаление',
    'context' => '',
  ),
  836 => 
  array (
    'term' => 'no_edit',
    'definition' => 'Запись, но не редактирование',
    'context' => '',
  ),
  837 => 
  array (
    'term' => 'role_cannot_edit_item',
    'definition' => 'Нельзя редактировать элементы',
    'context' => '',
  ),
  838 => 
  array (
    'term' => 'no_delete',
    'definition' => 'Запись, но не удаление',
    'context' => '',
  ),
  839 => 
  array (
    'term' => 'role_cannot_delete_item',
    'definition' => 'Нельзя удалять элементы',
    'context' => '',
  ),
  840 => 
  array (
    'term' => 'text_without_symbols',
    'definition' => 'Допускаются только цифры, буквы и символы # & % * $ @ ( ). Другие символы недопустимы!',
    'context' => '',
  ),
  841 => 
  array (
    'term' => 'my_profile',
    'definition' => 'Моя учетная запись',
    'context' => '',
  ),
  842 => 
  array (
    'term' => 'at_suggestion',
    'definition' => 'Предложение одобрено',
    'context' => '',
  ),
  843 => 
  array (
    'term' => 'character_not_allowed',
    'definition' => 'Символ не допустим!',
    'context' => '',
  ),
  844 => 
  array (
    'term' => 'error_saltkey_length',
    'definition' => 'SaltKey must be a 16 characters string!',
    'context' => '',
  ),
  845 => 
  array (
    'term' => 'starting',
    'definition' => 'Starting ...',
    'context' => '',
  ),
  846 => 
  array (
    'term' => 'total_number_of_items',
    'definition' => 'Total number of items',
    'context' => '',
  ),
  847 => 
  array (
    'term' => 'finalizing',
    'definition' => 'Finalizing',
    'context' => '',
  ),
  848 => 
  array (
    'term' => 'treating_items',
    'definition' => 'Treating items',
    'context' => '',
  ),
  849 => 
  array (
    'term' => 'number_of_items_treated',
    'definition' => 'Number of treated items',
    'context' => '',
  ),
  850 => 
  array (
    'term' => 'error_sent_back',
    'definition' => 'Next error occured',
    'context' => '',
  ),
  851 => 
  array (
    'term' => 'full',
    'definition' => 'Full',
    'context' => '',
  ),
  852 => 
  array (
    'term' => 'sequential',
    'definition' => 'Sequential',
    'context' => '',
  ),
  853 => 
  array (
    'term' => 'tree_load_strategy',
    'definition' => 'Tree load strategy',
    'context' => '',
  ),
  854 => 
  array (
    'term' => 'syslog_enable',
    'definition' => 'Enable log with Syslog',
    'context' => '',
  ),
  855 => 
  array (
    'term' => 'syslog_host',
    'definition' => 'Syslog server',
    'context' => '',
  ),
  856 => 
  array (
    'term' => 'syslog_port',
    'definition' => 'Syslog port',
    'context' => '',
  ),
);
?>