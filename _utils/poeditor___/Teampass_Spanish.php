<?php
$LANG = array (
		0 => array (
				'term' => 'user_ga_code',
				'definition' => 'Enviar GoogleAuthenticatior al usuario vía email',
				'context' => '' 
		),
		1 => array (
				'term' => 'send_ga_code',
				'definition' => 'Google Authenticator para el usuario',
				'context' => '' 
		),
		2 => array (
				'term' => 'error_no_email',
				'definition' => 'Este usuario no tiene correo configurado!',
				'context' => '' 
		),
		3 => array (
				'term' => 'error_no_user',
				'definition' => 'No se encontró el usuario!',
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
				'definition' => 'Habilitar cifrado de los items adjuntos',
				'context' => '' 
		),
		7 => array (
				'term' => 'settings_attachments_encryption_tip',
				'definition' => 'THIS OPTION COULD BREAK EXISTING ATTACHMENTS, please read carefully the next. If enabled, Items attachments are stored encrypted on the server. The ecryption uses the SALT defined for Teampass. This requieres more server ressources. WARNING: once you change strategy, it is mandatory to run the script to adapt existing attachments. See tab \'Specific Actions\'.',
				'context' => '' 
		),
		8 => array (
				'term' => 'admin_action_attachments_cryption',
				'definition' => 'Cifrar o descifrar los Items adjunots',
				'context' => '' 
		),
		9 => array (
				'term' => 'admin_action_attachments_cryption_tip',
				'definition' => 'WARNING: this action has ONLY to be performed after changing the associated option in Teampass settings. Please make a copy of the folder \'upload\' before doing any action, just in case ...',
				'context' => '' 
		),
		10 => array (
				'term' => 'encrypt',
				'definition' => 'Cifrar',
				'context' => '' 
		),
		11 => array (
				'term' => 'decrypt',
				'definition' => 'Descifrar',
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
				'definition' => 'Corregir los prefijos de claves',
				'context' => '' 
		),
		15 => array (
				'term' => 'admin_action_pw_prefix_correct_tip',
				'definition' => 'Before lauching this script, PLEASE be sure to make a dump of the database. This script will perform an update of passwords prefix. It SHALL only be used if you noticed that passwords are displayed with strange prefix.',
				'context' => '' 
		),
		16 => array (
				'term' => 'items_changed',
				'definition' => 'ha sido cambiado.',
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
				'definition' => 'Código de autenticación',
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
				'definition' => 'Campo',
				'context' => '' 
		),
		25 => array (
				'term' => 'category_in_folders_title',
				'definition' => 'Carpetas asociadas',
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
				'definition' => 'Exportar Items para el modo Off-line',
				'context' => '' 
		),
		30 => array (
				'term' => 'settings_offline_mode',
				'definition' => 'Activar modo Off-line',
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
				'definition' => 'Categorías',
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
				'definition' => 'Va a eliminar, ¿está seguro?',
				'context' => '' 
		),
		37 => array (
				'term' => 'confirm_rename',
				'definition' => 'Confirmar renombrado?',
				'context' => '' 
		),
		38 => array (
				'term' => 'new_field_title',
				'definition' => 'Enter the title of the new Field',
				'context' => '' 
		),
		39 => array (
				'term' => 'confirm_creation',
				'definition' => 'Confirmar creación?',
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
				'definition' => 'Mover a',
				'context' => '' 
		),
		43 => array (
				'term' => 'field_add_in_category',
				'definition' => 'Add a new field in this category',
				'context' => '' 
		),
		44 => array (
				'term' => 'rename',
				'definition' => 'Renombrar',
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
				'definition' => 'Más',
				'context' => '' 
		),
		49 => array (
				'term' => 'save_categories_position',
				'definition' => 'Save Categories order',
				'context' => '' 
		),
		50 => array (
				'term' => 'reload_table',
				'definition' => 'Recargar tabla',
				'context' => '' 
		),
		51 => array (
				'term' => 'settings_ldap_type',
				'definition' => 'Tipo de servidor LDAP',
				'context' => '' 
		),
		52 => array (
				'term' => 'use_md5_password_as_salt',
				'definition' => 'Usar el password como SALTkey',
				'context' => '' 
		),
		53 => array (
				'term' => 'server_time',
				'definition' => 'Hora del servidor',
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
				'definition' => 'Actualizar Carpetas Personales para todos los usuarios (las crea si no existen)',
				'context' => '' 
		),
		62 => array (
				'term' => 'admin_actions_title',
				'definition' => 'Acciones específicas',
				'context' => '' 
		),
		63 => array (
				'term' => 'enable_personal_folder_feature_tip',
				'definition' => 'Once activated, you need to manually run a script that will create the personal folders for the existing users. Notice that this will only create personal folders for Users that do not have such a folder. The script \'".$txt[\'admin_action_check_pf\']."\' is available in tab \'".$txt[\'admin_actions_title\']."\'',
				'context' => '' 
		),
		64 => array (
				'term' => 'is_administrated_by_role',
				'definition' => 'El usuario está administrado por',
				'context' => '' 
		),
		65 => array (
				'term' => 'administrators_only',
				'definition' => 'Sólo administradores',
				'context' => '' 
		),
		66 => array (
				'term' => 'managers_of',
				'definition' => 'Gestionadores del rol',
				'context' => '' 
		),
		67 => array (
				'term' => 'managed_by',
				'definition' => 'Gestionado por',
				'context' => '' 
		),
		68 => array (
				'term' => 'admin_small',
				'definition' => 'Administrador',
				'context' => '' 
		),
		69 => array (
				'term' => 'setting_can_create_root_folder',
				'definition' => 'Autorizado a crear nuevas carpetas en Raiz',
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
				'definition' => '¡La extensión OPENSSL no está cargada! ¡El cifrado no puede iniciarse!',
				'context' => '' 
		),
		74 => array (
				'term' => 'channel_encryption_no_file',
				'definition' => 'No se ha encontrado ningún juego de llaves!&lt;br&gt;Por favor inicie el proceso de actualización.',
				'context' => '' 
		),
		75 => array (
				'term' => 'admin_action_generate_encrypt_keys',
				'definition' => 'Generar nuevo juego de llaves',
				'context' => '' 
		),
		76 => array (
				'term' => 'admin_action_generate_encrypt_keys_tip',
				'definition' => 'El juego de llaves es un aspecto muy importante en la seguridad de la instalacion de su TeamPass. De hecho estas llaves se utilizan para cifrar la comunicación entre Servidor y Cliente. Aunque este fichero este securizado fuera de la zona web de su servidor, es recomendable regenerar las mismas de cuando en cuando. Tenga en cuenta que esta operación puede lleva no mas de 1 minuto.',
				'context' => '' 
		),
		77 => array (
				'term' => 'settings_anyone_can_modify_bydefault',
				'definition' => 'Activate la opción de \'&lt;b&gt;&lt;i&gt;Cualquiera puede editar&lt;/b&gt;&lt;/i&gt;\' por defecto',
				'context' => '' 
		),
		78 => array (
				'term' => 'channel_encryption_in_progress',
				'definition' => 'Cifrando canal',
				'context' => '' 
		),
		79 => array (
				'term' => 'channel_encryption_failed',
				'definition' => 'Fallo de autenticación!',
				'context' => '' 
		),
		80 => array (
				'term' => 'purge_log',
				'definition' => 'Logs purgados de',
				'context' => '' 
		),
		81 => array (
				'term' => 'to',
				'definition' => 'hacia',
				'context' => '' 
		),
		82 => array (
				'term' => 'purge_now',
				'definition' => 'Purgar Ahora!',
				'context' => '' 
		),
		83 => array (
				'term' => 'purge_done',
				'definition' => 'Purga realizada!&lt;br /&gt;Número de elementos eliminados:',
				'context' => '' 
		),
		84 => array (
				'term' => 'settings_upload_maxfilesize_tip',
				'definition' => 'Tamaño máximo de fichero permitido. Debe ser coherente con los parámetros del servidor.',
				'context' => '' 
		),
		85 => array (
				'term' => 'settings_upload_docext_tip',
				'definition' => 'Tipos de documento. Indicar las extensiones de fichero permitidas separadas por comas (,)',
				'context' => '' 
		),
		86 => array (
				'term' => 'settings_upload_imagesext_tip',
				'definition' => 'Tipos de imagen. Indicar las extensiones de fichero permitidas separadas por comas (,)',
				'context' => '' 
		),
		87 => array (
				'term' => 'settings_upload_pkgext_tip',
				'definition' => 'Tipos de paquete. Indicar las extensiones de fichero permitidas separadas por comas (,)',
				'context' => '' 
		),
		88 => array (
				'term' => 'settings_upload_otherext_tip',
				'definition' => 'Otros tipos de fichero. Indicar las extensiones de fichero permitidas separadas por comas (,)',
				'context' => '' 
		),
		89 => array (
				'term' => 'settings_upload_imageresize_options_tip',
				'definition' => 'Cuando se activa, esta opción redimensiona las imágenes al formato indicado justo debajo.',
				'context' => '' 
		),
		90 => array (
				'term' => 'settings_upload_maxfilesize',
				'definition' => 'Tamaño máximo del fichero (en MB)',
				'context' => '' 
		),
		91 => array (
				'term' => 'settings_upload_docext',
				'definition' => 'Extensiones de documento permitidas',
				'context' => '' 
		),
		92 => array (
				'term' => 'settings_upload_imagesext',
				'definition' => 'Extensiones de imagen permitidas',
				'context' => '' 
		),
		93 => array (
				'term' => 'settings_upload_pkgext',
				'definition' => 'Extensiones de paquete permitidas',
				'context' => '' 
		),
		94 => array (
				'term' => 'settings_upload_otherext',
				'definition' => 'Otras extensiones de fichero permitidas',
				'context' => '' 
		),
		95 => array (
				'term' => 'settings_upload_imageresize_options',
				'definition' => '¿Deberían redimensionarse las imágenes?',
				'context' => '' 
		),
		96 => array (
				'term' => 'settings_upload_imageresize_options_w',
				'definition' => 'Anchura de la imagen redimensionada (en píxeles)',
				'context' => '' 
		),
		97 => array (
				'term' => 'settings_upload_imageresize_options_h',
				'definition' => 'Altura de la imagen redimensionada (en píxeles)',
				'context' => '' 
		),
		98 => array (
				'term' => 'settings_upload_imageresize_options_q',
				'definition' => 'Calidad de la imagen redimensionada',
				'context' => '' 
		),
		99 => array (
				'term' => 'admin_upload_title',
				'definition' => 'Subidas',
				'context' => '' 
		),
		100 => array (
				'term' => 'settings_importing',
				'definition' => 'Habilitar importación de datos desde ficheros CVS/KeePass',
				'context' => '' 
		),
		101 => array (
				'term' => 'admin_proxy_ip',
				'definition' => 'IP/nombre del proxy usado',
				'context' => '' 
		),
		102 => array (
				'term' => 'admin_proxy_ip_tip',
				'definition' => '&lt;span style=\'font-size:11px;max-width:300px;\'&gt;Si su conexión a internet requiere un proxy, indíquelo aquí.&lt;br /&gt;Déjelo en blanco si no hay proxy.&lt;/span&gt;',
				'context' => '' 
		),
		103 => array (
				'term' => 'admin_proxy_port',
				'definition' => 'Puerto del proxy',
				'context' => '' 
		),
		104 => array (
				'term' => 'admin_proxy_port_tip',
				'definition' => '
&lt;span style=\'font-size:11px;max-width:300px;\'&gt;Si ha establecido una IP para el proxy, indique ahora su PUERTO (podría ser 8080).&lt;br /&gt;Déjelo en blanco si no hay proxy.&lt;/span&gt;',
				'context' => '' 
		),
		105 => array (
				'term' => 'settings_ldap_elusers',
				'definition' => 'Solo usuarios locales de TeamPass',
				'context' => '' 
		),
		106 => array (
				'term' => 'settings_ldap_elusers_tip',
				'definition' => 'Esta característica permite a los usuarios de la base de datos autenticarse mediante LDAP. Deshabilite esta opción si quiere navegar por cualquier directorio LDAP.',
				'context' => '' 
		),
		107 => array (
				'term' => 'error_role_complex_not_set',
				'definition' => 'El Rol debe tener un nivel mínimo de complejidad requerido para los passwords.',
				'context' => '' 
		),
		108 => array (
				'term' => 'item_updated_text',
				'definition' => 'Este elemento ha sido editado. Necesita actualizarlo antes de poder cambiarlo.',
				'context' => '' 
		),
		109 => array (
				'term' => 'database_menu',
				'definition' => 'Base de datos',
				'context' => '' 
		),
		110 => array (
				'term' => 'db_items_edited',
				'definition' => 'Elementos que se están editando actualmente',
				'context' => '' 
		),
		111 => array (
				'term' => 'item_edition_start_hour',
				'definition' => 'La edición empezó en',
				'context' => '' 
		),
		112 => array (
				'term' => 'settings_delay_for_item_edition',
				'definition' => 'Después de cuánto tiempo la edición de un elemento se considera fallida (en minutos)',
				'context' => '' 
		),
		113 => array (
				'term' => 'settings_delay_for_item_edition_tip',
				'definition' => '&lt;span style=\'font-size:11px;max-width:300px;\'&gt;Cuando se edita un elemento, el elemento se bloquea para impedir ediciones paralelas. Para ello se reserva una especie de token.&lt;br /&gt;Este parámetro permite borrar el token tras un cierto tiempo. Si el valor se establece a 0, el token nunca se borrará.&lt;/span&gt;',
				'context' => '' 
		),
		114 => array (
				'term' => 'db_users_logged',
				'definition' => 'Usuarios logados actualmente',
				'context' => '' 
		),
		115 => array (
				'term' => 'action',
				'definition' => 'Acción',
				'context' => '' 
		),
		116 => array (
				'term' => 'login_time',
				'definition' => 'Logado desde',
				'context' => '' 
		),
		117 => array (
				'term' => 'lastname',
				'definition' => 'Apellidos',
				'context' => '' 
		),
		118 => array (
				'term' => 'user_login',
				'definition' => 'Login',
				'context' => '' 
		),
		119 => array (
				'term' => 'at_user_new_lastname',
				'definition' => 'El apellido del usuario #user_login# ha cambiado',
				'context' => '' 
		),
		120 => array (
				'term' => 'at_user_new_name',
				'definition' => 'El nombre del usuario #user_login# ha cambiado',
				'context' => '' 
		),
		121 => array (
				'term' => 'info_list_of_connected_users_approximation',
				'definition' => 'Nota: esta lista puede mostrar más usuarios conectados de los que realmente son.',
				'context' => '' 
		),
		122 => array (
				'term' => 'disconnect_all_users',
				'definition' => 'Se desconectaron todos los usuarios (excepto los administradores)',
				'context' => '' 
		),
		123 => array (
				'term' => 'role',
				'definition' => 'Rol',
				'context' => '' 
		),
		124 => array (
				'term' => 'admin_2factors_authentication_setting',
				'definition' => 'Habilitar autenticación Google en dos pasos',
				'context' => '' 
		),
		125 => array (
				'term' => 'admin_2factors_authentication_setting_tip',
				'definition' => '&lt;span style=\'font-size:11px;max-width:300px;\'&gt;La autenticación Google en dos pasos permite añadir un nivel más de seguridad en la autenticación. Cuando el usuario quiere logarse a Teampass, se genera un código QR. Este código tiene que ser escaneado por el usuario para obtener un password de un solo uso.&lt;br /&gt;ADVERTENCIA: este paso extra necesita una conexión a Internet y un escaneador de códigos (por ejemplo un smartphone)&lt;/span&gt;',
				'context' => '' 
		),
		126 => array (
				'term' => '2factors_tile',
				'definition' => 'Autenticación en dos pasos',
				'context' => '' 
		),
		127 => array (
				'term' => '2factors_image_text',
				'definition' => 'Por favor, escanee el código QR',
				'context' => '' 
		),
		128 => array (
				'term' => '2factors_confirm_text',
				'definition' => 'Introduzca el password de un solo uso',
				'context' => '' 
		),
		129 => array (
				'term' => 'bad_onetime_password',
				'definition' => '¡Password de un solo uso erróneo!',
				'context' => '' 
		),
		130 => array (
				'term' => 'error_string_not_utf8',
				'definition' => 'Ocurrió un error porque el formato de cadena no es UTF8',
				'context' => '' 
		),
		131 => array (
				'term' => 'error_role_exist',
				'definition' => 'Este Rol ya existe',
				'context' => '' 
		),
		132 => array (
				'term' => 'error_no_edition_possible_locked',
				'definition' => 'No es posible editar ya que el elemento está siendo editado en este momento.',
				'context' => '' 
		),
		133 => array (
				'term' => 'error_mcrypt_not_loaded',
				'definition' => 'La extensión \'mcrypt\' no está cargada en los módulos de PHP. Este módulo es necesario para el funcionamiento de TeamPass. Por favor, informe al administrador si ve este mensaje.',
				'context' => '' 
		),
		134 => array (
				'term' => 'at_user_added',
				'definition' => 'Usuario #user_login# añadido',
				'context' => '' 
		),
		135 => array (
				'term' => 'at_user_deleted',
				'definition' => 'Usuario #user_login# eliminado',
				'context' => '' 
		),
		136 => array (
				'term' => 'at_user_locked',
				'definition' => 'Usuario #user_login# bloqueado',
				'context' => '' 
		),
		137 => array (
				'term' => 'at_user_unlocked',
				'definition' => 'Usuario #user_login# desbloqueado',
				'context' => '' 
		),
		138 => array (
				'term' => 'at_user_email_changed',
				'definition' => 'Correo electrónico del usuario #user_login# cambiado',
				'context' => '' 
		),
		139 => array (
				'term' => 'at_user_pwd_changed',
				'definition' => 'Clave del usuario #user_login# cambiada',
				'context' => '' 
		),
		140 => array (
				'term' => 'at_user_initial_pwd_changed',
				'definition' => 'Clave inicial del usuario #user_login# cambiada',
				'context' => '' 
		),
		141 => array (
				'term' => 'user_mngt',
				'definition' => 'Gestión de usuarios',
				'context' => '' 
		),
		142 => array (
				'term' => 'select',
				'definition' => 'elegir',
				'context' => '' 
		),
		143 => array (
				'term' => 'user_activity',
				'definition' => 'Actividad del usuario',
				'context' => '' 
		),
		144 => array (
				'term' => 'items',
				'definition' => 'Elementos',
				'context' => '' 
		),
		145 => array (
				'term' => 'enable_personal_saltkey_cookie',
				'definition' => 'Habilitar el almacenar la clave Salt personal en una cookie',
				'context' => '' 
		),
		146 => array (
				'term' => 'personal_saltkey_cookie_duration',
				'definition' => 'Tiempo de vida en DIAS de la clave Salt personal antes de que expire',
				'context' => '' 
		),
		147 => array (
				'term' => 'admin_emails',
				'definition' => 'Emails',
				'context' => '' 
		),
		148 => array (
				'term' => 'admin_emails_configuration',
				'definition' => 'Configuración de los emails',
				'context' => '' 
		),
		149 => array (
				'term' => 'admin_emails_configuration_testing',
				'definition' => 'Pruebas de configuración',
				'context' => '' 
		),
		150 => array (
				'term' => 'admin_email_smtp_server',
				'definition' => 'Servidor SMTP',
				'context' => '' 
		),
		151 => array (
				'term' => 'admin_email_auth',
				'definition' => 'El servidor SMTP necesita autenticación',
				'context' => '' 
		),
		152 => array (
				'term' => 'admin_email_auth_username',
				'definition' => 'Usuario de autenticación',
				'context' => '' 
		),
		153 => array (
				'term' => 'admin_email_auth_pwd',
				'definition' => 'Clave de autenticación',
				'context' => '' 
		),
		154 => array (
				'term' => 'admin_email_port',
				'definition' => 'Puerto del servidor',
				'context' => '' 
		),
		155 => array (
				'term' => 'admin_email_from',
				'definition' => 'Correo electrónico emisor (from)',
				'context' => '' 
		),
		156 => array (
				'term' => 'admin_email_from_name',
				'definition' => 'Nombre del emisor',
				'context' => '' 
		),
		157 => array (
				'term' => 'admin_email_test_configuration',
				'definition' => 'Probar configuración de email',
				'context' => '' 
		),
		158 => array (
				'term' => 'admin_email_test_configuration_tip',
				'definition' => 'This test should send an email to the address indicated. If you don\'t receive it, please check your credentials.',
				'context' => '' 
		),
		159 => array (
				'term' => 'admin_email_test_subject',
				'definition' => '[TeamPass] Correo electrónico de prueba',
				'context' => '' 
		),
		160 => array (
				'term' => 'admin_email_test_body',
				'definition' => 'Hola:&lt;br /&gt;&lt;br /&gt;El correo electrónico ha sido enviado satisfactoriamente.&lt;br /&gt;&lt;br /&gt;Saludos.',
				'context' => '' 
		),
		161 => array (
				'term' => 'admin_email_result_ok',
				'definition' => 'Correo electrónico enviado... revise su bandeja de entrada.',
				'context' => '' 
		),
		162 => array (
				'term' => 'admin_email_result_nok',
				'definition' => 'El correo no ha sido enviado... revise la configuración. Error asociado:',
				'context' => '' 
		),
		163 => array (
				'term' => 'email_subject_item_updated',
				'definition' => 'La clave ha sido actualizada',
				'context' => '' 
		),
		164 => array (
				'term' => 'email_body_item_updated',
				'definition' => 'Hola:&lt;br&gt;&lt;br&gt;La clave para \'#item_label#\' ha sido actualizada.&lt;br /&gt;&lt;br /&gt;Puede comprobarla &lt;a href=\'".@$_SESSION[\'settings\'][\'cpassman_url\']."/index.php?page=items&group=#item_category#&id=#item_id#\'&gt;aquí&lt;/a&gt;&lt;br /&gt;&lt;br /&gt;Saludos.',
				'context' => '' 
		),
		165 => array (
				'term' => 'email_bodyalt_item_updated',
				'definition' => 'La clave para #item_label# ha sido actualizada.',
				'context' => '' 
		),
		166 => array (
				'term' => 'admin_email_send_backlog',
				'definition' => 'Enviar correos de backlog (actualmente #nb_emails# correos)',
				'context' => '' 
		),
		167 => array (
				'term' => 'admin_email_send_backlog_tip',
				'definition' => 'Este script permite forzar en la base de datos que los correos electrónicos sean enviados.&lt;br /&gt;Puede llevar algo de tiempo dependiendo del número de mensajes a enviar.',
				'context' => '' 
		),
		168 => array (
				'term' => 'please_wait',
				'definition' => 'Por favor, espere...',
				'context' => '' 
		),
		169 => array (
				'term' => 'admin_url_to_files_folder',
				'definition' => 'URL a la carpeta Files',
				'context' => '' 
		),
		170 => array (
				'term' => 'admin_path_to_files_folder',
				'definition' => 'Ruta física a la carpeta Files',
				'context' => '' 
		),
		171 => array (
				'term' => 'admin_path_to_files_folder_tip',
				'definition' => '&lt;span style=\'font-size:11px;max-width:300px;\'&gt;La carpeta de Archivos (Files) se usa para almacenar todos los archivos generados por TeamPass y también algunos archivos subidos.&lt;br /&gt;IMPORTANTE: por razones de seguridad, esta carpeta no tiene que estar en la carpeta WWW de su sitio web. Tiene que estar en una zona protegida con una regla de redirección en la configuración del Servidor.&lt;br /&gt;IMPORTANTE 2: Puede ser bueno programar una tarea CRON para limpiar periódicamente esta carpeta.&lt;/span&gt;',
				'context' => '' 
		),
		172 => array (
				'term' => 'admin_path_to_upload_folder_tip',
				'definition' => '&lt;span style=\'font-size:11px;max-width:300px;\'&gt;La carpeta de Upload se usa para almacenar todos los archivos subidos asociados a los Elementos.&lt;br /&gt;IMPORTANTE: por razones de seguridad, esta carpeta no tiene que estar en la carpeta WWW de su sitio web. Tiene que estar en una zona protegida con una regla de redirección en la configuración del Servidor.&lt;br /&gt;IMPORTANTE 2: ¡Esta carpeta nunca debe ser eliminada! Esos archivos están asociados a Elementos.',
				'context' => '' 
		),
		173 => array (
				'term' => 'pdf_export',
				'definition' => 'Exportaciones PDF',
				'context' => '' 
		),
		174 => array (
				'term' => 'pdf_password',
				'definition' => 'Clave de encriptación PDF',
				'context' => '' 
		),
		175 => array (
				'term' => 'pdf_password_warning',
				'definition' => 'Debe proporcionar una clave de encriptación',
				'context' => '' 
		),
		176 => array (
				'term' => 'admin_pwd_maximum_length',
				'definition' => 'Longitud máxima de las claves',
				'context' => '' 
		),
		177 => array (
				'term' => 'admin_pwd_maximum_length_tip',
				'definition' => 'El valor por defecto de la longitud de clave está establecido en 40. Es importante saber que estableciendo un valor elevado tiene un impacto en el rendimiento. Cuanto mayor es este valor, el servidor necesita más tiempo para encriptar y desencriptar, y también para mostrar las claves.',
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
				'definition' => 'Añadir entrada en el historial de log',
				'context' => '' 
		),
		181 => array (
				'term' => 'at_manual',
				'definition' => 'Acción manual',
				'context' => '' 
		),
		182 => array (
				'term' => 'at_manual_add',
				'definition' => 'Añadido manualmente',
				'context' => '' 
		),
		183 => array (
				'term' => 'admin_path_to_upload_folder',
				'definition' => 'Ruta física a la carpeta Upload',
				'context' => '' 
		),
		184 => array (
				'term' => 'admin_url_to_upload_folder',
				'definition' => 'URL a la carpeta Upload',
				'context' => '' 
		),
		185 => array (
				'term' => 'automatic_del_after_date_text',
				'definition' => 'o despues de la fecha',
				'context' => '' 
		),
		186 => array (
				'term' => 'at_automatically_deleted',
				'definition' => 'Eliminado automáticamente',
				'context' => '' 
		),
		187 => array (
				'term' => 'admin_setting_enable_delete_after_consultation',
				'definition' => 'El elemento consultado puede ser automáticamente eliminado',
				'context' => '' 
		),
		188 => array (
				'term' => 'admin_setting_enable_delete_after_consultation_tip',
				'definition' => '&lt;span style=\'font-size:11px;max-width:300px;\'&gt;Cuando esta activado, el creador del elemento puede decidir si el elemento va a ser eliminado automáticamente después de ser visto X veces.&lt;/span&gt;',
				'context' => '' 
		),
		189 => array (
				'term' => 'enable_delete_after_consultation',
				'definition' => 'El elemento va a ser eliminado automáticamente después de ser visualizado',
				'context' => '' 
		),
		190 => array (
				'term' => 'times',
				'definition' => 'veces',
				'context' => '' 
		),
		191 => array (
				'term' => 'automatic_deletion_activated',
				'definition' => 'Eliminado automático activado',
				'context' => '' 
		),
		192 => array (
				'term' => 'at_automatic_del',
				'definition' => 'eliminado automático',
				'context' => '' 
		),
		193 => array (
				'term' => 'error_times_before_deletion',
				'definition' => 'El numero de consultas antes de la eliminación tiene que ser mayor que 0',
				'context' => '' 
		),
		194 => array (
				'term' => 'enable_notify',
				'definition' => 'Activar notificaciones',
				'context' => '' 
		),
		195 => array (
				'term' => 'disable_notify',
				'definition' => 'Desactivar notificaciones',
				'context' => '' 
		),
		196 => array (
				'term' => 'notify_activated',
				'definition' => 'Notificaciones activadas',
				'context' => '' 
		),
		197 => array (
				'term' => 'at_email',
				'definition' => 'email',
				'context' => '' 
		),
		198 => array (
				'term' => 'enable_email_notification_on_item_shown',
				'definition' => 'Enviar notificaciones por email cuando el elemento sea visualizado',
				'context' => '' 
		),
		199 => array (
				'term' => 'bad_email_format',
				'definition' => 'La dirección de email no tiene el formato esperado',
				'context' => '' 
		),
		200 => array (
				'term' => 'item_share_text',
				'definition' => 'Para poder compartir el elemento por correo, introduzca la dirección de email y pulse el botón ENVIAR.',
				'context' => '' 
		),
		201 => array (
				'term' => 'share',
				'definition' => 'Compartir este elemento',
				'context' => '' 
		),
		202 => array (
				'term' => 'share_sent_ok',
				'definition' => 'El email ha sido enviado',
				'context' => '' 
		),
		203 => array (
				'term' => 'email_share_item_subject',
				'definition' => '[TeamPass] Un elemento ha sido compartido con usted',
				'context' => '' 
		),
		204 => array (
				'term' => 'email_share_item_mail',
				'definition' => 'Hola,&lt;br&gt;&lt;br&gt;&lt;u&gt;#tp_user#&lt;/u&gt; ha compartido con usted el elemento &lt;b&gt;#tp_item#&lt;/b&gt;&lt;br&gt;Pulse &lt;a href=\'#tp_link#\'&gt;aquí&lt;/a&gt; para acceder.&lt;br&gt;&lt;br&gt;Saludos.',
				'context' => '' 
		),
		205 => array (
				'term' => 'see_item_title',
				'definition' => 'Detalles del elemento',
				'context' => '' 
		),
		206 => array (
				'term' => 'email_on_open_notification_subject',
				'definition' => '[TeamPass] Notificación de acceso a elemento',
				'context' => '' 
		),
		207 => array (
				'term' => 'email_on_open_notification_mail',
				'definition' => 'Hola,&lt;br&gt;&lt;br&gt;#tp_user# ha abierto y visualizado el elemento \'#tp_item#\'.&lt;br&gt;Pulse &lt;a href=\'#tp_link#\'&gt;aquí&lt;/a&gt; para acceder.&lt;br&gt;&lt;br&gt;Saludos.',
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
				'definition' => '[TeamPass] Creación de su cuenta',
				'context' => '' 
		),
		217 => array (
				'term' => 'email_new_user_mail',
				'definition' => 'Hola,&lt;br /&gt;&lt;br /&gt;Un administrador ha creado su cuenta para TeamPass.&lt;br /&gt;Puede usar las siguientes credenciales para iniciar sesión:&lt;br /&gt;- Usuario: #tp_login#&lt;br /&gt;- Contraseña: #tp_pw#&lt;br /&gt;&lt;br /&gt;Haga click &lt;a href=\'#tp_link#\'&gt;aquí&lt;/a&gt; para acceder.&lt;br /&gt;&lt;br /&gt;Saludos.',
				'context' => '' 
		),
		218 => array (
				'term' => 'error_empty_data',
				'definition' => 'No hay datos para proceder',
				'context' => '' 
		),
		219 => array (
				'term' => 'error_not_allowed_to',
				'definition' => '¡No está autorizado a hacer eso!',
				'context' => '' 
		),
		220 => array (
				'term' => 'personal_saltkey_lost',
				'definition' => 'La he perdido',
				'context' => '' 
		),
		221 => array (
				'term' => 'new_saltkey_warning_lost',
				'definition' => '¿Ha perdido su clave Salt? Por desgracia no es recuperable, así que asegúrese antes de continuar.&lt;br /&gt;Al restablecer su clave Salt, todos sus elementos personales existentes se borrarán.',
				'context' => '' 
		),
		222 => array (
				'term' => 'previous_pw',
				'definition' => 'Contraseñas anteriores usadas:',
				'context' => '' 
		),
		223 => array (
				'term' => 'no_previous_pw',
				'definition' => 'No hay contraseñas anteriores.',
				'context' => '' 
		),
		224 => array (
				'term' => 'request_access_ot_item',
				'definition' => 'Solicitar acceso al autor',
				'context' => '' 
		),
		225 => array (
				'term' => 'email_request_access_subject',
				'definition' => '[TeamPass] Solicitar acceso a un elemento',
				'context' => '' 
		),
		226 => array (
				'term' => 'email_request_access_mail',
				'definition' => 'Hola #tp_item_author#,&lt;br /&gt;&lt;br /&gt;El usuario #tp_user# solicita acceso al elemento \'#tp_item#\'.&lt;br /&gt;&lt;br /&gt;Confirme los privilegios de este usuario antes de cambiar la restricción al elemento.&lt;br /&gt;&lt;br /&gt;Un saludo.',
				'context' => '' 
		),
		227 => array (
				'term' => 'admin_action_change_salt_key',
				'definition' => 'Cambiar la clave SALT principal',
				'context' => '' 
		),
		228 => array (
				'term' => 'admin_action_change_salt_key_tip',
				'definition' => 'Antes de cambiar la clave SALT, asegúrese de realizar un backup completo de la base de datos y ponga la herramienta en Mantenimiento para evitar que ningún usuario se pueda conectar.',
				'context' => '' 
		),
		229 => array (
				'term' => 'block_admin_info',
				'definition' => 'Información de Administradores',
				'context' => '' 
		),
		230 => array (
				'term' => 'admin_new1',
				'definition' => '&lt;i&gt;&lt;u&gt;14FEB2012:&lt;/i&gt;&lt;/u&gt;&lt;br /&gt;El perfil Administrador ya no tiene permisos para ver elementos. Este perfil solamente es ahora solo una cuenta Administrativa.&lt;br /&gt;Ver &lt;a href=\'http://www.teampass.net/how-to-handle-changes-on-administrator-profile\' target=\'_blank\'&gt;TeamPass.net&lt;/a&gt; para saber cómo gestionar este cambio.',
				'context' => '' 
		),
		231 => array (
				'term' => 'nb_items_by_query',
				'definition' => 'Numero de elementos a obtener en cada consulta',
				'context' => '' 
		),
		232 => array (
				'term' => 'nb_items_by_query_tip',
				'definition' => '&lt;span style=\'font-size:11px;max-width:300px;\'&gt;Cuantos más elementos, más tiempo tardará en mostrar la lista.&lt;br /&gt;Seleccione \'auto\' para dejar a la herramienta que adapte este valor dependiendo del tamaño de la pantalla del usuario.&lt;br /&gt;Seleccione \'max\' para forzar que se muestre la lista completa de una sola vez.&lt;br /&gt;Escriba un número que corresponderá a la cantidad de elementos que se van a obtener en cada consulta.&lt;/span&gt;',
				'context' => '' 
		),
		233 => array (
				'term' => 'error_no_selected_folder',
				'definition' => 'Es necesario que seleccione una carpeta',
				'context' => '' 
		),
		234 => array (
				'term' => 'open_url_link',
				'definition' => 'Abrir en página nueva',
				'context' => '' 
		),
		235 => array (
				'term' => 'error_pw_too_long',
				'definition' => '¡La contraseña es demasiado larga! máximo 40 caracteres.',
				'context' => '' 
		),
		236 => array (
				'term' => 'at_restriction',
				'definition' => 'Restricción',
				'context' => '' 
		),
		237 => array (
				'term' => 'pw_encryption_error',
				'definition' => '¡Error en encriptación de la contraseña!',
				'context' => '' 
		),
		238 => array (
				'term' => 'enable_send_email_on_user_login',
				'definition' => 'Enviar un correo a los administradores cuando un usuario se conecte',
				'context' => '' 
		),
		239 => array (
				'term' => 'email_subject_on_user_login',
				'definition' => '[TeamPass] Un usuario se ha conectado',
				'context' => '' 
		),
		240 => array (
				'term' => 'email_body_on_user_login',
				'definition' => 'Hola,&lt;br /&gt;&lt;br /&gt;El usuario #tp_user# se ha conectado a TeamPass el día #tp_date# a las #tp_time#.&lt;br /&gt;Saludos.',
				'context' => '' 
		),
		241 => array (
				'term' => 'account_is_locked',
				'definition' => 'Esta cuenta está bloqueada',
				'context' => '' 
		),
		242 => array (
				'term' => 'activity',
				'definition' => 'Actividad',
				'context' => '' 
		),
		243 => array (
				'term' => 'add_button',
				'definition' => 'Agregar',
				'context' => '' 
		),
		244 => array (
				'term' => 'add_new_group',
				'definition' => 'Agregar nueva carpeta',
				'context' => '' 
		),
		245 => array (
				'term' => 'add_role_tip',
				'definition' => 'Agregar nuevo rol',
				'context' => '' 
		),
		246 => array (
				'term' => 'admin',
				'definition' => 'Administración',
				'context' => '' 
		),
		247 => array (
				'term' => 'admin_action',
				'definition' => 'Por favor valide su acción',
				'context' => '' 
		),
		248 => array (
				'term' => 'admin_action_db_backup',
				'definition' => 'Crear una copia de seguridad de la base de datos',
				'context' => '' 
		),
		249 => array (
				'term' => 'admin_action_db_backup_key_tip',
				'definition' => 'Por favor introduzca la clave de encriptación. Guárdela, se le pedirá para si hace una restauración. (Dejar en blanco para no encriptar).',
				'context' => '' 
		),
		250 => array (
				'term' => 'admin_action_db_backup_start_tip',
				'definition' => 'Empezar',
				'context' => '' 
		),
		251 => array (
				'term' => 'admin_action_db_backup_tip',
				'definition' => 'Es una buena práctica crear una copia de seguridad que pueda ser usada para restaurar su base de datos.',
				'context' => '' 
		),
		252 => array (
				'term' => 'admin_action_db_clean_items',
				'definition' => 'Eliminar elementos huerfanos de la base de datos',
				'context' => '' 
		),
		253 => array (
				'term' => 'admin_action_db_clean_items_result',
				'definition' => 'Se han borrado elementos.',
				'context' => '' 
		),
		254 => array (
				'term' => 'admin_action_db_clean_items_tip',
				'definition' => 'Esto solo borrara los elementos y logs asociados que no han sido borrados después de que la carpeta asociada haya sido eliminada. Se recomienda crear una copia de seguridad previamente.',
				'context' => '' 
		),
		255 => array (
				'term' => 'admin_action_db_optimize',
				'definition' => 'Optimizar la base de datos',
				'context' => '' 
		),
		256 => array (
				'term' => 'admin_action_db_restore',
				'definition' => 'Restaurar la base de datos',
				'context' => '' 
		),
		257 => array (
				'term' => 'admin_action_db_restore_key',
				'definition' => 'Por favor ingrese la clave de encriptación.',
				'context' => '' 
		),
		258 => array (
				'term' => 'admin_action_db_restore_tip',
				'definition' => 'Se ha de hacer utilizando un archivo de copia de seguridad de SQL creado con la función de copia de seguridad.',
				'context' => '' 
		),
		259 => array (
				'term' => 'admin_action_purge_old_files',
				'definition' => 'Purgar archivos obsoletos',
				'context' => '' 
		),
		260 => array (
				'term' => 'admin_action_purge_old_files_result',
				'definition' => 'archivos han sido eliminados.',
				'context' => '' 
		),
		261 => array (
				'term' => 'admin_action_purge_old_files_tip',
				'definition' => 'Esto borrará todos los archivos temporales con mas de 7 días.',
				'context' => '' 
		),
		262 => array (
				'term' => 'admin_action_reload_cache_table',
				'definition' => 'Recargar tabla Caché',
				'context' => '' 
		),
		263 => array (
				'term' => 'admin_action_reload_cache_table_tip',
				'definition' => 'Permite recargar todo el contenido de la tabla de Caché. Puede ser útil hacerlo a veces.',
				'context' => '' 
		),
		264 => array (
				'term' => 'admin_backups',
				'definition' => 'Copias de seguridad',
				'context' => '' 
		),
		265 => array (
				'term' => 'admin_error_no_complexity',
				'definition' => '(&lt;a href=\'index.php?page=manage_groups\'&gt;¿Definir?&lt;/a&gt;)',
				'context' => '' 
		),
		266 => array (
				'term' => 'admin_error_no_visibility',
				'definition' => 'Nadie puede ver este elemento. (&lt;a href=\'index.php?page=manage_roles\'&gt;Personalizar roles&lt;/a&gt;).',
				'context' => '' 
		),
		267 => array (
				'term' => 'admin_functions',
				'definition' => 'Administración de roles',
				'context' => '' 
		),
		268 => array (
				'term' => 'admin_groups',
				'definition' => 'Administración de carpetas',
				'context' => '' 
		),
		269 => array (
				'term' => 'admin_help',
				'definition' => 'Ayuda',
				'context' => '' 
		),
		270 => array (
				'term' => 'admin_info',
				'definition' => 'Información referente a la herramienta',
				'context' => '' 
		),
		271 => array (
				'term' => 'admin_info_loading',
				'definition' => 'Cargando información... espere por favor',
				'context' => '' 
		),
		272 => array (
				'term' => 'admin_ldap_configuration',
				'definition' => 'Configuración LDAP',
				'context' => '' 
		),
		273 => array (
				'term' => 'admin_ldap_menu',
				'definition' => 'Opciones de LDAP',
				'context' => '' 
		),
		274 => array (
				'term' => 'admin_main',
				'definition' => 'Información',
				'context' => '' 
		),
		275 => array (
				'term' => 'admin_misc_cpassman_dir',
				'definition' => 'Ruta física completa a TeamPass',
				'context' => '' 
		),
		276 => array (
				'term' => 'admin_misc_cpassman_url',
				'definition' => 'URL completa a TeamPass',
				'context' => '' 
		),
		277 => array (
				'term' => 'admin_misc_custom_login_text',
				'definition' => 'Texto de login personalizado',
				'context' => '' 
		),
		278 => array (
				'term' => 'admin_misc_custom_logo',
				'definition' => 'URL completa del logotipo personalizado',
				'context' => '' 
		),
		279 => array (
				'term' => 'admin_misc_favicon',
				'definition' => 'URL completa al archivo favicon',
				'context' => '' 
		),
		280 => array (
				'term' => 'admin_misc_title',
				'definition' => 'Personalizar',
				'context' => '' 
		),
		281 => array (
				'term' => 'admin_one_shot_backup',
				'definition' => 'Copia y restauración en un paso',
				'context' => '' 
		),
		282 => array (
				'term' => 'admin_script_backups',
				'definition' => 'Configuración para el script de copia de seguridad',
				'context' => '' 
		),
		283 => array (
				'term' => 'admin_script_backups_tip',
				'definition' => 'Para mayor seguridad se recomienda programar una copia de seguridad de la base de datos.&lt;br /&gt;Establezca en el servidor una tarea CRON diaria llamando al scriopt \'script.backup.php\' en el directorio \'backups\'.&lt;br /&gt;Primero debe establecer los dos primeros parámetros y GUARDARLOS.',
				'context' => '' 
		),
		284 => array (
				'term' => 'admin_script_backup_decrypt',
				'definition' => 'Nombre del archivo que desea desencriptar',
				'context' => '' 
		),
		285 => array (
				'term' => 'admin_script_backup_decrypt_tip',
				'definition' => 'Con el fin de desencriptar un archivo de copia de seguridad, indique el nombre del archivo de copia de seguridad (sin extensión ni ruta).&lt;br /&gt;El archivo se desencriptará en la misma carpeta que los archivos de copia de seguridad.',
				'context' => '' 
		),
		286 => array (
				'term' => 'admin_script_backup_encryption',
				'definition' => 'Clave de encriptación (opcional)',
				'context' => '' 
		),
		287 => array (
				'term' => 'admin_script_backup_encryption_tip',
				'definition' => 'Si se establece, esta clave se utiliza para encriptar el archivo',
				'context' => '' 
		),
		288 => array (
				'term' => 'admin_script_backup_filename',
				'definition' => 'Nombre del fichero de copia de seguridad',
				'context' => '' 
		),
		289 => array (
				'term' => 'admin_script_backup_filename_tip',
				'definition' => 'Nombre de archivo de copias de seguridad',
				'context' => '' 
		),
		290 => array (
				'term' => 'admin_script_backup_path',
				'definition' => 'Ruta física donde se almacenan las copias de seguridad',
				'context' => '' 
		),
		291 => array (
				'term' => 'admin_script_backup_path_tip',
				'definition' => '¿En qué carpeta almacenar los archivos de copia de seguridad?',
				'context' => '' 
		),
		292 => array (
				'term' => 'admin_settings',
				'definition' => 'Ajustes',
				'context' => '' 
		),
		293 => array (
				'term' => 'admin_settings_title',
				'definition' => 'Ajustes de TeamPass',
				'context' => '' 
		),
		294 => array (
				'term' => 'admin_setting_activate_expiration',
				'definition' => 'Habilitar expiración de contraseñas',
				'context' => '' 
		),
		295 => array (
				'term' => 'admin_setting_activate_expiration_tip',
				'definition' => 'Si esta activado, los elementos expirados no les serán mostrados a los usuarios.',
				'context' => '' 
		),
		296 => array (
				'term' => 'admin_users',
				'definition' => 'Administración de usuarios',
				'context' => '' 
		),
		297 => array (
				'term' => 'admin_views',
				'definition' => 'Vistas',
				'context' => '' 
		),
		298 => array (
				'term' => 'alert_message_done',
				'definition' => '¡Hecho!',
				'context' => '' 
		),
		299 => array (
				'term' => 'alert_message_personal_sk_missing',
				'definition' => 'Debe ingresar su clave Salt personal',
				'context' => '' 
		),
		300 => array (
				'term' => 'all',
				'definition' => 'todo',
				'context' => '' 
		),
		301 => array (
				'term' => 'anyone_can_modify',
				'definition' => 'Permitir que este elemento sea modificado por cualquiera que pueda acceder a él',
				'context' => '' 
		),
		302 => array (
				'term' => 'associated_role',
				'definition' => 'A qué Rol asociar esta carpeta:',
				'context' => '' 
		),
		303 => array (
				'term' => 'associate_kb_to_items',
				'definition' => 'Seleccione los elementos asociados a esta Base de Conocimientos',
				'context' => '' 
		),
		304 => array (
				'term' => 'assoc_authorized_groups',
				'definition' => 'Carpetas Asociadas Permitidas',
				'context' => '' 
		),
		305 => array (
				'term' => 'assoc_forbidden_groups',
				'definition' => 'Carpetas Asociadas Prohibidas',
				'context' => '' 
		),
		306 => array (
				'term' => 'at',
				'definition' => 'en',
				'context' => '' 
		),
		307 => array (
				'term' => 'at_add_file',
				'definition' => 'Archivo agregado',
				'context' => '' 
		),
		308 => array (
				'term' => 'at_category',
				'definition' => 'Carpeta',
				'context' => '' 
		),
		309 => array (
				'term' => 'at_copy',
				'definition' => 'Copia creada',
				'context' => '' 
		),
		310 => array (
				'term' => 'at_creation',
				'definition' => 'Creación',
				'context' => '' 
		),
		311 => array (
				'term' => 'at_delete',
				'definition' => 'Eliminación',
				'context' => '' 
		),
		312 => array (
				'term' => 'at_del_file',
				'definition' => 'Archivo eliminado',
				'context' => '' 
		),
		313 => array (
				'term' => 'at_description',
				'definition' => 'Descripción.',
				'context' => '' 
		),
		314 => array (
				'term' => 'at_file',
				'definition' => 'Archivo',
				'context' => '' 
		),
		315 => array (
				'term' => 'at_import',
				'definition' => 'Importación',
				'context' => '' 
		),
		316 => array (
				'term' => 'at_label',
				'definition' => 'Etiqueta',
				'context' => '' 
		),
		317 => array (
				'term' => 'at_login',
				'definition' => 'Login',
				'context' => '' 
		),
		318 => array (
				'term' => 'at_modification',
				'definition' => 'Modificación',
				'context' => '' 
		),
		319 => array (
				'term' => 'at_moved',
				'definition' => 'Movido',
				'context' => '' 
		),
		320 => array (
				'term' => 'at_personnel',
				'definition' => 'Personal',
				'context' => '' 
		),
		321 => array (
				'term' => 'at_pw',
				'definition' => 'Contraseña cambiada',
				'context' => '' 
		),
		322 => array (
				'term' => 'at_restored',
				'definition' => 'Restaurado',
				'context' => '' 
		),
		323 => array (
				'term' => 'at_shown',
				'definition' => 'Accedido',
				'context' => '' 
		),
		324 => array (
				'term' => 'at_url',
				'definition' => 'URL',
				'context' => '' 
		),
		325 => array (
				'term' => 'auteur',
				'definition' => 'Autor',
				'context' => '' 
		),
		326 => array (
				'term' => 'author',
				'definition' => 'Autor',
				'context' => '' 
		),
		327 => array (
				'term' => 'authorized_groups',
				'definition' => 'Carpetas permitidas',
				'context' => '' 
		),
		328 => array (
				'term' => 'auth_creation_without_complexity',
				'definition' => 'Permitir crear un elemento sin respetar la complejidad de clave requerida',
				'context' => '' 
		),
		329 => array (
				'term' => 'auth_modification_without_complexity',
				'definition' => 'Permitir modificar un elemento sin respetar la complejidad de clave requerida',
				'context' => '' 
		),
		330 => array (
				'term' => 'auto_create_folder_role',
				'definition' => 'Crear carpeta y rol para',
				'context' => '' 
		),
		331 => array (
				'term' => 'block_last_created',
				'definition' => 'Creado por ultima vez',
				'context' => '' 
		),
		332 => array (
				'term' => 'bugs_page',
				'definition' => 'Si descubre un bug, puede postearlo directamente en el &lt;a href=\'https://github.com/nilsteampassnet/TeamPass/issues\' target=\'_blank\'&gt;&lt;u&gt;Foro de Bugs&lt;/u&gt;&lt;/a&gt;.',
				'context' => '' 
		),
		333 => array (
				'term' => 'by',
				'definition' => 'por',
				'context' => '' 
		),
		334 => array (
				'term' => 'cancel',
				'definition' => 'Cancelar',
				'context' => '' 
		),
		335 => array (
				'term' => 'cancel_button',
				'definition' => 'Cancelar',
				'context' => '' 
		),
		336 => array (
				'term' => 'can_create_root_folder',
				'definition' => 'Puede crear carpetas en el nivel raiz',
				'context' => '' 
		),
		337 => array (
				'term' => 'changelog',
				'definition' => 'Últimas noticias',
				'context' => '' 
		),
		338 => array (
				'term' => 'change_authorized_groups',
				'definition' => 'Cambiar carpetas autorizadas',
				'context' => '' 
		),
		339 => array (
				'term' => 'change_forbidden_groups',
				'definition' => 'Cambiar carpetas prohibidas',
				'context' => '' 
		),
		340 => array (
				'term' => 'change_function',
				'definition' => 'Cambiar roles',
				'context' => '' 
		),
		341 => array (
				'term' => 'change_group_autgroups_info',
				'definition' => 'Elegir las carpetas autorizadas que este Rol puede ver y usar',
				'context' => '' 
		),
		342 => array (
				'term' => 'change_group_autgroups_title',
				'definition' => 'Personalizar las carpetas autorizadas',
				'context' => '' 
		),
		343 => array (
				'term' => 'change_group_forgroups_info',
				'definition' => 'Seleccionar las carpetas prohibidas que este Rol no puede ver ni usar',
				'context' => '' 
		),
		344 => array (
				'term' => 'change_group_forgroups_title',
				'definition' => 'Personalizar carpetas prohibidas',
				'context' => '' 
		),
		345 => array (
				'term' => 'change_user_autgroups_info',
				'definition' => 'Seleccionar las carpetas autorizadas que esta cuenta puede ver y usar',
				'context' => '' 
		),
		346 => array (
				'term' => 'change_user_autgroups_title',
				'definition' => 'Personalizar las carpetas autorizadas',
				'context' => '' 
		),
		347 => array (
				'term' => 'change_user_forgroups_info',
				'definition' => 'Seleccionar las carpetas prohibidas que esta cuenta no puede ver ni usar',
				'context' => '' 
		),
		348 => array (
				'term' => 'change_user_forgroups_title',
				'definition' => 'Personalizar carpetas prohibidas',
				'context' => '' 
		),
		349 => array (
				'term' => 'change_user_functions_info',
				'definition' => 'Seleccionar las funciones asociadas a esta cuenta',
				'context' => '' 
		),
		350 => array (
				'term' => 'change_user_functions_title',
				'definition' => 'Personalizar funciones asociadas',
				'context' => '' 
		),
		351 => array (
				'term' => 'check_all_text',
				'definition' => 'Seleccionar todo',
				'context' => '' 
		),
		352 => array (
				'term' => 'close',
				'definition' => 'Cerrar',
				'context' => '' 
		),
		353 => array (
				'term' => 'complexity',
				'definition' => 'Complejidad',
				'context' => '' 
		),
		354 => array (
				'term' => 'complex_asked',
				'definition' => 'Complejidad requerida',
				'context' => '' 
		),
		355 => array (
				'term' => 'complex_level0',
				'definition' => 'Muy débil',
				'context' => '' 
		),
		356 => array (
				'term' => 'complex_level1',
				'definition' => 'Débil',
				'context' => '' 
		),
		357 => array (
				'term' => 'complex_level2',
				'definition' => 'Media',
				'context' => '' 
		),
		358 => array (
				'term' => 'complex_level3',
				'definition' => 'Fuerte',
				'context' => '' 
		),
		359 => array (
				'term' => 'complex_level4',
				'definition' => 'Muy fuerte',
				'context' => '' 
		),
		360 => array (
				'term' => 'complex_level5',
				'definition' => 'Contundente',
				'context' => '' 
		),
		361 => array (
				'term' => 'complex_level6',
				'definition' => 'Muy contundente',
				'context' => '' 
		),
		362 => array (
				'term' => 'confirm',
				'definition' => 'Confirmar',
				'context' => '' 
		),
		363 => array (
				'term' => 'confirm_delete_group',
				'definition' => 'Ha decidido eliminar esta Carpeta y todos los elementos incluídos en ella... ¿Está seguro?',
				'context' => '' 
		),
		364 => array (
				'term' => 'confirm_del_account',
				'definition' => 'Ha decidido borrar esta Cuenta. ¿Está seguro?',
				'context' => '' 
		),
		365 => array (
				'term' => 'confirm_del_from_fav',
				'definition' => 'Por favor, confirme la eliminación de Favoritos',
				'context' => '' 
		),
		366 => array (
				'term' => 'confirm_del_role',
				'definition' => 'Por favor confirme la eliminación del siguiente rol:',
				'context' => '' 
		),
		367 => array (
				'term' => 'confirm_edit_role',
				'definition' => 'Por favor introduzca el nombre del siguiente rol:',
				'context' => '' 
		),
		368 => array (
				'term' => 'confirm_lock_account',
				'definition' => 'Ha decidido bloquear esta cuenta. ¿Está seguro?',
				'context' => '' 
		),
		369 => array (
				'term' => 'connection',
				'definition' => 'Conexión',
				'context' => '' 
		),
		370 => array (
				'term' => 'connections',
				'definition' => 'Conexiones',
				'context' => '' 
		),
		371 => array (
				'term' => 'copy',
				'definition' => 'Copiar',
				'context' => '' 
		),
		372 => array (
				'term' => 'copy_to_clipboard_small_icons',
				'definition' => 'Activar los iconos de copiar al portapapeles en la página de elementos',
				'context' => '' 
		),
		373 => array (
				'term' => 'copy_to_clipboard_small_icons_tip',
				'definition' => '&lt;span style=\'font-size:11px;max-width:300px;\'&gt;Esto puede ayudar a prevenir el consumo de memoria si los usuarios no tienen un ordenador moderno. De hecho, no se cargará la información de los elementos en el portapapeles.&lt;br /&gt;Por tanto no se podrá hacer una copia rápida de usuario y contraseña.&lt;/span&gt;',
				'context' => '' 
		),
		374 => array (
				'term' => 'creation_date',
				'definition' => 'Fecha de creación',
				'context' => '' 
		),
		375 => array (
				'term' => 'csv_import_button_text',
				'definition' => 'Buscar archivo CSV',
				'context' => '' 
		),
		376 => array (
				'term' => 'date',
				'definition' => 'Fecha',
				'context' => '' 
		),
		377 => array (
				'term' => 'date_format',
				'definition' => 'Formato de la fecha',
				'context' => '' 
		),
		378 => array (
				'term' => 'days',
				'definition' => 'Días',
				'context' => '' 
		),
		379 => array (
				'term' => 'definition',
				'definition' => 'Definición',
				'context' => '' 
		),
		380 => array (
				'term' => 'delete',
				'definition' => 'Eliminar',
				'context' => '' 
		),
		381 => array (
				'term' => 'deletion',
				'definition' => 'Eliminaciones ',
				'context' => '' 
		),
		382 => array (
				'term' => 'deletion_title',
				'definition' => 'Lista de elementos eliminados',
				'context' => '' 
		),
		383 => array (
				'term' => 'del_button',
				'definition' => 'Eliminar',
				'context' => '' 
		),
		384 => array (
				'term' => 'del_function',
				'definition' => 'Eliminar Roles',
				'context' => '' 
		),
		385 => array (
				'term' => 'del_group',
				'definition' => 'Eliminar Carpeta',
				'context' => '' 
		),
		386 => array (
				'term' => 'description',
				'definition' => 'Descripción',
				'context' => '' 
		),
		387 => array (
				'term' => 'disconnect',
				'definition' => 'Desconexión',
				'context' => '' 
		),
		388 => array (
				'term' => 'disconnection',
				'definition' => 'Desconexión',
				'context' => '' 
		),
		389 => array (
				'term' => 'div_dialog_message_title',
				'definition' => 'Información',
				'context' => '' 
		),
		390 => array (
				'term' => 'done',
				'definition' => 'Hecho',
				'context' => '' 
		),
		391 => array (
				'term' => 'drag_drop_helper',
				'definition' => 'Arrastrar y soltar elemento',
				'context' => '' 
		),
		392 => array (
				'term' => 'duplicate_folder',
				'definition' => 'Permitir varias carpetas con el mismo nombre.',
				'context' => '' 
		),
		393 => array (
				'term' => 'duplicate_item',
				'definition' => 'Permitir varios elementos con el mismo nombre.',
				'context' => '' 
		),
		394 => array (
				'term' => 'email',
				'definition' => 'Email',
				'context' => '' 
		),
		395 => array (
				'term' => 'email_altbody_1',
				'definition' => 'Elemento',
				'context' => '' 
		),
		396 => array (
				'term' => 'email_altbody_2',
				'definition' => 'ha sido creado.',
				'context' => '' 
		),
		397 => array (
				'term' => 'email_announce',
				'definition' => 'Anunciar este elemento por email',
				'context' => '' 
		),
		398 => array (
				'term' => 'email_body1',
				'definition' => 'Hola,

Elemento \'',
				'context' => '' 
		),
		399 => array (
				'term' => 'email_body2',
				'definition' => 'ha sido creado.&lt;br /&gt;&lt;br /&gt;Puede verlo haciendo click en &lt;a href=\'',
				'context' => '' 
		),
		400 => array (
				'term' => 'email_body3',
				'definition' => '\'&gt;este enlace&lt;/a&gt;&lt;br /&gt;&lt;br /&gt;Saludos.',
				'context' => '' 
		),
		401 => array (
				'term' => 'email_change',
				'definition' => 'Cambiar el email de la cuenta',
				'context' => '' 
		),
		402 => array (
				'term' => 'email_changed',
				'definition' => 'Email cambiado',
				'context' => '' 
		),
		403 => array (
				'term' => 'email_select',
				'definition' => 'Seleccionar personas a informar',
				'context' => '' 
		),
		404 => array (
				'term' => 'email_subject',
				'definition' => 'Creando un nuevo elemento en el Administrador de Contraseñas',
				'context' => '' 
		),
		405 => array (
				'term' => 'email_text_new_user',
				'definition' => 'Hola,&lt;br /&gt;&lt;br /&gt;Su cuenta ha sido creada en TeamPass.&lt;br /&gt;Puede acceder a $TeamPass_url utilizando las siguientes credenciales:&lt;br /&gt;',
				'context' => '' 
		),
		406 => array (
				'term' => 'enable_favourites',
				'definition' => 'Permitir a los usuarios almacenar Favoritos',
				'context' => '' 
		),
		407 => array (
				'term' => 'enable_personal_folder',
				'definition' => 'Habilitar Carpeta Personal',
				'context' => '' 
		),
		408 => array (
				'term' => 'enable_personal_folder_feature',
				'definition' => 'Habilitar la opción de Carpeta Personal',
				'context' => '' 
		),
		409 => array (
				'term' => 'enable_user_can_create_folders',
				'definition' => 'Los usuarios pueden administrar carpetas en las carpetas padre autorizadas',
				'context' => '' 
		),
		410 => array (
				'term' => 'encrypt_key',
				'definition' => 'Clave de encriptación',
				'context' => '' 
		),
		411 => array (
				'term' => 'errors',
				'definition' => 'errores',
				'context' => '' 
		),
		412 => array (
				'term' => 'error_complex_not_enought',
				'definition' => 'No se ha alcanzado la complejidad requerida para la contraseña',
				'context' => '' 
		),
		413 => array (
				'term' => 'error_confirm',
				'definition' => 'La confirmación de contraseña no es correcta',
				'context' => '' 
		),
		414 => array (
				'term' => 'error_cpassman_dir',
				'definition' => 'No hay una ruta física establecida para TeamPass. Por favor, seleccione la pestaña \'Configuración de TeamPass\' en la pagina de Configuraciones de Administrador.',
				'context' => '' 
		),
		415 => array (
				'term' => 'error_cpassman_url',
				'definition' => 'No se ha definido la URL para TeamPass. Seleccione la pestaña \'Configuración de TeamPass\' en la página de configuración.',
				'context' => '' 
		),
		416 => array (
				'term' => 'error_fields_2',
				'definition' => 'Los 2 campos son obligatorios',
				'context' => '' 
		),
		417 => array (
				'term' => 'error_group',
				'definition' => 'La carpeta es obligatoria',
				'context' => '' 
		),
		418 => array (
				'term' => 'error_group_complex',
				'definition' => 'La Carpeta debe teber un nivel de complejidad de contraseña minimo requerido',
				'context' => '' 
		),
		419 => array (
				'term' => 'error_group_exist',
				'definition' => 'Esa carpeta ya existe',
				'context' => '' 
		),
		420 => array (
				'term' => 'error_group_label',
				'definition' => 'La Carpeta debe tener un nombre',
				'context' => '' 
		),
		421 => array (
				'term' => 'error_html_codes',
				'definition' => 'El texto contiene código HTML, esto no está permitido.',
				'context' => '' 
		),
		422 => array (
				'term' => 'error_item_exists',
				'definition' => 'El elemento ya existe',
				'context' => '' 
		),
		423 => array (
				'term' => 'error_label',
				'definition' => 'La etiqueta es obligatoria',
				'context' => '' 
		),
		424 => array (
				'term' => 'error_must_enter_all_fields',
				'definition' => 'Tiene que completar todos los campos',
				'context' => '' 
		),
		425 => array (
				'term' => 'error_mysql',
				'definition' => '¡Error de MySQL!',
				'context' => '' 
		),
		426 => array (
				'term' => 'error_not_authorized',
				'definition' => 'No esta autorizado a ver esta pagina.',
				'context' => '' 
		),
		427 => array (
				'term' => 'error_not_exists',
				'definition' => 'Esta pagina no existe.',
				'context' => '' 
		),
		428 => array (
				'term' => 'error_no_folders',
				'definition' => 'Deberia empezar creando alguna carpeta.',
				'context' => '' 
		),
		429 => array (
				'term' => 'error_no_password',
				'definition' => 'Debe ingresar su contraseña',
				'context' => '' 
		),
		430 => array (
				'term' => 'error_no_roles',
				'definition' => 'También debería crear algunos roles y asociarlos a carpetas.',
				'context' => '' 
		),
		431 => array (
				'term' => 'error_password_confirmation',
				'definition' => 'La contraseña debería ser la misma',
				'context' => '' 
		),
		432 => array (
				'term' => 'error_pw',
				'definition' => 'La contraseña es obligatoria',
				'context' => '' 
		),
		433 => array (
				'term' => 'error_renawal_period_not_integer',
				'definition' => 'El periodo de renovación debe estar expresado en meses',
				'context' => '' 
		),
		434 => array (
				'term' => 'error_salt',
				'definition' => '&lt;b&gt;La clave SALT es demasiado larga. No utilice la herramienta hasta que un Administrador modifique su clave Salt.&lt;/b&gt; En el archivo settings.php la clave Salt no puede ser superior a 32 caracteres.',
				'context' => '' 
		),
		435 => array (
				'term' => 'error_tags',
				'definition' => 'No se permiten caracteres de puntuación en las etiquetas, solo espacios.',
				'context' => '' 
		),
		436 => array (
				'term' => 'error_user_exists',
				'definition' => 'El usuario ya existe',
				'context' => '' 
		),
		437 => array (
				'term' => 'expiration_date',
				'definition' => 'Fecha de expiración',
				'context' => '' 
		),
		438 => array (
				'term' => 'expir_one_month',
				'definition' => '1 mes',
				'context' => '' 
		),
		439 => array (
				'term' => 'expir_one_year',
				'definition' => '1 año',
				'context' => '' 
		),
		440 => array (
				'term' => 'expir_six_months',
				'definition' => '6 meses',
				'context' => '' 
		),
		441 => array (
				'term' => 'expir_today',
				'definition' => 'hoy',
				'context' => '' 
		),
		442 => array (
				'term' => 'files_&_images',
				'definition' => 'Archivos e Imágenes',
				'context' => '' 
		),
		443 => array (
				'term' => 'find',
				'definition' => 'Buscar',
				'context' => '' 
		),
		444 => array (
				'term' => 'find_text',
				'definition' => 'Su búsqueda',
				'context' => '' 
		),
		445 => array (
				'term' => 'folders',
				'definition' => 'Carpetas',
				'context' => '' 
		),
		446 => array (
				'term' => 'forbidden_groups',
				'definition' => 'Carpetas prohibidas',
				'context' => '' 
		),
		447 => array (
				'term' => 'forgot_my_pw',
				'definition' => '¿Olvidó su contraseña?',
				'context' => '' 
		),
		448 => array (
				'term' => 'forgot_my_pw_email_sent',
				'definition' => 'El email ha sido enviado',
				'context' => '' 
		),
		449 => array (
				'term' => 'forgot_my_pw_error_email_not_exist',
				'definition' => 'Este email no existe',
				'context' => '' 
		),
		450 => array (
				'term' => 'forgot_my_pw_text',
				'definition' => 'Su contraseña le será enviada al email asociado a su cuenta.',
				'context' => '' 
		),
		451 => array (
				'term' => 'forgot_pw_email_altbody_1',
				'definition' => 'Hola, sus credenciales para TeamPass son:',
				'context' => '' 
		),
		452 => array (
				'term' => 'forgot_pw_email_body',
				'definition' => 'Hola,&lt;br /&gt;&lt;br /&gt;Su nueva contraseña para acceder a TeamPass es:',
				'context' => '' 
		),
		453 => array (
				'term' => 'forgot_pw_email_body_1',
				'definition' => 'Hola,

sus credenciales para TeamPass son:

',
				'context' => '' 
		),
		454 => array (
				'term' => 'forgot_pw_email_subject',
				'definition' => 'TeamPass - Su contraseña',
				'context' => '' 
		),
		455 => array (
				'term' => 'forgot_pw_email_subject_confirm',
				'definition' => '[TeamPass] Su contraseña paso 2',
				'context' => '' 
		),
		456 => array (
				'term' => 'functions',
				'definition' => 'Roles',
				'context' => '' 
		),
		457 => array (
				'term' => 'function_alarm_no_group',
				'definition' => 'Este rol no esta asociado a ninguna Carpeta',
				'context' => '' 
		),
		458 => array (
				'term' => 'generate_pdf',
				'definition' => 'Generar un archivo PDF',
				'context' => '' 
		),
		459 => array (
				'term' => 'generation_options',
				'definition' => 'Opciones de generación',
				'context' => '' 
		),
		460 => array (
				'term' => 'gestionnaire',
				'definition' => 'Gestor',
				'context' => '' 
		),
		461 => array (
				'term' => 'give_function_tip',
				'definition' => 'Agregar un nuevo rol',
				'context' => '' 
		),
		462 => array (
				'term' => 'give_function_title',
				'definition' => 'agregar un nuevo Rol',
				'context' => '' 
		),
		463 => array (
				'term' => 'give_new_email',
				'definition' => 'Por favor, introduzca un nuevo email para',
				'context' => '' 
		),
		464 => array (
				'term' => 'give_new_login',
				'definition' => 'Por favor seleccione la cuenta',
				'context' => '' 
		),
		465 => array (
				'term' => 'give_new_pw',
				'definition' => 'Por favor indique la nueva contraseña para',
				'context' => '' 
		),
		466 => array (
				'term' => 'god',
				'definition' => 'DIOS',
				'context' => '' 
		),
		467 => array (
				'term' => 'group',
				'definition' => 'Carpeta',
				'context' => '' 
		),
		468 => array (
				'term' => 'group_parent',
				'definition' => 'Carpeta Padre',
				'context' => '' 
		),
		469 => array (
				'term' => 'group_pw_duration',
				'definition' => 'Periodo de renovación',
				'context' => '' 
		),
		470 => array (
				'term' => 'group_pw_duration_tip',
				'definition' => 'En meses. Use 0 para deshabilitar.',
				'context' => '' 
		),
		471 => array (
				'term' => 'group_select',
				'definition' => 'Seleccionar carpeta',
				'context' => '' 
		),
		472 => array (
				'term' => 'group_title',
				'definition' => 'Etiqueta de la carpeta',
				'context' => '' 
		),
		473 => array (
				'term' => 'history',
				'definition' => 'Historial',
				'context' => '' 
		),
		474 => array (
				'term' => 'home',
				'definition' => 'Página principal',
				'context' => '' 
		),
		475 => array (
				'term' => 'home_personal_menu',
				'definition' => 'Acciones Personales',
				'context' => '' 
		),
		476 => array (
				'term' => 'home_personal_saltkey',
				'definition' => 'Su clave Salt personal',
				'context' => '' 
		),
		477 => array (
				'term' => 'home_personal_saltkey_button',
				'definition' => 'Guardar',
				'context' => '' 
		),
		478 => array (
				'term' => 'home_personal_saltkey_info',
				'definition' => 'Debería ingresar su clave Salt personal si necesita usar sus elementos personales.',
				'context' => '' 
		),
		479 => array (
				'term' => 'home_personal_saltkey_label',
				'definition' => 'Ingrese su clave Salt personal',
				'context' => '' 
		),
		480 => array (
				'term' => 'importing_details',
				'definition' => 'Lista de detalles',
				'context' => '' 
		),
		481 => array (
				'term' => 'importing_folders',
				'definition' => 'Importando carpetas',
				'context' => '' 
		),
		482 => array (
				'term' => 'importing_items',
				'definition' => 'Importando elementos',
				'context' => '' 
		),
		483 => array (
				'term' => 'import_button',
				'definition' => 'Importar',
				'context' => '' 
		),
		484 => array (
				'term' => 'import_csv_anyone_can_modify_in_role_txt',
				'definition' => 'Activar "todos los del mismo rol pueden modificar" en todos los elementos importados.',
				'context' => '' 
		),
		485 => array (
				'term' => 'import_csv_anyone_can_modify_txt',
				'definition' => 'Activar "todos pueden modificar" en todos los elementos importados.',
				'context' => '' 
		),
		486 => array (
				'term' => 'import_csv_dialog_info',
				'definition' => 'Información: La importación debe hacerse usando un archivo CSV. Generalmente un archivo exportado desde KeePass tiene la estructura esperada.
Si usted usa un archivo generado por otra herramienta, por favor chequee que la estructura CSV sea la siguiente: \'Cuenta\',\'Nombre de usuario\',\'Contraseña\',\'Sitio web\',\'Comentarios\'.',
				'context' => '' 
		),
		487 => array (
				'term' => 'import_csv_menu_title',
				'definition' => 'Importar elementos desde archivo (CSV/KeePass XML)',
				'context' => '' 
		),
		488 => array (
				'term' => 'import_error_no_file',
				'definition' => 'Debe seleccionar un archivo!',
				'context' => '' 
		),
		489 => array (
				'term' => 'import_error_no_read_possible',
				'definition' => 'No se puede leer el archivo',
				'context' => '' 
		),
		490 => array (
				'term' => 'import_error_no_read_possible_kp',
				'definition' => 'No se puede leer el archivo. Debe ser un archivo KeePass.',
				'context' => '' 
		),
		491 => array (
				'term' => 'import_keepass_dialog_info',
				'definition' => 'Por favor use esto para seleccionar un archivo XML generado por la funcionalidad de exportación de KeePass. Solo va a funcionar con un archivo KeePass. Tenga en cuenta que el script de importación no importará carpetas o elementos que ya existan en el mismo nivel de la estructura de árbol.',
				'context' => '' 
		),
		492 => array (
				'term' => 'import_keepass_to_folder',
				'definition' => 'Seleccione la carpeta de destino',
				'context' => '' 
		),
		493 => array (
				'term' => 'import_kp_finished',
				'definition' => 'La importación desde KeePass ha finalizado.
Por defecto, el nivel de complejidad para las nuevas carpetas ha sido establecido a \'Medio\'. Quizás necesite cambiarlo.',
				'context' => '' 
		),
		494 => array (
				'term' => 'import_to_folder',
				'definition' => 'Seleccione los elementos que quiere importar a la carpeta:',
				'context' => '' 
		),
		495 => array (
				'term' => 'index_add_one_hour',
				'definition' => 'Extender la sesión 1 hora',
				'context' => '' 
		),
		496 => array (
				'term' => 'index_alarm',
				'definition' => '¡¡¡ALARMA!!!',
				'context' => '' 
		),
		497 => array (
				'term' => 'index_bas_pw',
				'definition' => 'Contraseña incorrecta para esta cuenta',
				'context' => '' 
		),
		498 => array (
				'term' => 'index_change_pw',
				'definition' => 'Debe cambiar su contraseña',
				'context' => '' 
		),
		499 => array (
				'term' => 'index_change_pw_button',
				'definition' => 'Cambiar',
				'context' => '' 
		),
		500 => array (
				'term' => 'index_change_pw_confirmation',
				'definition' => 'Confirmar',
				'context' => '' 
		),
		501 => array (
				'term' => 'index_expiration_in',
				'definition' => 'la sesión expira en',
				'context' => '' 
		),
		502 => array (
				'term' => 'index_get_identified',
				'definition' => 'Por favor, identifíquese',
				'context' => '' 
		),
		503 => array (
				'term' => 'index_identify_button',
				'definition' => 'Entrar',
				'context' => '' 
		),
		504 => array (
				'term' => 'index_identify_you',
				'definition' => 'Por favor, identifíquese',
				'context' => '' 
		),
		505 => array (
				'term' => 'index_last_pw_change',
				'definition' => 'Contraseña cambiada el',
				'context' => '' 
		),
		506 => array (
				'term' => 'index_last_seen',
				'definition' => 'Última conexión, el',
				'context' => '' 
		),
		507 => array (
				'term' => 'index_login',
				'definition' => 'Cuenta',
				'context' => '' 
		),
		508 => array (
				'term' => 'index_maintenance_mode',
				'definition' => 'Modo de mantenimiento activado. Solo los Administradores pueden ingresar.',
				'context' => '' 
		),
		509 => array (
				'term' => 'index_maintenance_mode_admin',
				'definition' => 'Modo de mantenimiento activado. En este momento los usuarios no pueden acceder a TeamPass.',
				'context' => '' 
		),
		510 => array (
				'term' => 'index_new_pw',
				'definition' => 'Nueva contraseña',
				'context' => '' 
		),
		511 => array (
				'term' => 'index_password',
				'definition' => 'Contraseña',
				'context' => '' 
		),
		512 => array (
				'term' => 'index_pw_error_identical',
				'definition' => 'Las contraseñas deben ser idénticas',
				'context' => '' 
		),
		513 => array (
				'term' => 'index_pw_expiration',
				'definition' => 'Expiración de la contraseña actual en',
				'context' => '' 
		),
		514 => array (
				'term' => 'index_pw_level_txt',
				'definition' => 'Complejidad',
				'context' => '' 
		),
		515 => array (
				'term' => 'index_refresh_page',
				'definition' => 'Actualizar página',
				'context' => '' 
		),
		516 => array (
				'term' => 'index_session_duration',
				'definition' => 'Duración de la sesión',
				'context' => '' 
		),
		517 => array (
				'term' => 'index_session_ending',
				'definition' => 'Su sesión terminará en menos de 1 minuto.',
				'context' => '' 
		),
		518 => array (
				'term' => 'index_session_expired',
				'definition' => 'Su sesión ha expirado o no está correctamente identificado',
				'context' => '' 
		),
		519 => array (
				'term' => 'index_welcome',
				'definition' => 'Bienvenido',
				'context' => '' 
		),
		520 => array (
				'term' => 'info',
				'definition' => 'Información',
				'context' => '' 
		),
		521 => array (
				'term' => 'info_click_to_edit',
				'definition' => 'Pulse en una celda para editar su valor',
				'context' => '' 
		),
		522 => array (
				'term' => 'is_admin',
				'definition' => 'Es Administrador',
				'context' => '' 
		),
		523 => array (
				'term' => 'is_manager',
				'definition' => 'Es Gestor',
				'context' => '' 
		),
		524 => array (
				'term' => 'is_read_only',
				'definition' => 'Es de Solo Lectura',
				'context' => '' 
		),
		525 => array (
				'term' => 'items_browser_title',
				'definition' => 'Carpetas',
				'context' => '' 
		),
		526 => array (
				'term' => 'item_copy_to_folder',
				'definition' => 'Por favor, seleccione la carpeta en la que el elemento tiene que ser copiado.',
				'context' => '' 
		),
		527 => array (
				'term' => 'item_menu_add_elem',
				'definition' => 'Agregar elemento',
				'context' => '' 
		),
		528 => array (
				'term' => 'item_menu_add_rep',
				'definition' => 'Agregar una Carpeta',
				'context' => '' 
		),
		529 => array (
				'term' => 'item_menu_add_to_fav',
				'definition' => 'Agregar a Favoritos',
				'context' => '' 
		),
		530 => array (
				'term' => 'item_menu_collab_disable',
				'definition' => 'La edición no está permitida',
				'context' => '' 
		),
		531 => array (
				'term' => 'item_menu_collab_enable',
				'definition' => 'La edición esta permitida',
				'context' => '' 
		),
		532 => array (
				'term' => 'item_menu_copy_elem',
				'definition' => 'Copiar elemento',
				'context' => '' 
		),
		533 => array (
				'term' => 'item_menu_copy_login',
				'definition' => 'Copiar login',
				'context' => '' 
		),
		534 => array (
				'term' => 'item_menu_copy_pw',
				'definition' => 'Copiar contraseña',
				'context' => '' 
		),
		535 => array (
				'term' => 'item_menu_del_elem',
				'definition' => 'Eliminar elemento',
				'context' => '' 
		),
		536 => array (
				'term' => 'item_menu_del_from_fav',
				'definition' => 'Eliminar de Favoritos',
				'context' => '' 
		),
		537 => array (
				'term' => 'item_menu_del_rep',
				'definition' => 'Eliminar una Carpeta',
				'context' => '' 
		),
		538 => array (
				'term' => 'item_menu_edi_elem',
				'definition' => 'Editar elemento',
				'context' => '' 
		),
		539 => array (
				'term' => 'item_menu_edi_rep',
				'definition' => 'Editar una Carpeta',
				'context' => '' 
		),
		540 => array (
				'term' => 'item_menu_find',
				'definition' => 'Buscar',
				'context' => '' 
		),
		541 => array (
				'term' => 'item_menu_mask_pw',
				'definition' => 'Enmascarar contraseña',
				'context' => '' 
		),
		542 => array (
				'term' => 'item_menu_refresh',
				'definition' => 'Actualizar página',
				'context' => '' 
		),
		543 => array (
				'term' => 'kbs',
				'definition' => 'KBs',
				'context' => '' 
		),
		544 => array (
				'term' => 'kb_menu',
				'definition' => 'Base de conocimientos',
				'context' => '' 
		),
		545 => array (
				'term' => 'keepass_import_button_text',
				'definition' => 'Buscar archivo XML',
				'context' => '' 
		),
		546 => array (
				'term' => 'label',
				'definition' => 'Etiqueta',
				'context' => '' 
		),
		547 => array (
				'term' => 'last_items_icon_title',
				'definition' => 'Mostrar/Ocultar el último elemento visto',
				'context' => '' 
		),
		548 => array (
				'term' => 'last_items_title',
				'definition' => 'Últimos elementos vistos',
				'context' => '' 
		),
		549 => array (
				'term' => 'ldap_extension_not_loaded',
				'definition' => 'La extensión LDAP no está activada en el servidor.',
				'context' => '' 
		),
		550 => array (
				'term' => 'level',
				'definition' => 'Nivel',
				'context' => '' 
		),
		551 => array (
				'term' => 'link_copy',
				'definition' => 'Obtener un enlace a este elemento',
				'context' => '' 
		),
		552 => array (
				'term' => 'link_is_copied',
				'definition' => 'El enlace a este elemento ha sido copiado al portapapeles.',
				'context' => '' 
		),
		553 => array (
				'term' => 'login',
				'definition' => 'Login (si es necesario)',
				'context' => '' 
		),
		554 => array (
				'term' => 'login_attempts_on',
				'definition' => 'Intentos de ingreso en',
				'context' => '' 
		),
		555 => array (
				'term' => 'login_copied_clipboard',
				'definition' => 'Login copiado al portapapeles',
				'context' => '' 
		),
		556 => array (
				'term' => 'login_copy',
				'definition' => 'Copiar cuenta al portapapeles',
				'context' => '' 
		),
		557 => array (
				'term' => 'logs',
				'definition' => 'Logs',
				'context' => '' 
		),
		558 => array (
				'term' => 'logs_1',
				'definition' => 'Generar el archivo log para las contraseñas cambiadas el',
				'context' => '' 
		),
		559 => array (
				'term' => 'logs_passwords',
				'definition' => 'Generar log de contraseñas',
				'context' => '' 
		),
		560 => array (
				'term' => 'maj',
				'definition' => 'Letras mayúsculas',
				'context' => '' 
		),
		561 => array (
				'term' => 'mask_pw',
				'definition' => 'Enmascarar/Mostrar la contraseña',
				'context' => '' 
		),
		562 => array (
				'term' => 'max_last_items',
				'definition' => 'Máximo número de últimos elementos vistos por el usuario (por defecto es 10)',
				'context' => '' 
		),
		563 => array (
				'term' => 'menu_title_new_personal_saltkey',
				'definition' => 'Cambiando su clave Salt personal',
				'context' => '' 
		),
		564 => array (
				'term' => 'minutes',
				'definition' => 'minutos',
				'context' => '' 
		),
		565 => array (
				'term' => 'modify_button',
				'definition' => 'Modificar',
				'context' => '' 
		),
		566 => array (
				'term' => 'my_favourites',
				'definition' => 'Mis favoritos',
				'context' => '' 
		),
		567 => array (
				'term' => 'name',
				'definition' => 'Nombre',
				'context' => '' 
		),
		568 => array (
				'term' => 'nb_false_login_attempts',
				'definition' => 'Número de intentos de autenticación incorrectos antes de deshabilitar la cuenta (0 para deshabilitar)',
				'context' => '' 
		),
		569 => array (
				'term' => 'nb_folders',
				'definition' => 'Número de Carpetas',
				'context' => '' 
		),
		570 => array (
				'term' => 'nb_items',
				'definition' => 'Número de Elementos',
				'context' => '' 
		),
		571 => array (
				'term' => 'nb_items_by_page',
				'definition' => 'Número de artículos por página',
				'context' => '' 
		),
		572 => array (
				'term' => 'new_label',
				'definition' => 'Nueva etiqueta',
				'context' => '' 
		),
		573 => array (
				'term' => 'new_role_title',
				'definition' => 'Nuevo nombre de rol',
				'context' => '' 
		),
		574 => array (
				'term' => 'new_saltkey',
				'definition' => 'Nueva clave Salt',
				'context' => '' 
		),
		575 => array (
				'term' => 'new_saltkey_warning',
				'definition' => 'Por favor, asegúrese de utilizar la clave Salt original. De no ser así, la nueva encriptación se corromperá. ¡Antes de realizar ningún cambio, compruebe su clave Salt actual!',
				'context' => '' 
		),
		576 => array (
				'term' => 'new_user_title',
				'definition' => 'Agregar un nuevo usuario',
				'context' => '' 
		),
		577 => array (
				'term' => 'no',
				'definition' => 'No',
				'context' => '' 
		),
		578 => array (
				'term' => 'nom',
				'definition' => 'Nombre',
				'context' => '' 
		),
		579 => array (
				'term' => 'none',
				'definition' => 'Ninguno',
				'context' => '' 
		),
		580 => array (
				'term' => 'none_selected_text',
				'definition' => 'Ningún elemento seleccionado',
				'context' => '' 
		),
		581 => array (
				'term' => 'not_allowed_to_see_pw',
				'definition' => 'No esta autorizado a ver ese elemento',
				'context' => '' 
		),
		582 => array (
				'term' => 'not_allowed_to_see_pw_is_expired',
				'definition' => 'Este elemento ha expirado',
				'context' => '' 
		),
		583 => array (
				'term' => 'not_defined',
				'definition' => 'No definido',
				'context' => '' 
		),
		584 => array (
				'term' => 'no_last_items',
				'definition' => 'No hay elementos vistos',
				'context' => '' 
		),
		585 => array (
				'term' => 'no_restriction',
				'definition' => 'Sin restricción',
				'context' => '' 
		),
		586 => array (
				'term' => 'numbers',
				'definition' => 'Números',
				'context' => '' 
		),
		587 => array (
				'term' => 'number_of_used_pw',
				'definition' => 'El número de contraseñas nuevas que el usuario debe usar antes de poder reutilizar una contraseña antigua.',
				'context' => '' 
		),
		588 => array (
				'term' => 'ok',
				'definition' => 'OK',
				'context' => '' 
		),
		589 => array (
				'term' => 'pages',
				'definition' => 'Páginas',
				'context' => '' 
		),
		590 => array (
				'term' => 'pdf_del_date',
				'definition' => 'El PDF ha generado el',
				'context' => '' 
		),
		591 => array (
				'term' => 'pdf_del_title',
				'definition' => 'Seguimiento de la renovacion de contraseñas',
				'context' => '' 
		),
		592 => array (
				'term' => 'pdf_download',
				'definition' => 'Descargar archivo',
				'context' => '' 
		),
		593 => array (
				'term' => 'personal_folder',
				'definition' => 'Carpeta personal',
				'context' => '' 
		),
		594 => array (
				'term' => 'personal_saltkey_change_button',
				'definition' => 'Cámbialo',
				'context' => '' 
		),
		595 => array (
				'term' => 'personal_salt_key',
				'definition' => 'Su clave Salt personal',
				'context' => '' 
		),
		596 => array (
				'term' => 'personal_salt_key_empty',
				'definition' => 'No se ha introducido clave Salt personal',
				'context' => '' 
		),
		597 => array (
				'term' => 'personal_salt_key_info',
				'definition' => 'Esta clave Salt se usará para encriptar y desencriptar sus contraseñas.
No se almacena en la base de datos, usted es la única persona que la conoce.
¡Por lo tanto, no la pierda!',
				'context' => '' 
		),
		598 => array (
				'term' => 'please_update',
				'definition' => 'Por favor, actualice la herramienta',
				'context' => '' 
		),
		599 => array (
				'term' => 'print',
				'definition' => 'Imprimir',
				'context' => '' 
		),
		600 => array (
				'term' => 'print_out_menu_title',
				'definition' => 'Imprimir listado con sus elementos',
				'context' => '' 
		),
		601 => array (
				'term' => 'print_out_pdf_title',
				'definition' => 'TeamPass - Lista de elementos exportados',
				'context' => '' 
		),
		602 => array (
				'term' => 'print_out_warning',
				'definition' => '¡Todas las claves y datos confidenciales se escribirán en este archivo sin encriptar! Al hacer esto usted esta asumiendo la completa responsabilidad sobre la protección de esta lista.',
				'context' => '' 
		),
		603 => array (
				'term' => 'pw',
				'definition' => 'Contraseña',
				'context' => '' 
		),
		604 => array (
				'term' => 'pw_change',
				'definition' => 'Cambiar la contraseña de la cuenta',
				'context' => '' 
		),
		605 => array (
				'term' => 'pw_changed',
				'definition' => 'Contraseña cambiada',
				'context' => '' 
		),
		606 => array (
				'term' => 'pw_copied_clipboard',
				'definition' => 'Contraseña copiada al portapapeles',
				'context' => '' 
		),
		607 => array (
				'term' => 'pw_copy_clipboard',
				'definition' => 'Copiar contraseña al portapapeles',
				'context' => '' 
		),
		608 => array (
				'term' => 'pw_generate',
				'definition' => 'Generar',
				'context' => '' 
		),
		609 => array (
				'term' => 'pw_is_expired_-_update_it',
				'definition' => 'El elemento ha expirado. Debe cambiar su contraseña.',
				'context' => '' 
		),
		610 => array (
				'term' => 'pw_life_duration',
				'definition' => 'Tiempo de vida de la contraseña de los usuarios antes de expirar (en dias, 0 para deshabilitar)',
				'context' => '' 
		),
		611 => array (
				'term' => 'pw_recovery_asked',
				'definition' => 'Usted ha solicitado una recuperación de contraseña',
				'context' => '' 
		),
		612 => array (
				'term' => 'pw_recovery_button',
				'definition' => 'Enviar mi nueva contraseña',
				'context' => '' 
		),
		613 => array (
				'term' => 'pw_recovery_info',
				'definition' => 'Pulsando el siguiente botón recibirá un email con la nueva contraseña de su cuenta.',
				'context' => '' 
		),
		614 => array (
				'term' => 'pw_used',
				'definition' => 'Esta contraseña ya se ha usado anteriormente',
				'context' => '' 
		),
		615 => array (
				'term' => 'readme_open',
				'definition' => 'Abrir archivo README completo',
				'context' => '' 
		),
		616 => array (
				'term' => 'read_only_account',
				'definition' => 'Solo Lectura',
				'context' => '' 
		),
		617 => array (
				'term' => 'refresh_matrix',
				'definition' => 'Actualizar Matriz',
				'context' => '' 
		),
		618 => array (
				'term' => 'renewal_menu',
				'definition' => 'Seguimiento de la renovación',
				'context' => '' 
		),
		619 => array (
				'term' => 'renewal_needed_pdf_title',
				'definition' => 'Lista de elementos que deben ser renovados',
				'context' => '' 
		),
		620 => array (
				'term' => 'renewal_selection_text',
				'definition' => 'Listar todos los elementos que van a expirar:',
				'context' => '' 
		),
		621 => array (
				'term' => 'restore',
				'definition' => 'Restaurar',
				'context' => '' 
		),
		622 => array (
				'term' => 'restricted_to',
				'definition' => 'Restringido a',
				'context' => '' 
		),
		623 => array (
				'term' => 'restricted_to_roles',
				'definition' => 'Permitir restringir elementos a Usuarios y Roles',
				'context' => '' 
		),
		624 => array (
				'term' => 'rights_matrix',
				'definition' => 'Matriz de permisos de usuario',
				'context' => '' 
		),
		625 => array (
				'term' => 'roles',
				'definition' => 'Roles',
				'context' => '' 
		),
		626 => array (
				'term' => 'role_cannot_modify_all_seen_items',
				'definition' => 'No permitir a este rol modificar todos los elementos accesibles (configuración habitual)',
				'context' => '' 
		),
		627 => array (
				'term' => 'role_can_modify_all_seen_items',
				'definition' => 'Permitir a este rol modificar todos los elementos accesibles (configuración NO SEGURA)',
				'context' => '' 
		),
		628 => array (
				'term' => 'root',
				'definition' => 'Raíz',
				'context' => '' 
		),
		629 => array (
				'term' => 'save_button',
				'definition' => 'Salvar',
				'context' => '' 
		),
		630 => array (
				'term' => 'secure',
				'definition' => 'Seguro',
				'context' => '' 
		),
		631 => array (
				'term' => 'see_logs',
				'definition' => 'Ver los logs',
				'context' => '' 
		),
		632 => array (
				'term' => 'select_folders',
				'definition' => 'Seleccionar carpetas',
				'context' => '' 
		),
		633 => array (
				'term' => 'select_language',
				'definition' => 'Seleccione su idioma',
				'context' => '' 
		),
		634 => array (
				'term' => 'send',
				'definition' => 'Enviar',
				'context' => '' 
		),
		635 => array (
				'term' => 'settings_anyone_can_modify',
				'definition' => 'Activar una opción para cada elemento que le permita a cualquier persona modificarlo',
				'context' => '' 
		),
		636 => array (
				'term' => 'settings_anyone_can_modify_tip',
				'definition' => '&lt;span style=\'font-size:11px;max-width:300px;\'&gt;Cuando está activado, añade una casilla en el elemento que permite a su creador posibilitar la modificación de este elemento por cualquier usuario.&lt;/span&gt;',
				'context' => '' 
		),
		637 => array (
				'term' => 'settings_default_language',
				'definition' => 'Define el idioma por defecto',
				'context' => '' 
		),
		638 => array (
				'term' => 'settings_kb',
				'definition' => 'Habilitar Base de Conocimientos (beta)',
				'context' => '' 
		),
		639 => array (
				'term' => 'settings_kb_tip',
				'definition' => '&lt;span style=\'font-size:11px;max-width:300px;\'&gt;Una vez activado, esto agregará una página en la cual usted puede construir su Base de Conocimientos.&lt;/span&gt;',
				'context' => '' 
		),
		640 => array (
				'term' => 'settings_ldap_domain',
				'definition' => 'Sufijo de cuentas LDAP para su dominio',
				'context' => '' 
		),
		641 => array (
				'term' => 'settings_ldap_domain_controler',
				'definition' => 'Array de controladores de dominio LDAP',
				'context' => '' 
		),
		642 => array (
				'term' => 'settings_ldap_domain_controler_tip',
				'definition' => '&lt;span style=\'font-size:11px;max-width:300px;\'&gt;Especifique múltiples servidores si desea que la clase balancee las consultas LDAP entre ellos.&lt;br /&gt;Debe delimitar los dominios con una coma (,). Por ejemplo: dominio_1,dominio_2,dominio_3.&lt;/span&gt;',
				'context' => '' 
		),
		643 => array (
				'term' => 'settings_ldap_domain_dn',
				'definition' => 'Base DN del LDAP para su dominio',
				'context' => '' 
		),
		644 => array (
				'term' => 'settings_ldap_mode',
				'definition' => 'Permitir autenticación de usuarios a traves de servidor LDAP',
				'context' => '' 
		),
		645 => array (
				'term' => 'settings_ldap_mode_tip',
				'definition' => 'Habilitar solamente si usted tiene un servidor LDAP y desea utilizarlo para autenticar los usuarios de TeamPass.',
				'context' => '' 
		),
		646 => array (
				'term' => 'settings_ldap_ssl',
				'definition' => 'Usar LDAP a traves de SSL (LDAPS)',
				'context' => '' 
		),
		647 => array (
				'term' => 'settings_ldap_tls',
				'definition' => 'Usar LDAP a traves de TSL',
				'context' => '' 
		),
		648 => array (
				'term' => 'settings_log_accessed',
				'definition' => 'Habilitar logs de quién accedió a los elementos',
				'context' => '' 
		),
		649 => array (
				'term' => 'settings_log_connections',
				'definition' => 'Habilitar el logging de todas las conexiones de los usuarios a la base de datos.',
				'context' => '' 
		),
		650 => array (
				'term' => 'settings_maintenance_mode',
				'definition' => 'Poner TeamPass en Modo Mantenimiento',
				'context' => '' 
		),
		651 => array (
				'term' => 'settings_maintenance_mode_tip',
				'definition' => 'Este modo negará el acceso a cualquier usuario con excepción de los Administradores.',
				'context' => '' 
		),
		652 => array (
				'term' => 'settings_manager_edit',
				'definition' => 'Los gestores pueden editar y eliminar los elementos que pueden ver',
				'context' => '' 
		),
		653 => array (
				'term' => 'settings_printing',
				'definition' => 'Habilitar la impresión de elementos a archivos PDF',
				'context' => '' 
		),
		654 => array (
				'term' => 'settings_printing_tip',
				'definition' => 'Una vez habilitado, aparecerá un botón en la página inicial del usuario que le permitirá escribir un listado de elementos en un archivo PDF. Tenga en cuenta que el listado de claves aparecerá desencriptado.',
				'context' => '' 
		),
		655 => array (
				'term' => 'settings_restricted_to',
				'definition' => 'Activar la funcionalidad \'Restringido Para\' en los elementos',
				'context' => '' 
		),
		656 => array (
				'term' => 'settings_richtext',
				'definition' => 'Habilitar texto enriquecido para la descripción de elementos',
				'context' => '' 
		),
		657 => array (
				'term' => 'settings_richtext_tip',
				'definition' => '&lt;span style=\'font-size:11px;max-width:300px;\'&gt;Esto activa la edición de texto enriquecido con BBCodes en el campo de descripción.&lt;/span&gt;',
				'context' => '' 
		),
		658 => array (
				'term' => 'settings_send_stats',
				'definition' => 'Enviar estadisticas mensuales al autor para un mejor entendimiento del uso de TeamPass',
				'context' => '' 
		),
		659 => array (
				'term' => 'settings_send_stats_tip',
				'definition' => 'Estas estadísticas son completamente anónimas. Su IP no será enviada, solamente la siguiente información sera transmitida: cantidad de elementos, carpetas y usuarios, versión de TeamPass, carpetas personales habilitadas, LDAP habilitado. Le agradecemos por adelantado si habilita dichas estadísticas. Con esto me ayuda a seguir desarrollando TeamPass.',
				'context' => '' 
		),
		660 => array (
				'term' => 'settings_show_description',
				'definition' => 'Mostrar descripción en la lista de elementos',
				'context' => '' 
		),
		661 => array (
				'term' => 'show',
				'definition' => 'Mostrar',
				'context' => '' 
		),
		662 => array (
				'term' => 'show_help',
				'definition' => 'Mostrar Ayuda',
				'context' => '' 
		),
		663 => array (
				'term' => 'show_last_items',
				'definition' => 'Mostrar el bloque de los últimos elementos en la página principal',
				'context' => '' 
		),
		664 => array (
				'term' => 'size',
				'definition' => 'Tamaño',
				'context' => '' 
		),
		665 => array (
				'term' => 'start_upload',
				'definition' => 'Empezar a subir archivos',
				'context' => '' 
		),
		666 => array (
				'term' => 'sub_group_of',
				'definition' => 'Depende de ',
				'context' => '' 
		),
		667 => array (
				'term' => 'support_page',
				'definition' => 'Para obtener soporte por favor use el &lt;a href=\'https://github.com/nilsteampassnet/TeamPass/issues\' target=\'_blank\'&gt;&lt;u&gt;Foro&lt;/u&gt;&lt;/a&gt;.',
				'context' => '' 
		),
		668 => array (
				'term' => 'symbols',
				'definition' => 'Símbolos',
				'context' => '' 
		),
		669 => array (
				'term' => 'tags',
				'definition' => 'Etiquetas ',
				'context' => '' 
		),
		670 => array (
				'term' => 'thku',
				'definition' => '¡Gracias por usar TeamPass!',
				'context' => '' 
		),
		671 => array (
				'term' => 'timezone_selection',
				'definition' => 'Zona horaria selecionada',
				'context' => '' 
		),
		672 => array (
				'term' => 'time_format',
				'definition' => 'Formato de la hora',
				'context' => '' 
		),
		673 => array (
				'term' => 'uncheck_all_text',
				'definition' => 'Deseleccionar todo',
				'context' => '' 
		),
		674 => array (
				'term' => 'unlock_user',
				'definition' => 'El usuario esta bloqueado. ¿Desea desbloquear esta cuenta?',
				'context' => '' 
		),
		675 => array (
				'term' => 'update_needed_mode_admin',
				'definition' => 'Se recomienda actualizar su instalación de TeamPass. Pulse &lt;a href="install/upgrade.php"&gt;aquí&lt;/a&gt;.',
				'context' => '' 
		),
		676 => array (
				'term' => 'uploaded_files',
				'definition' => 'Archivos Existentes',
				'context' => '' 
		),
		677 => array (
				'term' => 'upload_button_text',
				'definition' => 'Navegar',
				'context' => '' 
		),
		678 => array (
				'term' => 'upload_files',
				'definition' => 'Cargar Archivos Nuevos',
				'context' => '' 
		),
		679 => array (
				'term' => 'url',
				'definition' => 'URL',
				'context' => '' 
		),
		680 => array (
				'term' => 'url_copied',
				'definition' => 'La URL ha sido copiada',
				'context' => '' 
		),
		681 => array (
				'term' => 'used_pw',
				'definition' => 'Constraseña usada',
				'context' => '' 
		),
		682 => array (
				'term' => 'user',
				'definition' => 'Usuario',
				'context' => '' 
		),
		683 => array (
				'term' => 'users',
				'definition' => 'Usuarios',
				'context' => '' 
		),
		684 => array (
				'term' => 'users_online',
				'definition' => 'Usuarios conectados',
				'context' => '' 
		),
		685 => array (
				'term' => 'user_action',
				'definition' => 'Acción sobre un usuario',
				'context' => '' 
		),
		686 => array (
				'term' => 'user_alarm_no_function',
				'definition' => 'Este usuario no tiene Roles',
				'context' => '' 
		),
		687 => array (
				'term' => 'user_del',
				'definition' => 'Eliminar cuenta',
				'context' => '' 
		),
		688 => array (
				'term' => 'user_lock',
				'definition' => 'Bloquear usuario',
				'context' => '' 
		),
		689 => array (
				'term' => 'version',
				'definition' => 'Versión actual',
				'context' => '' 
		),
		690 => array (
				'term' => 'views_confirm_items_deletion',
				'definition' => '¿Desea eliminar los elementos seleccionados de la base de datos?',
				'context' => '' 
		),
		691 => array (
				'term' => 'views_confirm_restoration',
				'definition' => 'Por favor confirme la restauracion de este elemento',
				'context' => '' 
		),
		692 => array (
				'term' => 'visibility',
				'definition' => 'Visibilidad',
				'context' => '' 
		),
		693 => array (
				'term' => 'warning_screen_height',
				'definition' => 'CUIDADO: la altura de pantalla no es suficiente para mostrar la lista de elementos',
				'context' => '' 
		),
		694 => array (
				'term' => 'yes',
				'definition' => 'Sí',
				'context' => '' 
		),
		695 => array (
				'term' => 'your_version',
				'definition' => 'Su versión',
				'context' => '' 
		),
		696 => array (
				'term' => 'disconnect_all_users_sure',
				'definition' => 'Ha decidido desconectar a todos los usuarios. ¿Está seguro?',
				'context' => '' 
		),
		697 => array (
				'term' => 'Test the Email configuration',
				'definition' => 'Esta prueba enviará un correo electrónico a la dirección indicada. Si no lo recibe, revise sus credenciales.',
				'context' => '' 
		),
		698 => array (
				'term' => 'url_copied_clipboard',
				'definition' => 'URL copied in clipboard',
				'context' => '' 
		),
		699 => array (
				'term' => 'url_copy',
				'definition' => 'Copiar URL en el portapapeles',
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
				'definition' => 'Lista de claves',
				'context' => '' 
		),
		706 => array (
				'term' => 'settings_api_keys_list_tip',
				'definition' => 'This is the keys that are allowed to access to Teampass. Without a valid Key, the access is not possible. You should share those Keys carrefuly!',
				'context' => '' 
		),
		707 => array (
				'term' => 'settings_api_generate_key',
				'definition' => 'Generar clave',
				'context' => '' 
		),
		708 => array (
				'term' => 'settings_api_delete_key',
				'definition' => 'Borrar clave',
				'context' => '' 
		),
		709 => array (
				'term' => 'settings_api_add_key',
				'definition' => 'Agregar nueva clave',
				'context' => '' 
		),
		710 => array (
				'term' => 'settings_api_key',
				'definition' => 'Clave',
				'context' => '' 
		),
		711 => array (
				'term' => 'settings_api_key_label',
				'definition' => 'Etiqueta',
				'context' => '' 
		),
		712 => array (
				'term' => 'settings_api_ip_whitelist',
				'definition' => 'White-list of authorized IPs',
				'context' => '' 
		),
		713 => array (
				'term' => 'settings_api_ip_whitelist_tip',
				'definition' => 'If no IP is listed then any IP is authorized.',
				'context' => '' 
		),
		714 => array (
				'term' => 'settings_api_add_ip',
				'definition' => 'Agregar nueva IP',
				'context' => '' 
		),
		715 => array (
				'term' => 'settings_api_db_intro',
				'definition' => 'Give a label for this new Key (not mandatory but recommended)',
				'context' => '' 
		),
		716 => array (
				'term' => 'error_too_long',
				'definition' => 'Error - cadena demasiado larga!',
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
				'definition' => 'Agregar una sugerencia para el item',
				'context' => '' 
		),
		726 => array (
				'term' => 'comment',
				'definition' => 'Comentario',
				'context' => '' 
		),
		727 => array (
				'term' => 'suggestion_error_duplicate',
				'definition' => 'Una sugerencia similar ya existe!',
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
		),
		773 => array (
				'term' => 'expand',
				'definition' => 'Expand',
				'context' => '' 
		),
		774 => array (
				'term' => 'collapse',
				'definition' => 'Collapse',
				'context' => '' 
		),
		775 => array (
				'term' => 'error_file_is_missing',
				'definition' => 'Error: The file is missing!',
				'context' => '' 
		),
		776 => array (
				'term' => 'click_to_change',
				'definition' => 'Click to change',
				'context' => '' 
		) 
);
?>