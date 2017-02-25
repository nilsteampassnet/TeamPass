<?php
/**
 * @file          views.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
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
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

//Load file
require_once 'views.load.php';

// show TABS permitting to select specific actions
echo '
<div id="tabs">
    <ul>
        <li><a href="#tabs-1">'.$LANG['logs_passwords'].'</a></li>
        <li><a href="#tabs-2">'.$LANG['deletion'].'</a></li>
        <li><a href="views_logs.php"><i id="loader_1" style="display:none;" class="fa fa-cog fa-spin"></i>&nbsp;'.$LANG['logs'].'</a></li>
        <li><a href="#tabs-4">'.$LANG['renewal_menu'].'</a></li>
        <li><a href="views_database.php">'.$LANG['database_menu'].'</a></li>
    </ul>';

    //TAB 1 - Log password
    echo '
    <div id="tabs-1">
        <p>
        '.$LANG['logs_1'].' : <input type="text" id="log_jours" />&nbsp;
		<span class="fa-stack tip" title="'.htmlentities(strip_tags($LANG['pw_generate']), ENT_QUOTES).'" onclick="GenererLog()" style="cursor:pointer;">
			<i class="fa fa-square fa-stack-2x"></i>
			<i class="fa fa-cogs fa-stack-1x fa-inverse"></i>
		</span>&nbsp;
        </p>
        <div id="lien_pdf" style="text-align:center; width:100%; margin-top:15px;"></div>
    </div>';

    //TAB 2 - DELETION
    echo '
    <div id="tabs-2">
        <h3>'.$LANG['deletion_title'].'</h3>
        <div id="liste_elems_del" style="margin-left:30px;margin-top:10px;"></div>
    </div>';

    //TAB 4 - RENEWAL
    echo '
    <div id="tabs-4">
        '.$LANG['renewal_selection_text'].'
        <select id="expiration_period">
            <option value="0">'.$LANG['expir_today'].'</option>
            <option value="1month">'.$LANG['expir_one_month'].'</option>
            <option value="6months">'.$LANG['expir_six_months'].'</option>
            <option value="1year">'.$LANG['expir_one_year'].'</option>
        </select>
		<span class="fa-stack tip" title="'.htmlentities(strip_tags($LANG['pw_generate']), ENT_QUOTES).'" onclick="generate_renewal_listing()" style="cursor:pointer;">
			<i class="fa fa-square fa-stack-2x"></i>
			<i class="fa fa-cogs fa-stack-1x fa-inverse"></i>
		</span>&nbsp;
        <span id="renewal_icon_pdf" style="margin-left:15px;display:none;cursor:pointer;" title="'.htmlentities(strip_tags($LANG['generate_pdf']), ENT_QUOTES).'" onclick="generate_renewal_pdf()">
            <span class="fa fa-file-pdf-o"></span>
        </span>
        <div id="list_renewal_items" style="width:700px;margin:10px auto 0 auto;"></div>
        <input type="hidden" id="list_renewal_items_pdf" />
    </div>';

    echo '
</div>
<input type="hidden" id="tab2_action" />
';

// Deletion / Restoration dialogbox
echo '
<div id="tab2_dialog" style="display:none;">
    <div style="display:none;text-align:center;padding:2px;" class="ui-state-error ui-corner-all" id="tab2_dialog_error"></div>
    <div style="text-align:center;padding:2px;" id="tab2_dialog_html"></div>
</div>';
