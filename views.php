<?php
/**
 * @file          views.php
 * @author        Nils Laumaillé
 * @version       2.1.19
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || 
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || 
    !isset($_SESSION['key']) || empty($_SESSION['key'])) 
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], curPage())) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include 'error.php';
    exit();
}

//Load file
require_once 'views.load.php';

// show TABS permitting to select specific actions
echo '
<div id="tabs">
    <ul>
        <li><a href="#tabs-1">'.$txt['logs_passwords'].'</a></li>
        <li><a href="#tabs-2">'.$txt['deletion'].'</a></li>
        <li><a href="views_logs.php">'.$txt['logs'].'</a></li>
        <li><a href="#tabs-4">'.$txt['renewal_menu'].'</a></li>
        <li><a href="views_database.php">'.$txt['database_menu'].'</a></li>
    </ul>';

    //TAB 1 - Log password
    echo '
    <div id="tabs-1">
        <p>
        '.$txt['logs_1'].' : <input type="text" id="log_jours" /> <img src="includes/images/asterisk.png" onClick="GenererLog()" style="cursor:pointer;" />
        </p>
        <div id="lien_pdf" style="text-align:center; width:100%; margin-top:15px;"></div>
    </div>';

    //TAB 2 - DELETION
    echo '
    <div id="tabs-2">
        <h3>'.$txt['deletion_title'].'</h3>
        <div id="liste_elems_del" style="margin-left:30px;margin-top:10px;"></div>
    </div>';

    //TAB 4 - RENEWAL
    echo '
    <div id="tabs-4">
        '.$txt['renewal_selection_text'].'
        <select id="expiration_period">
            <option value="0">'.$txt['expir_today'].'</option>
            <option value="1month">'.$txt['expir_one_month'].'</option>
            <option value="6months">'.$txt['expir_six_months'].'</option>
            <option value="1year">'.$txt['expir_one_year'].'</option>
        </select>
        <img src="includes/images/asterisk.png" style="cursor:pointer;" alt="" onclick="generate_renewal_listing()" />
        <span id="renewal_icon_pdf" style="margin-left:15px;display:none;cursor:pointer;"><img src="includes/images/document-pdf-text.png" alt="" title="'.$txt['generate_pdf'].'" onclick="generate_renewal_pdf()" /></span>
        <div id="list_renewal_items" style="width:700px;margin:10px auto 0 auto;"></div>
        <input type="hidden" id="list_renewal_items_pdf" />
    </div>';

    echo '
</div>
';
