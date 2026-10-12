<?php

declare(strict_types=1);

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
 * @file      profile.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('profile') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //

// Prepare GET variables
$get = [];
$get['tab'] = $request->query->get('tab') === null ? '' : $request->query->get('tab');
if ($get['tab'] === 'keys') {
    $get['tab'] = '';
}
// user type
if ($session->get('user-admin') === 1) {
    $session->set('user-privilege', $lang->get('god'));
} elseif ($session->get('user-manager') === 1) {
    $session->set('user-privilege', $lang->get('gestionnaire'));
} elseif ($session->get('user-read_only') === 1) {
    $session->set('user-privilege', $lang->get('read_only_account'));
} elseif ($session->get('user-can_manage_all_users') === 1) {
    $session->set('user-privilege', $lang->get('human_resources'));
} else {
    $session->set('user-privilege', $lang->get('user'));
}

// prepare list of timezones
$zones = timezone_list();
// prepare list of languages
$languages = DB::query(
    'SELECT label, name FROM ' . prefixTable('languages') . ' ORDER BY label ASC'
);

// Do some stats
$userItemsNumber = DB::queryFirstField(
    'SELECT COUNT(id_item) as count
    FROM ' . prefixTable('log_items') . '
    WHERE action = "at_creation" AND  id_user = %i',
    $session->get('user-id')
);

$userModificationNumber = DB::queryFirstField(
    'SELECT COUNT(id_item) as count
    FROM ' . prefixTable('log_items') . '
    WHERE action = "at_modification" AND  id_user = %i',
    $session->get('user-id')
);

$userSeenItemsNumber = DB::queryFirstField(
    'SELECT COUNT(id_item) as count
    FROM ' . prefixTable('log_items') . '
    WHERE action = "at_shown" AND  id_user = %i',
    $session->get('user-id')
);

$userSeenPasswordsNumber = DB::queryFirstField(
    'SELECT COUNT(id_item)
    FROM ' . prefixTable('log_items') . '
    WHERE action = "at_password_shown" AND  id_user = %i',
    $session->get('user-id')
);

$userInfo = DB::queryFirstRow(
    'SELECT avatar, last_pw_change
    FROM ' . prefixTable('users') . ' 
    WHERE id = %i',
    $session->get('user-id')
);

// The delete button follows the stored value, not the resolved URL, so a stale
// database reference can still be cleared by the user.
$hasCustomAvatar = empty($userInfo['avatar']) === false;
$avatar = getUserAvatarUrl([$userInfo['avatar']]);
if ($avatar === '') {
    $avatar = './assets/images/photo.jpg';
}

// Avatar can only be changed when the user is allowed to edit his profile
$canEditAvatar = (string) ($SETTINGS['disable_user_edit_profile'] ?? '0') === '0';

// Get Groups name
$userParOfGroups = [];
foreach ($session->get('user-roles_array') as $role) {
    $tmp = DB::queryFirstRow(
        'SELECT title 
        FROM ' . prefixTable('roles_title') . ' 
        WHERE id = %i',
        $role
    );
    if ($tmp !== null) {
        array_push($userParOfGroups, $tmp['title']);
    }
}

// Password: only an account TeamPass authenticates by itself changes it here,
// like the "Change password" entry of the account menu.
$userAuthType = (string) $session->get('user-auth_type');
$passwordManagedHere = $userAuthType !== '' && $userAuthType !== 'ldap' && $userAuthType !== 'oauth2';
$passwordSummary = '';
if ($passwordManagedHere === true && (int) ($userInfo['last_pw_change'] ?? 0) > 0) {
    $passwordSummary = sprintf(
        $lang->get('profile_password_changed_on'),
        date($SETTINGS['date_format'] ?? 'd/m/Y', (int) $userInfo['last_pw_change'])
    );
    // 'infinite' when passwords never expire
    $daysBeforeExpiration = $session->get('user-num_days_before_exp');
    if (is_numeric($daysBeforeExpiration) === true && (int) $daysBeforeExpiration > 0) {
        $passwordSummary .= ' ' . sprintf($lang->get('profile_password_expires_in'), (string) (int) $daysBeforeExpiration);
    }
}

