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
 * @file      2fa.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

if ($session->get('key') === null) {
    die('Hacking attempt...');
}

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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('mfa') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    /**
     * Relying party ID of the sign-in passkeys. Saved here instead of by the generic handler of
     * admin.js.php: moving the passkeys to another domain makes every registered one unusable,
     * so the administrator confirms first. The server refuses a value that does not suit the
     * TeamPass URL; the same rule decides here whether the domain really changes.
     */
    $(document).on('change', '#webauthn_rp_id', function() {
        const $field = $(this);
        const host = String($field.attr('placeholder') || '');
        const typed = String($field.val() || '').trim().toLowerCase();
        const next = typed !== '' ? typed : host;
        const isIp = /^[0-9.]+$/.test(host) || host.indexOf(':') !== -1;
        const valid = next === host || (isIp === false && next.indexOf('.') !== -1 && host.endsWith('.' + next));
        const count = parseInt($field.attr('data-passkeys'), 10) || 0;
        const current = String($field.attr('data-effective') || '');

        if (valid === false || count === 0 || next === current) {
            saveFieldValue($field, 'webauthn_rp_id', false);
            if (valid === true) {
                $field.attr('data-effective', next);
            }
            return;
        }

        let confirmed = false;
        launchConfirmDialog(
            <?php echo json_encode($lang->get('webauthn_rp_id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
            $('<span>').text(
                <?php echo json_encode($lang->get('webauthn_rp_id_change_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
                    .replace('#count#', String(count))
                    .replace('#rp_id#', current)
            ).html(),
            function() {
                confirmed = true;
                saveFieldValue($field, 'webauthn_rp_id', false);
                $field.attr('data-effective', next);
            },
            <?php echo json_encode($lang->get('confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
            <?php echo json_encode($lang->get('cancel'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
        );
        // Cancelled: the field shows the value still in force
        $('#warningModal').one('hidden.bs.modal', function() {
            if (confirmed === false) {
                $field.val(current === host ? '' : current);
            }
        });
    });

    $(document).on('click', '#button-duo-config-check', function() {
        toastr
            .info('<?php echo $lang->get('loading_item'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // Prepare data
        var data = {
            'duo_ikey': sanitizeString($('#duo_ikey').val()),
            'duo_skey': sanitizeString($('#duo_skey').val()),
            'duo_host': sanitizeString($('#duo_host').val())
        }
        console.log(data);

        // Launch action
        $.post(
            'sources/admin.queries.php', {
                type: 'run_duo_config_check',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');

                if (data.error === true) {
                    // ERROR
                    toastr.remove();
                    toastr.warning(
                        data.message,
                        '', {
                            timeOut: 15000,
                            progressBar: true
                        }
                    );
                } else {
                    // Inform user
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('duo-config-check-success'); ?>',
                        '', {
                            timeOut: 5000
                        }
                    );
                }
            }
        );
    });


    //]]>
</script>
