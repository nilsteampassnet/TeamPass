<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 *
 * @file      options.js.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2022 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

if (
    isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id']) === true
    || isset($_SESSION['key']) === false || empty($_SESSION['key']) === true
) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php') === true) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php') === true) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception('Error file "/includes/config/tp.config.php" not exists', 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'profile', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    $(document).on('click', '#button-find-options', function() {
        searchKeyword($('#find-options').val());
    });

    $('#find-options').on('keypress', function(e) {
        var code = e.keyCode || e.which,
            character = '';
        //console.log('> '+code)
        if (code === 13 || code === 8 || code === 46) {
            //console.log('GO')
        } else {
            character = String.fromCharCode(event.keyCode).toLowerCase();
        }

        // Launch
        searchKeyword($('#find-options').val() + character);
    });


    function searchKeyword(criteria) {
        var rows = $('[data-keywords*="' + criteria + '"]');

        if (rows.length > 0) {
            // HIde rows
            $('.option').addClass('hidden');

            // SHow
            $.each(rows, function(i, value) {
                $(value).removeClass('hidden');
            });
        } else {
            $('.option').removeClass('hidden');
        }
    }
</script>