// Passkeys used to sign in to TeamPass. Still listed when the
// administrator turned the feature off, so they can be deleted.
$webauthnLoginMode = (int) ($SETTINGS['webauthn_login_mode'] ?? 0);
if ($webauthnLoginMode === 0) {
    $webauthnLoginShown = (int) DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('user_webauthn_credentials') . ' WHERE user_id = %i',
        (int) $session->get('user-id')
    ) > 0;
    $webauthnLoginTip = $lang->get('webauthn_login_tip_disabled');
} else {
    $webauthnLoginShown = true;
    if ($webauthnLoginMode === 1) {
        $webauthnLoginTip = $lang->get('webauthn_login_tip_second_factor');
    } elseif ($session->get('user-auth_type') === 'local') {
        $webauthnLoginTip = $lang->get('webauthn_login_tip_passwordless');
    } else {
        $webauthnLoginTip = $lang->get('webauthn_login_tip_directory');
    }
}

$apiFeatureEnabled = isset($SETTINGS['api']) === true && (int) $SETTINGS['api'] === 1;
// Per-user API access, as /authorize checks it. Read now rather than from the
// session: an administrator may change it while the user is signed in.
$userApiAccessEnabled = $apiFeatureEnabled === true
    && (int) DB::queryFirstField(
        'SELECT enabled FROM ' . prefixTable('api') . ' WHERE user_id = %i',
        (int) $session->get('user-id')
    ) === 1;
// Same gate as generate_extension_token in users.queries.php
$extensionTokensAllowed = $apiFeatureEnabled === true
    && (
        (isset($SETTINGS['extension_token_all_auth_types']) === true && (int) $SETTINGS['extension_token_all_auth_types'] === 1)
        || (isset($SETTINGS['oauth2_api_enabled']) === true && (int) $SETTINGS['oauth2_api_enabled'] === 1 && $session->get('user-auth_type') === 'oauth2')
    );
// Tokens issued before an administrator turned token access off stay listable
// and revocable: they would work again if it were turned back on.
$extensionTokensOwned = $apiFeatureEnabled === true
    && $extensionTokensAllowed === false
    && (int) DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('api_tokens') . ' WHERE user_id = %i',
        (int) $session->get('user-id')
    ) > 0;

// Personal items encryption keys — self-repair. A personal object
// only ever has keys for its owner and the internal account, so
// when the internal one is gone the owner's own session is the
// only place it can be rebuilt: no administrator can do it.
$personalItemsCheckShown = (int) $session->get('user-personal_folder_enabled') === 1
    && isset($SETTINGS['enable_pf_feature']) === true && (int) $SETTINGS['enable_pf_feature'] === 1;

// Sections of the page, in menu order. One with nothing to show is left out.
$profileSections = [
    'overview' => true,
    'security' => $passwordManagedHere === true || $webauthnLoginShown === true,
    'extension' => $extensionTokensAllowed === true || $extensionTokensOwned === true,
    'settings' => true,
    'timeline' => true,
    'advanced' => true,
];
// icon, label, what the section holds
$profileMenu = [
    'overview' => ['fa-solid fa-table-cells-large', $lang->get('profile_section_overview'), $lang->get('profile_section_overview_tip')],
    'security' => ['fa-solid fa-shield-halved', $lang->get('profile_section_security'), $lang->get('profile_section_security_tip')],
    'extension' => ['fa-solid fa-puzzle-piece', $lang->get('profile_section_extension'), $lang->get('profile_section_extension_tip')],
    'settings' => ['fa-solid fa-sliders', $lang->get('profile_section_preferences'), $lang->get('profile_section_preferences_tip')],
    'timeline' => ['fa-solid fa-clock-rotate-left', $lang->get('profile_section_activity'), $lang->get('profile_section_activity_tip')],
    'advanced' => ['fa-solid fa-screwdriver-wrench', $lang->get('profile_section_advanced'), $lang->get('profile_section_advanced_tip')],
];
// ?tab= opens a section directly; 'settings' and 'timeline' are the names existing links use
$activeTab = is_string($get['tab']) === true && ($profileSections[$get['tab']] ?? false) === true ? $get['tab'] : 'overview';

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">
                    <i class="fas fa-user-circle mr-2"></i><?php echo $lang->get('profile'); ?>
                </h1>
            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->
    </div>
    <!-- /.container-fluid -->
