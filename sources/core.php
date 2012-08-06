<?php
/**
 * @file 		core.php
 * @author		Nils Laumaillé
 * @version 	2.1.8
 * @copyright 	(c) 2009-2011 Nils Laumaillé
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

//session_start();
if (!isset($_SESSION['CPM'] ) || $_SESSION['CPM'] != 1)
	die('Hacking attempt...');


/* CHECK IF UPDATE IS NEEDED */
    if ( isset($_SESSION['settings']['update_needed']) && ($_SESSION['settings']['update_needed'] != false || empty($_SESSION['settings']['update_needed'])) ){
        $row = $db->fetch_row("SELECT valeur FROM ".$pre."misc WHERE type = 'admin' AND intitule = 'cpassman_version'");
        if ( $row[0] != $k['version'] ){
            $_SESSION['settings']['update_needed'] = true;
        }else{
            $_SESSION['settings']['update_needed'] = false;
        }
    }

/* LOAD CPASSMAN SETTINGS */
    $_SESSION['settings']['duplicate_folder'] = 0;  //by default, this is false;
    $_SESSION['settings']['duplicate_item'] = 0;  //by default, this is false;
    $_SESSION['settings']['number_of_used_pw'] = 5; //by default, this value is 5;

    $rows = $db->fetch_all_array("SELECT * FROM ".$pre."misc WHERE type = 'admin' OR type = 'settings'");
    foreach( $rows as $reccord ){
    	if($reccord['type'] == 'admin') {
    		$_SESSION['settings'][$reccord['intitule']] = $reccord['valeur'];
    	}else{
    		$settings[$reccord['intitule']] = $reccord['valeur'];
    	}

    }

$rows = $db->fetch_all_array("SELECT valeur,intitule FROM ".$pre."misc WHERE type = 'admin'");
foreach( $rows as $reccord ){
	$_SESSION['settings'][$reccord['intitule']] = $reccord['valeur'];
}


//pw complexity levels
$pw_complexity = array(
    0=>array(0,$txt['complex_level0']),
    25=>array(25,$txt['complex_level1']),
    50=>array(50,$txt['complex_level2']),
    60=>array(60,$txt['complex_level3']),
    70=>array(70,$txt['complex_level4']),
    80=>array(80,$txt['complex_level5']),
    90=>array(90,$txt['complex_level6'])
);


//Load Languages stuff
if(empty($languages_dropmenu)){
	$languages_dropmenu = "";
	$languages_list = array();
	$rows = $db->fetch_all_array("SELECT * FROM ".$pre."languages GROUP BY name ORDER BY name ASC");
	foreach( $rows as $reccord ){
		$languages_dropmenu .= '<li><a href="#"><img class="flag" src="includes/images/flags/'.$reccord['flag'].'" alt="'.$reccord['label'].'" title="'.$reccord['label'].'" onclick="ChangeLanguage(\''.$reccord['name'].'\')" /></a></li>';
		array_push($languages_list, $reccord['name']);
		if(isset($_SESSION['user_language']) && $reccord['name'] == $_SESSION['user_language']){
			$_SESSION['user_language_flag'] = $reccord['flag'];
			$_SESSION['user_language_code'] = $reccord['code'];
			$_SESSION['user_language_label'] = $reccord['label'];
			$_SESSION['user_language_id'] = $reccord['id'];
		}
	}
}
/**
 * Define Timezone
 */
if (!isset($_SESSION['settings']['timezone'])) {
	$_SESSION['settings']['timezone'] = 'UTC';
}
date_default_timezone_set($_SESSION['settings']['timezone']);


/* CHECK IF MAINTENANCE MODE
* IF yes then authorize all ADMIN connections and
* reject all others
*/
    if ( isset($_SESSION['settings']['maintenance_mode']) && $_SESSION['settings']['maintenance_mode'] == 1 ){
        if ( isset($_SESSION['user_admin']) && $_SESSION['user_admin'] != 1 ){
            // Update table by deleting ID
            if ( isset($_SESSION['user_id']) )
                $db->query_update(
                    "users",
                    array(
                        'key_tempo' => ''
                    ),
                    "id=".$_SESSION['user_id']
                );

            //Log into DB the user's disconnection
            if ( isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] == 1 )
                logEvents('user_connection','disconnection',$_SESSION['user_id']);

        	syslog(LOG_WARNING, "Unlog user: ".date("Y/m/d H:i:s")." {$_SERVER['REMOTE_ADDR']} ({$_SERVER['HTTP_USER_AGENT']})");

            // erase session table
        	$_SESSION = array();

        	setcookie('pma_end_session');

            // Kill session
            session_destroy();

            // REDIRECTION PAGE ERREUR
            echo '
            <script language="javascript" type="text/javascript">
            <!--
            document.location.href="index.php?session=expired";
            -->
            </script>';
            exit;
        }
    }


