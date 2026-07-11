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
 * @file      reviews.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request AS SymfonyRequest;
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('reviews') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    let tpCurrentReviewId = 0

    const tpDecisionBadge = {
        'pending': '<span class="badge badge-warning"><?php echo addslashes($lang->get('access_reviews_decision_pending')); ?></span>',
        'attested': '<span class="badge badge-success"><?php echo addslashes($lang->get('access_reviews_decision_attested')); ?></span>',
        'revoked': '<span class="badge badge-danger"><?php echo addslashes($lang->get('access_reviews_decision_revoked')); ?></span>',
    }

    // Initial load
    $(function() {
        if ($('#reviews-list').length > 0) {
            tpLoadReviews()
        }
    })

    /**
     * Load and render the campaigns list.
     */
    function tpLoadReviews() {
        $.post(
            'sources/reviews.queries.php', {
                type: 'list_reviews',
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                if (debugJavascript === true) console.log(data);

                if (data.error !== false) {
                    toastr.remove();
                    toastr.error(data.message, '<?php echo addslashes($lang->get('caution')); ?>', { timeOut: 5000 });
                    return;
                }

                if (!Array.isArray(data.reviews) || data.reviews.length === 0) {
                    $('#reviews-list').html('<div class="alert alert-info m-3"><?php echo addslashes($lang->get('access_reviews_none')); ?></div>')
                    return
                }

                let html = '<table class="table table-bordered table-striped table-sm mb-0"><thead><tr>' +
                    '<th><?php echo addslashes($lang->get('label')); ?></th>' +
                    '<th><?php echo addslashes($lang->get('date')); ?></th>' +
                    '<th><?php echo addslashes($lang->get('author')); ?></th>' +
                    '<th><?php echo addslashes($lang->get('access_reviews_progress')); ?></th>' +
                    '<th><?php echo addslashes($lang->get('status')); ?></th>' +
                    '<th></th>' +
                    '</tr></thead><tbody>'

                data.reviews.forEach(review => {
                    const statusBadge = review.status === 'open' ?
                        '<span class="badge badge-info"><?php echo addslashes($lang->get('access_reviews_open')); ?></span>' :
                        '<span class="badge badge-secondary"><?php echo addslashes($lang->get('access_reviews_closed')); ?></span>'

                    html += '<tr>' +
                        '<td>' + htmlEncode(review.label) + '</td>' +
                        '<td>' + htmlEncode(review.started_at) + '</td>' +
                        '<td>' + htmlEncode(review.started_by) + '</td>' +
                        '<td>' + review.percent + '% (' + (review.attested + review.revoked) + '/' + review.total + ')' +
                            (review.revoked > 0 ? ' <span class="badge badge-danger">' + review.revoked + ' <?php echo addslashes($lang->get('access_reviews_decision_revoked')); ?></span>' : '') +
                        '</td>' +
                        '<td>' + statusBadge + '</td>' +
                        '<td class="text-right">' +
                            '<button class="btn btn-sm btn-primary review-open mr-1" data-id="' + review.id + '" data-label="' + htmlEncode(review.label) + '"><i class="fas fa-eye"></i></button>' +
                            '<button class="btn btn-sm btn-default review-export mr-1" data-id="' + review.id + '" data-label="' + htmlEncode(review.label) + '"><i class="fas fa-file-csv"></i></button>' +
                            (review.status === 'open' && review.pending === 0 ?
                                '<button class="btn btn-sm btn-success review-close" data-id="' + review.id + '"><i class="fas fa-check"></i> <?php echo addslashes($lang->get('access_reviews_close')); ?></button>' : '') +
                        '</td>' +
                        '</tr>'
                })

                html += '</tbody></table>'
                $('#reviews-list').html(html)
            }
        );
    }

    /**
     * Load and render the items of one campaign.
     * @param {number} reviewId - Campaign id
     */
    function tpLoadReviewItems(reviewId) {
        $.post(
            'sources/reviews.queries.php', {
                type: 'get_review_items',
                review_id: reviewId,
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                if (debugJavascript === true) console.log(data);

                if (data.error !== false) {
                    toastr.remove();
                    toastr.error(data.message, '<?php echo addslashes($lang->get('caution')); ?>', { timeOut: 5000 });
                    return;
                }

                tpCurrentReviewId = reviewId
                $('#review-detail-title').text(data.review.label)

                const reviewOpen = data.review.status === 'open'

                let html = '<table class="table table-bordered table-striped table-sm mb-0"><thead><tr>' +
                    '<th><?php echo addslashes($lang->get('role')); ?></th>' +
                    '<th><?php echo addslashes($lang->get('folder')); ?></th>' +
                    '<th><?php echo addslashes($lang->get('accesses')); ?></th>' +
                    '<th><?php echo addslashes($lang->get('access_reviews_decision')); ?></th>' +
                    '<th></th>' +
                    '</tr></thead><tbody>'

                data.items.forEach(item => {
                    let decisionCell = tpDecisionBadge[item.decision] || ''
                    if (item.decision !== 'pending' && item.decided_by !== '') {
                        decisionCell += ' <small class="text-muted">' + htmlEncode(item.decided_by) + ' — ' + htmlEncode(item.decided_at) + '</small>'
                    }

                    html += '<tr>' +
                        '<td>' + htmlEncode(item.role) + '</td>' +
                        '<td>' + htmlEncode(item.folder) + '</td>' +
                        '<td>' + htmlEncode(item.access) + '</td>' +
                        '<td>' + decisionCell + '</td>' +
                        '<td class="text-right">' +
                            (reviewOpen && item.decision === 'pending' ?
                                '<button class="btn btn-sm btn-success review-attest mr-1" data-id="' + item.id + '"><i class="fas fa-check mr-1"></i><?php echo addslashes($lang->get('access_reviews_attest')); ?></button>' +
                                (item.can_revoke === true ?
                                    '<button class="btn btn-sm btn-danger review-revoke" data-id="' + item.id + '"><i class="fas fa-ban mr-1"></i><?php echo addslashes($lang->get('access_reviews_revoke')); ?></button>'
                                    : '<span class="badge badge-light infotip" title="<?php echo addslashes($lang->get('access_reviews_revoke_readonly')); ?>"><i class="fas fa-lock mr-1"></i><?php echo addslashes($lang->get('read_only')); ?></span>')
                                : '') +
                        '</td>' +
                        '</tr>'
                })

                html += '</tbody></table>'
                $('#review-detail').html(html)
                $('#review-detail-card').show()
            }
        );
    }

    /**
     * Record a decision on a review item.
     * @param {number} itemId - Review item id
     * @param {string} decision - 'attested' or 'revoked'
     */
    function tpDecideReviewItem(itemId, decision) {
        toastr.remove();
        toastr.info('<?php echo addslashes($lang->get('in_progress')); ?> ... <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>');

        const data = {
            'item_id': itemId,
            'decision': decision,
        }

        $.post(
            'sources/reviews.queries.php', {
                type: 'decide_review_item',
                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                if (debugJavascript === true) console.log(data);

                toastr.remove();
                if (data.error !== false) {
                    toastr.error(data.message, '<?php echo addslashes($lang->get('caution')); ?>', { timeOut: 5000 });
                    return;
                }

                toastr.success('<?php echo addslashes($lang->get('done')); ?>', '', { timeOut: 1000 });
                tpLoadReviewItems(tpCurrentReviewId)
                tpLoadReviews()
            }
        );
    }

    // Launch a campaign
    $(document).on('click', '#review-start', function() {
        const label = $('#review-label').val().trim()
        if (label === '') {
            toastr.remove();
            toastr.warning('<?php echo addslashes($lang->get('error_empty_data')); ?>', '', { timeOut: 3000 });
            return
        }

        toastr.remove();
        toastr.info('<?php echo addslashes($lang->get('in_progress')); ?> ... <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>');

        const data = {
            'label': label,
            'folder_id': parseInt($('#review-folder').val()),
        }

        $.post(
            'sources/reviews.queries.php', {
                type: 'start_review',
                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                if (debugJavascript === true) console.log(data);

                toastr.remove();
                if (data.error !== false) {
                    toastr.error(data.message, '<?php echo addslashes($lang->get('caution')); ?>', { timeOut: 5000 });
                    return;
                }

                $('#review-label').val('')
                toastr.success('<?php echo addslashes($lang->get('done')); ?>', '', { timeOut: 1000 });
                tpLoadReviews()
                tpLoadReviewItems(data.review_id)
            }
        );
    })

    // Open a campaign detail
    $(document).on('click', '.review-open', function() {
        tpLoadReviewItems($(this).data('id'))
    })

    // Hide the detail panel
    $(document).on('click', '#review-detail-close-panel', function() {
        $('#review-detail-card').hide()
    })

    // Attest a grant
    $(document).on('click', '.review-attest', function() {
        tpDecideReviewItem($(this).data('id'), 'attested')
    })

    // Revoke a grant (with confirmation — it removes real access)
    $(document).on('click', '.review-revoke', function() {
        const itemId = $(this).data('id')
        toastr.remove();
        toastr.warning(
            '&nbsp;<button type="button" class="btn clear btn-toastr review-revoke-confirm" data-id="' + itemId + '" style="width:100%;"><?php echo addslashes($lang->get('please_confirm')); ?></button>',
            '<?php echo addslashes($lang->get('access_reviews_confirm_revoke')); ?>', {
                positionClass: 'toast-top-center',
                closeButton: true
            }
        );
    })
    $(document).on('click', '.review-revoke-confirm', function() {
        tpDecideReviewItem($(this).data('id'), 'revoked')
    })

    // Close a campaign
    $(document).on('click', '.review-close', function() {
        const data = {
            'review_id': parseInt($(this).data('id')),
        }

        $.post(
            'sources/reviews.queries.php', {
                type: 'close_review',
                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                if (debugJavascript === true) console.log(data);

                toastr.remove();
                if (data.error !== false) {
                    toastr.error(data.message, '<?php echo addslashes($lang->get('caution')); ?>', { timeOut: 5000 });
                    return;
                }

                toastr.success('<?php echo addslashes($lang->get('done')); ?>', '', { timeOut: 1000 });
                tpLoadReviews()
                $('#review-detail-card').hide()
            }
        );
    })

    // Export a campaign as CSV evidence
    $(document).on('click', '.review-export', function() {
        const reviewId = $(this).data('id')
        const reviewLabel = $(this).data('label')

        $.post(
            'sources/reviews.queries.php', {
                type: 'export_review',
                review_id: reviewId,
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                if (debugJavascript === true) console.log(data);

                if (data.error !== false || !data.csv) {
                    toastr.remove();
                    toastr.error(data.message || '<?php echo addslashes($lang->get('error_unknown')); ?>', '', { timeOut: 5000 });
                    return;
                }

                const blob = new Blob(['﻿' + data.csv], { type: 'text/csv;charset=utf-8;' })
                const link = document.createElement('a')
                link.href = URL.createObjectURL(blob)
                link.download = 'teampass-access-review-' + reviewId + '.csv'
                document.body.appendChild(link)
                link.click()
                document.body.removeChild(link)
                URL.revokeObjectURL(link.href)
            }
        );
    })

    //]]>
</script>