</div>
<!-- /.content-header -->


<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <div class="row">

            <div class="col-md-3">

                <!-- Profile  -->
                <div class="card card-primary card-outline">
                    <div class="card-body box-profile">
                        <div class="text-center">
                            <img class="profile-user-img img-fluid img-circle<?php echo $canEditAvatar === true ? ' pointer' : ''; ?>" src="<?php echo $avatar; ?>" alt="User profile picture" id="profile-user-avatar"<?php echo $canEditAvatar === true ? ' title="' . $lang->get('upload_new_avatar') . '"' : ''; ?>>
                        </div>

                        <h3 id="profile-username" class="text-center">
                            <?php
                            if (null !== $session->get('user-name') && empty($session->get('user-name')) === false) {
                                echo $session->get('user-name') . ' ' . $session->get('user-lastname');
                            } else {
                                echo $session->get('user-login');
                            }
                            ?>
                        </h3>

                        <p class="text-info text-center"><?php echo $session->get('user-email'); ?></p>
                        <p class="text-muted text-center mb-0"><?php echo $session->get('user-privilege'); ?></p>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->

                <!-- Sections -->
                <div class="card">
                    <div class="card-body p-2">
                        <ul class="nav nav-pills flex-column" id="profile-tabs">
                            <?php foreach ($profileMenu as $section => $entry) :
                                if ($profileSections[$section] === false) {
                                    continue;
                                } ?>
                            <li class="nav-item">
                                <a class="nav-link d-flex align-items-center<?php echo $activeTab === $section ? ' active' : ''; ?>" href="#tab_<?php echo $section; ?>" data-toggle="tab">
                                    <i class="<?php echo $entry[0]; ?> fa-fw fa-lg mr-3"></i>
                                    <span>
                                        <span class="d-block font-weight-bold"><?php echo $entry[1]; ?></span>
                                        <small class="d-block"><?php echo $entry[2]; ?></small>
                                    </span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <!-- /.card -->
            </div>

            <div class="col-md-9">
                <div class="tab-content">

                    <!-- OVERVIEW -->
                    <div class="tab-pane<?php echo $activeTab === 'overview' ? ' active' : ''; ?>" id="tab_overview">
                        <?php if ($profileSections['security'] === true || $profileSections['extension'] === true) : ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><?php echo $lang->get('profile_glance_title'); ?></h3>
                            </div>
                            <div class="list-group list-group-flush">
                                <?php if ($passwordManagedHere === true) : ?>
                                <a href="#tab_security" class="list-group-item list-group-item-action d-flex align-items-center profile-goto">
                                    <i class="fa-solid fa-lock fa-fw fa-lg mr-3 text-primary"></i>
                                    <span class="flex-grow-1">
                                        <span class="d-block font-weight-bold"><?php echo $lang->get('password'); ?></span>
                                        <span class="d-block text-muted small"><?php echo $passwordSummary !== '' ? $passwordSummary : $lang->get('index_change_pw'); ?></span>
                                    </span>
                                    <i class="fa-solid fa-chevron-right text-muted"></i>
                                </a>
                                <?php endif;
                                if ($webauthnLoginShown === true) : ?>
                                <a href="#tab_security" class="list-group-item list-group-item-action d-flex align-items-center profile-goto">
                                    <i class="fa-solid fa-fingerprint fa-fw fa-lg mr-3 text-primary"></i>
                                    <span class="flex-grow-1">
                                        <span class="d-block font-weight-bold"><?php echo $lang->get('webauthn_login_passkeys'); ?></span>
                                        <span class="d-block text-muted small" id="profile-glance-passkeys">&nbsp;</span>
                                    </span>
                                    <i class="fa-solid fa-chevron-right text-muted"></i>
                                </a>
                                <?php endif;
                                if ($profileSections['extension'] === true) : ?>
                                <a href="#tab_extension" class="list-group-item list-group-item-action d-flex align-items-center profile-goto">
                                    <i class="fa-solid fa-puzzle-piece fa-fw fa-lg mr-3 text-primary"></i>
                                    <span class="flex-grow-1">
                                        <span class="d-block font-weight-bold"><?php echo $lang->get('profile_section_extension'); ?></span>
                                        <span class="d-block text-muted small"<?php echo $extensionTokensAllowed === true ? ' id="profile-glance-extension"' : ''; ?>><?php echo $lang->get('profile_section_extension_tip'); ?></span>
                                    </span>
                                    <i class="fa-solid fa-chevron-right text-muted"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-lg-6 d-flex">
                                <div class="card flex-fill">
                                    <div class="card-header">
                                        <h3 class="card-title"><?php echo $lang->get('profile_my_account'); ?></h3>
                                    </div>
                                    <div class="card-body">
                                        <dl class="row mb-0">
                                            <dt class="col-sm-5"><i class="fas fa-users fa-fw mr-2"></i><?php echo $lang->get('part_of_groups'); ?></dt>
                                            <dd class="col-sm-7"><span id="profile-groups"><?php echo implode(', ', $userParOfGroups); ?></span></dd>
                                            <dt class="col-sm-5"><i class="fas fa-child fa-fw mr-2"></i><?php echo $lang->get('index_last_seen'); ?></dt>
                                            <dd class="col-sm-7 mb-0">
                                                <?php
                                                if (isset($SETTINGS['date_format']) === true) {
                                                    echo date($SETTINGS['date_format'], (int) $session->get('user-last_connection'));
                                                } else {
                                                    echo date('d/m/Y', (int) $session->get('user-last_connection'));
                                                }
                                                echo ' ' . $lang->get('at') . ' ';
                                                if (isset($SETTINGS['time_format']) === true) {
                                                    echo date($SETTINGS['time_format'], (int) $session->get('user-last_connection'));
                                                } else {
                                                    echo date('H:i:s', (int) $session->get('user-last_connection'));
                                                }
                                                ?>
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 d-flex">
                                <div class="card flex-fill">
                                    <div class="card-header">
                                        <h3 class="card-title"><?php echo $lang->get('profile_my_activity'); ?></h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-6 pb-3">
                                                <div class="h3 mb-0"><?php echo strval($userItemsNumber); ?></div>
                                                <div class="text-muted small"><?php echo $lang->get('created_items'); ?></div>
                                            </div>
                                            <div class="col-6 pb-3">
                                                <div class="h3 mb-0"><?php echo strval($userModificationNumber); ?></div>
                                                <div class="text-muted small"><?php echo $lang->get('modification_performed'); ?></div>
                                            </div>
                                            <div class="col-6">
                                                <div class="h3 mb-0"><?php echo strval($userSeenItemsNumber); ?></div>
                                                <div class="text-muted small"><?php echo $lang->get('items_opened'); ?></div>
                                            </div>
                                            <div class="col-6">
                                                <div class="h3 mb-0"><?php echo strval($userSeenPasswordsNumber); ?></div>
                                                <div class="text-muted small"><?php echo $lang->get('passwords_seen'); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SIGN-IN AND SECURITY -->
                    <?php if ($profileSections['security'] === true) : ?>
                    <div class="tab-pane<?php echo $activeTab === 'security' ? ' active' : ''; ?>" id="tab_security">
                        <?php if ($passwordManagedHere === true) : ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fa-solid fa-lock mr-2"></i><?php echo $lang->get('password'); ?></h3>
                            </div>
                            <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
                                <span class="mr-3 py-1"><?php echo $passwordSummary; ?></span>
                                <button type="button" class="btn btn-outline-secondary" id="profile-change-password"><i class="fa-solid fa-lock mr-1"></i><?php echo $lang->get('index_change_pw'); ?></button>
                            </div>
                        </div>
                        <?php endif;
                        if ($webauthnLoginShown === true) : ?>
                        <div class="card" id="webauthn-login-block" data-mode="<?php echo $webauthnLoginMode; ?>">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fa-solid fa-fingerprint mr-2"></i><?php echo $lang->get('webauthn_login_passkeys'); ?></h3>
                            </div>
                            <div class="card-body">
                                <p class="text-muted"><?php echo $webauthnLoginTip; ?></p>
                                <div id="webauthn-login-list"></div>
                                <?php if ($webauthnLoginMode !== 0) : ?>
                                <div class="input-group mt-3" style="max-width: 32rem;">
                                    <input type="text" class="form-control" id="webauthn-login-label" maxlength="100" placeholder="<?php echo $lang->get('webauthn_login_label_placeholder'); ?>">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-primary" id="webauthn-login-add"><i class="fa-solid fa-plus mr-1"></i><?php echo $lang->get('webauthn_login_add'); ?></button>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- BROWSER EXTENSION -->
                    <?php
                    // Extension tokens (Personal Access Tokens) and the one-click set-up that issues them
                    if ($extensionTokensAllowed === true || $extensionTokensOwned === true) {
                        if ($extensionTokensAllowed === false) {
                            $extensionTokensTip = $lang->get('extension_tokens_tip_revoke_only');
                        } elseif (isset($SETTINGS['extension_token_all_auth_types']) === true && (int) $SETTINGS['extension_token_all_auth_types'] === 1) {
                            $extensionTokensTip = $lang->get('extension_tokens_tip_all_auth_types');
                        } else {
                            $extensionTokensTip = $lang->get('extension_tokens_tip');
                        }
                        ?>
                    <div class="tab-pane<?php echo $activeTab === 'extension' ? ' active' : ''; ?>" id="tab_extension">
                        <?php
                        // Browser extension auto-configuration — when token-based access is
                        // available to this user (OAuth2, or all-auth-types toggle on).
                        if ($extensionTokensAllowed === true) { ?>
                        <div class="card" id="extension-autoconfig-block">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fa-solid fa-wand-magic-sparkles mr-2"></i><?php echo $lang->get('profile_extension_setup_title'); ?></h3>
                                <div class="card-tools">
                                    <span class="badge badge-secondary hidden" id="extension-autoconfig-status"></span>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="text-muted"><?php echo $lang->get('profile_extension_setup_tip'); ?></p>
                                <button class="btn btn-primary" id="extension-autoconfig-configure"><i class="fa-solid fa-wand-magic-sparkles mr-1"></i><?php echo $lang->get('extension_autoconfig_button'); ?></button>
                                <button class="btn btn-outline-secondary ml-1 hidden" id="extension-autoconfig-download"><i class="fa-solid fa-download mr-1"></i><?php echo $lang->get('extension_autoconfig_download'); ?></button>
                            </div>
                        </div>
                        <?php } ?>
                        <div class="card" id="extension-tokens-block">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fa-solid fa-puzzle-piece mr-2"></i><?php echo $lang->get('profile_extension_access_title'); ?></h3>
                            </div>
                            <div class="card-body">
                                <p class="text-muted"><?php echo $extensionTokensTip; ?></p>
                                <div id="extension-tokens-list"></div>
                                <?php echo $extensionTokensAllowed === true
                                    ? '<button class="btn btn-outline-secondary mt-3" id="generate-extension-token"><i class="fa-solid fa-plus mr-1"></i>' . $lang->get('extension_token_generate') . '</button>'
                                    : ''; ?>
                            </div>
                        </div>
                    </div>
                    <?php } ?>

                    <!-- PREFERENCES -->
                    <div class="tab-pane<?php echo $activeTab === 'settings' ? ' active' : ''; ?>" id="tab_settings">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fa-solid fa-sliders mr-2"></i><?php echo $lang->get('profile_section_preferences'); ?></h3>
                            </div>
                            <form class="needs-validation" novalidate onsubmit="return false;">
                                <div class="card-body">
                                <?php if (($SETTINGS['disable_user_edit_profile'] ?? '0') === '0') : ?>
                                    <h5 class="mb-3"><?php echo $lang->get('profile_preferences_identity'); ?></h5>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="profile-user-name"><?php echo $lang->get('name'); ?></label>
                                            <input type="text" class="form-control" id="profile-user-name" placeholder="" value="<?php echo $session->get('user-name'); ?>">
                                        </div>

                                        <div class="form-group col-md-6">
                                            <label for="profile-user-lastname"><?php echo $lang->get('lastname'); ?></label>
                                            <input type="text" class="form-control" id="profile-user-lastname" placeholder="" value="<?php echo $session->get('user-lastname'); ?>">
                                        </div>

                                        <div class="form-group col-md-6">
                                            <label for="profile-user-email"><?php echo $lang->get('email'); ?></label>
                                            <input type="email" class="form-control" id="profile-user-email" placeholder="name@domain.com" value="<?php echo $session->get('user-email'); ?>">
                                        </div>

                                        <?php if ($canEditAvatar === true) { ?>
                                        <div class="form-group col-md-6">
                                            <label><?php echo $lang->get('profile_picture'); ?></label>
                                            <div class="d-flex align-items-center">
                                                <button type="button" class="btn btn-outline-secondary" id="profile-avatar-file"><?php echo $lang->get('upload_new_avatar'); ?></button>
                                                <button type="button" class="btn btn-outline-danger ml-2<?php echo $hasCustomAvatar === true ? '' : ' hidden'; ?>" id="profile-avatar-delete" title="<?php echo $lang->get('delete_current_avatar'); ?>" aria-label="<?php echo $lang->get('delete_current_avatar'); ?>" data-toggle="tooltip">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                            <small class="form-text text-muted"><?php echo $lang->get('avatar_upload_hint'); ?></small>
                                        </div>
                                        <?php } ?>
                                    </div>
                                <?php endif; /* disable_user_edit_profile */ ?>

                                    <h5 class="mb-3"><?php echo $lang->get('profile_preferences_display'); ?></h5>
                                    <div class="form-row">
                                    <?php if (($SETTINGS['disable_user_edit_timezone'] ?? '0') === '0') : ?>
                                        <div class="form-group col-md-6">
                                            <label for="profile-user-timezone"><?php echo $lang->get('timezone_selection');?></label>
                                            <select class="form-control" id="profile-user-timezone">
                                                <?php foreach ($zones as $key => $zone): ?>
                                                    <option value="<?php echo $key; ?>"<?php
                                                        if ($session->has('user-timezone'))
                                                            if($session->get('user-timezone') === $key)
                                                                echo ' selected';
                                                            elseif ($session->get('user-timezone') === 'not_defined')
                                                                if (isset($SETTINGS['timezone']) && $SETTINGS['timezone'] === $key)
                                                                    echo ' selected';
                                                    ?>><?php echo $zone; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endif; /* disable_user_edit_timezone */
                                    if (($SETTINGS['disable_user_edit_language'] ?? '0') === '0') : ?>
                                        <div class="form-group col-md-6">
                                            <label for="profile-user-language"><?php echo $lang->get('language'); ?></label>
                                            <select class="form-control" id="profile-user-language">
                                                <?php
                                                    foreach ($languages as $language) {
                                                        echo '<option value="' . strval($language['name']) . '"',
                                                        strtolower(strval($session->get('user-language'))) === strtolower(strval($language['name'])) ?
                                                        ' selected="selected"' : '',
                                                    '>' . strval($language['label']) . '</option>';
                                                    }
                                                ?>
                                            </select>
                                        </div>
                                    <?php endif; /* disable_user_edit_language */ ?>
                                    </div>

                                    <div class="custom-control custom-switch mb-2">
                                        <input type="checkbox" class="custom-control-input" id="profile-user-split_view_mode"<?php echo (int) $session->get('user-split_view_mode') === 1 ? ' checked' : ''; ?>>
                                        <label class="custom-control-label" for="profile-user-split_view_mode"><?php echo $lang->get('items_page_split_view_mode'); ?></label>
                                    </div>

                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="profile-user-show_subfolders"<?php echo (int) $session->get('user-show_subfolders') === 1 ? ' checked' : ''; ?>>
                                        <label class="custom-control-label" for="profile-user-show_subfolders"><?php echo $lang->get('show_subfolders_in_main_view'); ?></label>
                                    </div>

                                    <div id="profile-avatar-file-container" class="hidden"></div>
                                    <div id="profile-avatar-file-list" class="hidden"></div>
                                    <input type="hidden" id="profile-user-token">
                                </div>
                                <div class="card-footer">
                                    <button type="button" class="btn btn-info" id="profile-user-save-settings"><?php echo $lang->get('save'); ?></button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- ACTIVITY -->
                    <div class="tab-pane<?php echo $activeTab === 'timeline' ? ' active' : ''; ?>" id="tab_timeline">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fa-solid fa-clock-rotate-left mr-2"></i><?php echo $lang->get('profile_activity_title'); ?></h3>
                            </div>
                            <div class="card-body pb-0">
                                <p class="text-muted mb-0"><?php echo $lang->get('profile_activity_tip'); ?></p>
                            </div>
                            <ul class="list-group list-group-flush">
                                <?php
                                // qui also stores IP addresses: compared as an integer, '10.0.0.5'
                                // would match user 10, so it is compared as text.
                                $rows = DB::query(
                                    'SELECT label AS labelAction, date, null
                                            FROM ' . prefixTable('log_system') . '
                                            WHERE qui = %s
                                            UNION
                                            SELECT l.action, l.date, i.label AS itemLabel
                                            FROM ' . prefixTable('log_items') . ' AS l
                                            INNER JOIN ' . prefixTable('items') . ' AS i ON (l.id_item = i.id)
                                            WHERE l.id_user = %i AND l.action IN ("at_access")
                                            ORDER BY date DESC
                                            LIMIT 0, 40',
                                    (string) $session->get('user-id'),
                                    $session->get('user-id')
                                );
                                foreach ($rows as $record) {
                                    if (substr($record['labelAction'], 0, 3) === 'at_') {
                                        $text = $lang->get(substr($record['labelAction'], 3));
                                    } else {
                                        $text = $lang->get($record['labelAction']);
                                    }
                                    if (empty($record['NULL']) === false) {
                                        $text .= ' ' . $lang->get('for') . ' <span class="font-weight-light">' . addslashes($record['NULL']) . '</span>';
                                    }
                                    echo '<li class="list-group-item">' . date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], intval($record['date'])) . ' - ' . $text . '</li>';
                                }
                                ?>
                            </ul>
                        </div>
                    </div>

                    <!-- HELP AND ADVANCED -->
                    <div class="tab-pane<?php echo $activeTab === 'advanced' ? ' active' : ''; ?>" id="tab_advanced">
                        <div class="callout callout-info">
                            <i class="fa-solid fa-circle-info mr-2"></i><?php echo $lang->get('profile_advanced_notice'); ?>
                        </div>

                        <?php if ($personalItemsCheckShown === true) : ?>
                        <div class="card" id="personal-sharekeys-block">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fa-solid fa-key mr-2"></i><?php echo $lang->get('profile_personal_items_check_title'); ?></h3>
                            </div>
                            <div class="card-body">
                                <p class="text-muted"><?php echo $lang->get('profile_personal_items_check_tip'); ?></p>
                                <button type="button" class="btn btn-primary" id="personal-sharekeys-repair"><i class="fa-solid fa-wrench mr-1"></i><?php echo $lang->get('profile_personal_items_check_button'); ?></button>
                                <span class="ml-2" id="personal-sharekeys-result"></span>
                            </div>
                        </div>
                        <?php endif;

                        if ($apiFeatureEnabled === true) :
                            // Why /authorize would refuse this key, the most blocking reason first
                            $apiKeyWarning = '';
                            if ($userApiAccessEnabled === false) {
                                $apiKeyWarning = $lang->get('user_profile_api_access_disabled');
                            } elseif ($session->get('user-special') === 'recrypt-private-key') {
                                $apiKeyWarning = $lang->get('user_profile_api_key_needs_recrypt');
                            }
                            ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-paper-plane mr-2"></i><?php echo $lang->get('profile_api_key_title'); ?></h3>
                            </div>
                            <div class="card-body">
                                <p class="text-muted"><?php echo $lang->get('profile_api_key_tip'); ?></p>
                                <div class="d-flex flex-wrap align-items-center">
                                    <code class="mr-3 my-1" style="word-break: break-all;" id="profile-user-api-token"><?php echo null !== $session->get('user-api_key') ? $session->get('user-api_key') : ''; ?></code>
                                    <button class="btn btn-sm btn-outline-secondary mr-1 my-1" id="copy-api-key"><i class="fa-regular fa-copy mr-1"></i><?php echo $lang->get('copy'); ?></button>
                                    <button class="btn btn-sm btn-outline-secondary my-1" id="generate-api-key"><i class="fa-solid fa-rotate mr-1"></i><?php echo $lang->get('profile_api_key_generate'); ?></button>
                                </div>
                                <?php if ($apiKeyWarning !== '') : ?>
                                <small class="form-text text-warning" id="profile-user-api-access-warning"><i class="fa-solid fa-triangle-exclamation mr-1"></i><?php echo $apiKeyWarning; ?></small>
                                <?php elseif ($session->get('user-auth_type') === 'ldap') :
                                    // The API only knows the directory password seen at the last web sign-in ?>
                                <small class="form-text text-muted"><?php echo $lang->get('user_profile_api_key_ldap_tip'); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php // Open API connections (one per issued JWT) — list and revoke ?>
                        <div class="card" id="api-sessions-block">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fa-solid fa-tower-broadcast mr-2"></i><?php echo $lang->get('profile_api_connections_title'); ?></h3>
                            </div>
                            <div class="card-body">
                                <p class="text-muted"><?php echo $lang->get('profile_api_connections_tip'); ?></p>
                                <div id="api-sessions-list"></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fa-solid fa-circle-info mr-2"></i><?php echo $lang->get('profile_technical_details'); ?></h3>
                            </div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-sm-4"><i class="fas fa-cloud-upload-alt fa-fw mr-2"></i><?php echo $lang->get('upload_feature'); ?></dt>
                                    <dd class="col-sm-8"><span id="profile-plupload-runtime" class="text-danger" data-enabled="0"><?php echo $lang->get('error_upload_runtime_not_found'); ?></span></dd>
                                    <dt class="col-sm-4"><i class="fas fa-stream fa-fw mr-2"></i><?php echo $lang->get('tree_load_strategy'); ?></dt>
                                    <dd class="col-sm-8 mb-0"><?php echo null !== $session->get('user-tree_load_strategy') ? $session->get('user-tree_load_strategy') : ''; ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- /.tab-content -->

                <?php
                if ($extensionTokensAllowed === true) { ?>
                <div class="modal fade" id="extension-token-modal" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fa-solid fa-puzzle-piece mr-2"></i><?php echo $lang->get('extension_tokens'); ?></h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-warning" role="alert"><i class="fa-solid fa-triangle-exclamation mr-2"></i><?php echo $lang->get('extension_token_copy_once'); ?></div>
                                <div class="input-group">
                                    <input type="text" readonly class="form-control" id="extension-token-value">
                                    <div class="input-group-append">
                                        <button class="btn btn-primary" type="button" id="copy-extension-token" title="<?php echo $lang->get('copy_to_clipboard'); ?>"><i class="fa-regular fa-copy"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>

        </div>
        <!-- /.row -->
    </div>
    <!-- /.container-fluid -->
</div>
<!-- /.content -->