/* LOAD INFORMATION CONCERNING USER */
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])){
        // query on user
        $sql="SELECT * FROM ".$pre."users WHERE id = '".$_SESSION['user_id']."'";
        $row = $db->query($sql);
        $data = $db->fetch_array($row);

    	//Check if user has been deleted or unlogged
		if (empty($data)) {
			// erase session table
			$_SESSION = array();

			// Kill session
			session_destroy();

			//redirection to index
			echo '
	        <script language="javascript" type="text/javascript">
	        <!--
	        document.location.href="index.php";
	        -->
	        </script>';
		}else{
			// update user's rights
			$_SESSION['user_admin'] = $data['admin'];
			$_SESSION['user_gestionnaire'] = $data['gestionnaire'];
			$_SESSION['groupes_visibles'] = array();
			$_SESSION['groupes_interdits'] = array();
			if ( !empty($data['groupes_visibles'])) $_SESSION['groupes_visibles'] = @implode(';',$data['groupes_visibles']);
			if ( !empty($data['groupes_interdits'])) $_SESSION['groupes_interdits'] = @implode(';',$data['groupes_interdits']);

			$db->query_update(
			"users",
			array(
				'timestamp'=>mktime(date("h"),date("i"),date("s"),date("m"),date("d"),date("Y"))
			),
			"id=".$_SESSION['user_id']
			);

			// get access rights
			IdentifyUserRights($data['groupes_visibles'],$data['groupes_interdits'],$data['admin'],$data['fonction_id'],false);
		}
    }


/* CHECK IF LOGOUT IS ASKED OR IF SESSION IS EXPIRED */
    if ( (isset($_POST['menu_action']) && $_POST['menu_action'] == "deconnexion") || (isset($_GET['session']) && $_GET['session'] == "expired") || (isset($_POST['session']) && $_POST['session'] == "expired")){
        // Update table by deleting ID
        if ( isset($_SESSION['user_id']) )
            $db->query_update(
                "users",
                array(
                    'key_tempo' => ''
                ),
                "id=".$_SESSION['user_id']
            );

        //Log into DB the user's disconnection
        if ( isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] == 1 )
            logEvents('user_connection','disconnection',@$_SESSION['user_id']);

        // erase session table
        $_SESSION = array();

        // Kill session
        session_destroy();

        // REDIRECTION PAGE ERREUR
        echo '
        <script language="javascript" type="text/javascript">
        <!--
        document.location.href="index.php";
        -->
        </script>';
        exit;
    }


/*
* CHECK PASSWORD VALIDITY
* Don't take into consideration if LDAP in use
*/
	$nb_jours_avant_expiration_du_mdp = "";	//initiliaze variable
	if (isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1) {
		$_SESSION['validite_pw'] = true;
		$_SESSION['last_pw_change'] = true;
	}else{
		if ( isset($_SESSION['last_pw_change']) ){
			if ( $_SESSION['settings']['pw_life_duration'] == 0 ){
				$nb_jours_avant_expiration_du_mdp = "infinite";
				$_SESSION['validite_pw'] = true;
			}else{
				$nb_jours_avant_expiration_du_mdp = $_SESSION['settings']['pw_life_duration'] - round( (mktime(0,0,0,date('m'),date('d'),date('y'))-$_SESSION['last_pw_change'])/(24*60*60) );
				if ( $nb_jours_avant_expiration_du_mdp <= 0 )
					$_SESSION['validite_pw'] = false;
				else
					$_SESSION['validite_pw'] = true;
			}
		}else{
			$_SESSION['validite_pw'] = false;
		}
	}

/* CHECK IF SESSION EXISTS AND IF SESSION IS VALID */
    if ( !empty($_SESSION['fin_session']) ) {
        $data_session = $db->fetch_row("SELECT key_tempo FROM ".$pre."users WHERE id=".$_SESSION['user_id']);
    }else
        $data_session[0] = "";

    if ( isset($_SESSION['user_id']) && ( empty($_SESSION['fin_session']) || $_SESSION['fin_session'] < time() || empty($_SESSION['key']) || $_SESSION['key'] != $data_session[0] ) ){
        // Update table by deleting ID
        $db->query_update(
            "users",
            array(
                'key_tempo' => ''
            ),
            "id=".$_SESSION['user_id']
        );

        //Log into DB the user's disconnection
        if ( isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] == 1 )
            logEvents('user_connection','disconnection',$_SESSION['user_id']);

        // erase session table
        $_SESSION = array();

        // Kill session
        session_destroy();

        //Redirection
        echo '
        <script language="javascript" type="text/javascript">
        <!--
        document.location.href="index.php";
        -->
        </script>';
    }

    /*
    * CHECK IF SENDING ANONYMOUS STATS
    */
    if ( isset($_SESSION['settings']['send_stats']) && $_SESSION['settings']['send_stats'] == 1 && isset($_SESSION['settings']['send_stats_time']) && !isset($_SESSION['temporary']['send_stats_done'])  ){
        if ( ( $_SESSION['settings']['send_stats_time'] + $k['one_month_seconds'] ) <= time() ){
            CPMStats();
            $_SESSION['temporary']['send_stats_done'] = true;   //permits to test only once by session
        }
    }

    /* CHECK NUMBER OF USER ONLINE */
    $query_count = $db->fetch_row("SELECT COUNT(*) FROM ".$pre."users WHERE timestamp >= '".(mktime(date('h'),date('m'),date('s'),date('m'),date('d'),date('y')) - 600)."'");
    $_SESSION['nb_users_online'] = $query_count[0];


?>