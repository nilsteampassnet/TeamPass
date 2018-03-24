<?php
/**
 * @file          kb.queries.categories.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2018 Nils Laumaillé
 * @licensing     GNU GPL-3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once 'SecureHandler.php';
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

global $k, $settings, $SETTINGS_EXT;
require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';
header("Content-type: text/x-json; charset=".$SETTINGS_EXT['charset']);
require_once 'main.functions.php';

//Connect to DB
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
$pass = defuse_return_decrypted($pass);
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = true;
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

//manage filtering
$sOutput = '';
if (!empty($_GET['term'])) {
    $rows = DB::query(
        "SELECT id, category FROM ".prefix_table("kb_categories")."
        WHERE category LIKE %ss
        ORDER BY category ASC",
        $_GET['term']
    );
} else {
    $rows = DB::query("SELECT id, category FROM ".prefix_table("kb_categories")." ORDER BY category ASC");
}
$counter = DB::count();

if ($counter > 0) {
    foreach ($rows as $record) {
        if (empty($sOutput)) {
            $sOutput = '"'.$record['category'].'"';
        } else {
            $sOutput .= ', "'.$record['category'].'"';
        }
    }

    //Finish the line
    echo '[ '.$sOutput.' ]';
}
