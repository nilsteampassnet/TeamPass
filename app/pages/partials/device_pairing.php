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
 * @file      device_pairing.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

// This private view is included by the authenticated profile, with its explicit display context.
if (!isset($lang, $devicePairingAllowed, $devicePairingServer, $devicePairingUnavailableReason)) {
    throw new LogicException('Missing device pairing view context');
}
$deviceEscape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<style>
    #external-devices-block .device-entry-name { overflow-wrap: anywhere; }
    #device-pairing-modal .device-qr-surface { background: #fff; padding: 18px; border-radius: .5rem; display: inline-block; max-width: 100%; }
    #device-pairing-qr canvas, #device-pairing-qr img { max-width: 100%; height: auto; margin: auto; }
    #device-pairing-modal .device-comparison-code { font-variant-numeric: tabular-nums; letter-spacing: .16em; font-size: 2.1rem; font-weight: 700; }
    #device-pairing-modal .device-instance { overflow-wrap: anywhere; }
    #device-pairing-modal .btn { white-space: normal; }
    @media (max-width: 575.98px) {
        #device-pairing-modal .modal-footer .btn { flex: 1 1 100%; margin: .2rem 0; }
        #device-pairing-decisions .btn { display: block; width: 100%; margin-right: 0; }
    }
</style>
<div class="card" id="external-devices-block">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-laptop mr-2" aria-hidden="true"></i><?php echo $deviceEscape($lang->get('device_pairing_title')); ?></h3>
        <div class="card-tools"><span class="badge badge-info"><?php echo $deviceEscape($lang->get('device_pairing_experimental')); ?></span></div>
    </div>
    <div class="card-body">
        <p class="text-muted"><?php echo $deviceEscape($lang->get('device_pairing_intro')); ?></p>
        <?php if ($devicePairingAllowed) : ?>
        <button type="button" class="btn btn-primary" id="device-pairing-open"><i class="fa-solid fa-qrcode mr-2" aria-hidden="true"></i><?php echo $deviceEscape($lang->get('device_pairing_associate')); ?></button>
        <?php else : ?>
        <div class="alert alert-light border mb-0"><?php echo $deviceEscape($devicePairingUnavailableReason); ?></div>
        <?php endif; ?>
    </div>
    <div class="card-body border-top">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
            <h4 class="h6 mb-2"><?php echo $deviceEscape($lang->get('device_pairing_inventory')); ?></h4>
            <button type="button" class="btn btn-sm btn-outline-secondary mb-2" id="device-pairing-refresh"><i class="fa-solid fa-rotate mr-1" aria-hidden="true"></i><?php echo $deviceEscape($lang->get('device_pairing_refresh')); ?></button>
        </div>
        <label class="sr-only" for="device-pairing-search"><?php echo $deviceEscape($lang->get('device_pairing_search')); ?></label>
        <input type="search" class="form-control form-control-sm mb-3" id="device-pairing-search" placeholder="<?php echo $deviceEscape($lang->get('device_pairing_search')); ?>" autocomplete="off">
        <div id="device-pairing-list" aria-live="polite"></div>
        <p class="text-muted small mt-3 mb-0"><?php echo $deviceEscape($lang->get('device_pairing_revoke_tip')); ?></p>
    </div>
</div>
<?php if ($devicePairingAllowed) : ?>
<div class="modal fade" id="device-pairing-modal" tabindex="-1" role="dialog" aria-labelledby="device-pairing-modal-title" aria-describedby="device-pairing-modal-intro" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="device-pairing-modal-title"><i class="fa-solid fa-link mr-2" aria-hidden="true"></i><?php echo $deviceEscape($lang->get('device_pairing_associate')); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo $deviceEscape($lang->get('device_pairing_close')); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p id="device-pairing-modal-intro"><?php echo $deviceEscape($lang->get('device_pairing_modal_intro')); ?></p>
                <p class="device-instance text-muted small"><i class="fa-solid fa-server mr-1" aria-hidden="true"></i><?php echo $deviceEscape($devicePairingServer); ?></p>
                <div class="alert alert-warning d-none" id="device-pairing-error" role="alert"></div>
                <div class="text-center py-2 d-none" id="device-pairing-qr-wrap" role="img" aria-label="<?php echo $deviceEscape($lang->get('device_pairing_qr_label')); ?>"><div class="device-qr-surface"><div id="device-pairing-qr"></div></div></div>
                <p class="text-center font-weight-bold my-3" id="device-pairing-status" role="status" aria-live="polite"></p>
                <p class="text-center text-muted small" id="device-pairing-expiry" role="timer" aria-live="off"></p>
                <div id="device-pairing-claim" class="d-none border rounded p-3 text-center">
                    <p class="mb-1 text-break font-weight-bold" id="device-pairing-device-name"></p>
                    <div class="device-comparison-code my-2" id="device-pairing-comparison"></div>
                    <p class="small text-muted mb-0"><?php echo $deviceEscape($lang->get('device_pairing_compare_tip')); ?></p>
                </div>
                <div class="form-group mt-3 d-none" id="device-pairing-password-group">
                    <label for="device-pairing-password"><?php echo $deviceEscape($lang->get('device_pairing_password')); ?></label>
                    <input type="password" class="form-control" id="device-pairing-password" autocomplete="current-password" maxlength="1024">
                </div>
                <div class="d-none mt-3" id="device-pairing-decisions">
                    <button type="button" class="btn btn-success mr-2 mb-2" id="device-pairing-approve"><i class="fa-solid fa-check mr-1" aria-hidden="true"></i><?php echo $deviceEscape($lang->get('device_pairing_approve')); ?></button>
                    <button type="button" class="btn btn-outline-danger mb-2" id="device-pairing-reject"><?php echo $deviceEscape($lang->get('device_pairing_reject')); ?></button>
                </div>
            </div>
            <div class="modal-footer d-flex flex-wrap justify-content-between">
                <button type="button" class="btn btn-outline-secondary mb-1" id="device-pairing-new"><?php echo $deviceEscape($lang->get('device_pairing_new')); ?></button>
                <button type="button" class="btn btn-secondary mb-1" data-dismiss="modal" id="device-pairing-cancel"><?php echo $deviceEscape($lang->get('device_pairing_cancel')); ?></button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
