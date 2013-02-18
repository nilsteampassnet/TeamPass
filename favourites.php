<?php
/**
 * @file 		favourites.php
 * @author		Nils Laumaillé
 * @version 	2.1.8
 * @copyright 	(c) 2009-2013 Nils Laumaillé
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

if (!isset($_SESSION['CPM'] ) || $_SESSION['CPM'] != 1)
    die('Hacking attempt...');

echo '
<form name="form_favourites" method="post" action="">
    <div class="title ui-widget-content ui-corner-all">'.$txt['my_favourites'].'</div>

    <div style="height:100%;overflow:auto;">';
    if ( empty($_SESSION['favourites']) )
        echo '
        ';
    else{
        echo '
        <table id="t_items" style="empty-cells:show;width:100%;" cellspacing="0" cellpadding="5">
            <thead><tr>
                <th style="width:55px;"></th>
                <th style="min-width:15%;">'.$txt['label'].'</th>
                <th style="min-width:50%;">'.$txt['description'].'</th>
                <th style="min-width:20%;">'.$txt['group'].'</th>
            </tr></thead>
            <tbody>';
            //Get favourites
            $cpt= 0 ;
            foreach ($_SESSION['favourites'] as $fav) {
                if ( !empty($fav) ) {
                    $data = $db->query_first(
                        "SELECT i.label, i.description, i.id, i.id_tree, t.title
                        FROM ".$pre."items AS i
                        INNER JOIN ".$pre."nested_tree AS t ON (t.id = i.id_tree)
                        WHERE i.id = ".$fav);
                    if (!empty($data['label'])) {
                        echo '
                            <tr class="ligne'.($cpt%2).'">
                                <td>
                                    <img src="includes/images/key__arrow.png" onClick="javascript:window.location.href = \'index.php?page=items&amp;group='.$data['id_tree'].'&amp;id='.$data['id'].'\';" style="cursor:pointer;" />
                                    &nbsp;
                                    <img src="includes/images/favourite_delete.png" onClick="prepare_delete_fav(\''.$data['id'].'\');" style="cursor:pointer;" title="'.$txt['item_menu_del_from_fav'].'" />
                                </td>
                                <td align="left">'.stripslashes($data['label']).'</td>
                                <td align="center">'.stripslashes($data['description']).'</td>
                                <td align="center">',$data['title'] == $_SESSION['user_id']?$_SESSION['login']:$data['title'],'</td>
                            </tr>';
                        $cpt++;
                    }
                }
            }
            echo '
            </tbody>
        </table>';
    }
    echo '
    </div>
</form>';

// DIV FOR FAVOURITES DELETION
echo '
<div id="div_delete_fav" style="display:none;">
    '.$txt['confirm_del_from_fav'].'
    <input type="hidden" id="detele_fav_id" />
</div>';
