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
 * @file      phpseclibv3_migration_modal.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;

$session = SessionManager::getSession();
$lang = new Language($session->get('user-language') ?? 'english');

// Check if phpseclib v3 migration modal should be displayed
$showModal = $session->get('phpseclibv3_migration_started') === true ||
             $session->get('phpseclibv3_migration_in_progress') === true;

if (!$showModal) {
    return;
}

$taskId = $session->get('phpseclibv3_migration_task_id');
$totalObjects = $session->get('phpseclibv3_migration_total') ?? 0;
?>

<!-- phpseclib v3 Migration Modal (Non-closable) -->
<div class="modal fade" id="phpseclibv3MigrationModal"
     data-backdrop="static"
     data-keyboard="false"
     tabindex="-1"
     role="dialog"
     aria-labelledby="phpseclibv3MigrationModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="phpseclibv3MigrationModalLabel">
                    <i class="fas fa-shield-alt mr-2"></i>
                    <?php echo $lang->get('phpseclibv3_migration_title') ?>
                </h5>
                <!-- No close button - modal is non-closable -->
            </div>

            <div class="modal-body">
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong><?php echo $lang->get('phpseclibv3_migration_info_title'); ?></strong><br>
                    <?php echo $lang->get('phpseclibv3_migration_info_text'); ?>
                </div>

                <!-- Progress Bar -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span><strong><?php echo $lang->get('progress'); ?>:</strong></span>
                        <span id="phpseclibv3-progress-percentage" class="font-weight-bold">0%</span>
                    </div>
                    <div class="progress" style="height: 35px;">
                        <div id="phpseclibv3-progress-bar"
                             class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                             role="progressbar"
                             style="width: 0%"
                             aria-valuenow="0"
                             aria-valuemin="0"
                             aria-valuemax="100">
                            <span id="phpseclibv3-progress-text" class="progress-bar-text">0%</span>
                        </div>
                    </div>
                </div>

                <!-- Status Information -->
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-2">
                            <strong><?php echo $lang->get('status'); ?>:</strong>
                            <span id="phpseclibv3-status-text" class="ml-2 badge badge-info">
                                <?php echo $lang->get('in_progress'); ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-2">
                            <strong><?php echo $lang->get('objects_to_migrate'); ?>:</strong>
                            <span id="phpseclibv3-total" class="ml-2"><?php echo number_format($totalObjects); ?></span>
                        </p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-2">
                            <strong><?php echo $lang->get('objects_migrated'); ?>:</strong>
                            <span id="phpseclibv3-completed" class="ml-2">0</span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-2">
                            <strong><?php echo $lang->get('objects_remaining'); ?>:</strong>
                            <span id="phpseclibv3-remaining" class="ml-2"><?php echo number_format($totalObjects); ?></span>
                        </p>
                    </div>
                </div>

                <!-- Spinner -->
                <div id="phpseclibv3-spinner" class="text-center mt-3">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                </div>

                <!-- Success Message (hidden initially) -->
                <div id="phpseclibv3-success-message" class="alert alert-success mt-3" style="display: none;" role="alert">
                    <i class="fas fa-check-circle mr-2"></i>
                    <strong><?php echo $lang->get('phpseclibv3_migration_completed'); ?></strong><br>
                    <?php echo $lang->get('phpseclibv3_migration_completed_text'); ?>
                </div>

                <!-- Error Message (hidden initially) -->
                <div id="phpseclibv3-error-message" class="alert alert-danger mt-3" style="display: none;" role="alert">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong><?php echo $lang->get('error'); ?></strong><br>
                    <span id="phpseclibv3-error-text"></span>
                </div>
            </div>

            <div class="modal-footer">
                <small class="text-muted mr-auto">
                    <i class="fas fa-info-circle"></i>
                    <?php echo $lang->get('phpseclibv3_migration_footer_note'); ?>
                </small>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const migrationTaskId = <?php echo json_encode($taskId); ?>;
    const migrationTotal = <?php echo json_encode($totalObjects); ?>;
    let migrationCheckInterval = null;
    let migrationCompleted = false;

    // Show modal on page load
    $('#phpseclibv3MigrationModal').modal('show');

    // Start automatic migration progress checking
    startMigrationProgressCheck();

    /**
     * Start polling for migration progress
     */
    function startMigrationProgressCheck() {
        if (migrationCheckInterval !== null) {
            return; // Already running
        }

        // Immediate first check
        checkMigrationProgress();

        // Then check every 2 seconds
        migrationCheckInterval = setInterval(checkMigrationProgress, 2000);
    }

    /**
     * Check migration progress via AJAX
     */
    function checkMigrationProgress() {
        if (migrationCompleted) {
            return;
        }

        $.ajax({
            url: 'sources/phpseclibv3_migration.queries.php',
            type: 'POST',
            dataType: 'json',
            data: {
                type: 'get_phpseclibv3_migration_progress',
                task_id: migrationTaskId
            },
            success: function(data) {
                if (data.error) {
                    handleMigrationError(data.error);
                    return;
                }

                updateMigrationUI(data);

                // Check if migration is completed
                if (data.status === 'completed') {
                    handleMigrationComplete();
                } else if (data.status === 'failed') {
                    handleMigrationError(data.error_message || 'Migration failed');
                }
            },
            error: function(xhr, status, error) {
                console.error('Migration progress check failed:', error);
                // Don't stop polling on transient errors
            }
        });
    }

    /**
     * Update UI with migration progress
     */
    function updateMigrationUI(data) {
        const completed = parseInt(data.completed) || 0;
        const total = parseInt(data.total) || migrationTotal;
        const remaining = parseInt(data.remaining) || 0; // Use remaining from server
        const percentage = total > 0 ? Math.round((completed / total) * 100) : 0;

        // Update progress bar
        $('#phpseclibv3-progress-bar')
            .css('width', percentage + '%')
            .attr('aria-valuenow', percentage);
        $('#phpseclibv3-progress-text').text(percentage + '%');
        $('#phpseclibv3-progress-percentage').text(percentage + '%');

        // Update counters
        $('#phpseclibv3-completed').text(completed.toLocaleString());
        $('#phpseclibv3-remaining').text(remaining.toLocaleString());
        $('#phpseclibv3-total').text(total.toLocaleString());

        // Debug logging (can be removed later)
        if (console && console.log) {
            console.log('Migration progress:', {
                completed: completed,
                remaining: remaining,
                total: total,
                percentage: percentage,
                status: data.status
            });
        }

        // Update status badge
        const statusBadge = $('#phpseclibv3-status-text');
        if (data.status === 'in_progress') {
            statusBadge.removeClass('badge-success badge-danger')
                      .addClass('badge-info')
                      .text('<?php echo $lang->get('in_progress'); ?>');
        } else if (data.status === 'pending') {
            statusBadge.removeClass('badge-success badge-danger')
                      .addClass('badge-warning')
                      .text('<?php echo $lang->get('pending'); ?>');
        }
    }

    /**
     * Handle migration completion
     */
    function handleMigrationComplete() {
        migrationCompleted = true;
        clearInterval(migrationCheckInterval);

        // Update UI
        $('#phpseclibv3-progress-bar')
            .removeClass('progress-bar-animated progress-bar-striped bg-primary')
            .addClass('bg-success')
            .css('width', '100%');
        $('#phpseclibv3-progress-text').text('100%');
        $('#phpseclibv3-progress-percentage').text('100%');

        $('#phpseclibv3-status-text')
            .removeClass('badge-info badge-warning')
            .addClass('badge-success')
            .text('<?php echo $lang->get('completed'); ?>');

        // Update counters to show completion
        const total = parseInt($('#phpseclibv3-total').text().replace(/,/g, '')) || 0;
        $('#phpseclibv3-completed').text(total.toLocaleString());
        $('#phpseclibv3-remaining').text('0');

        $('#phpseclibv3-spinner').hide();
        $('#phpseclibv3-success-message').slideDown();

        // Clear session variable via AJAX
        $.post('sources/phpseclibv3_migration.queries.php', {
            type: 'clear_phpseclibv3_migration_session'
        });

        // Auto-close modal after 3 seconds
        setTimeout(function() {
            $('#phpseclibv3MigrationModal').modal('hide');

            // Show success notification
            toastr.success(
                '<?php echo $lang->get('phpseclibv3_migration_completed'); ?>',
                '',
                {
                    timeOut: 5000,
                    progressBar: true
                }
            );
        }, 3000);
    }

    /**
     * Handle migration error
     */
    function handleMigrationError(errorMessage) {
        migrationCompleted = true;
        clearInterval(migrationCheckInterval);

        // Update UI
        $('#phpseclibv3-progress-bar')
            .removeClass('progress-bar-animated progress-bar-striped bg-primary')
            .addClass('bg-danger');

        $('#phpseclibv3-status-text')
            .removeClass('badge-info badge-warning')
            .addClass('badge-danger')
            .text('<?php echo $lang->get('failed'); ?>');

        $('#phpseclibv3-spinner').hide();
        $('#phpseclibv3-error-text').text(errorMessage);
        $('#phpseclibv3-error-message').slideDown();

        // Log error
        console.error('Migration error:', errorMessage);
    }
});
</script>

<style>
#phpseclibv3MigrationModal .progress {
    border-radius: 0.5rem;
    box-shadow: inset 0 1px 2px rgba(0,0,0,.1);
}

#phpseclibv3MigrationModal .progress-bar {
    font-size: 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
}

#phpseclibv3MigrationModal .progress-bar-text {
    color: #fff;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}
</style>
