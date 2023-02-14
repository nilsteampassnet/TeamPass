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
 * @version   3.0.0.23
 * @file      users.background.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

require_once 'SecureHandler.php';
session_name('teampass_session');
session_start();
if (! isset($_SESSION['CPM']) || $_SESSION['CPM'] === false || ! isset($_SESSION['key']) || empty($_SESSION['key'])) {
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

// Do checks
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'folders', $SETTINGS) === false) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit;
}

require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user']['user_language'].'.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
require_once 'main.functions.php';
// Connect to mysql server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = DB_PASSWD_CLEAR;
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;
DB::$ssl = DB_SSL;
DB::$connect_options = DB_CONNECT_OPTIONS;

$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

// Check KEY
if ($post_key !== $_SESSION['key']) {
    echo prepareExchangedData(
        $SETTINGS['cpassman_dir'],
        array(
            'error' => true,
            'message' => langHdl('key_is_not_correct'),
        ),
        'encode'
    );
} elseif ($_SESSION['user_read_only'] === true) {
    echo prepareExchangedData(
        $SETTINGS['cpassman_dir'],
        array(
            'error' => true,
            'message' => langHdl('error_not_allowed_to'),
        ),
        'encode'
    );
}

// decrypt and retrieve data in JSON format
$dataReceived = prepareExchangedData(
    $SETTINGS['cpassman_dir'],
    $post_data,
    'decode'
);

// Prepare variables
$type = filter_var($dataReceived['type'], FILTER_SANITIZE_STRING);
$user_id = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);
$creator_id = filter_var($dataReceived['creator_id'], FILTER_SANITIZE_NUMBER_INT);
$creator_pwd = filter_var($dataReceived['creator_pwd'], FILTER_SANITIZE_STRING);

// Store process
DB::insert(
    prefixTable('processes'),
    array(
        'created_at' => time(),
        'owner_id' => (int) $creator_id,
        'process_type' => (string) $type,
        'arguments' => json_encode([
            'user_id' => $user_id ,
            'creator_pwd' => $creator_pwd,
        ]),
    )
);

// Now let the cron manage the action
/*
$queue_id = DB::insertId();

require_once $SETTINGS['cpassman_dir'].'/includes/libraries/BackgroundProcesser/BackgroundProcess.php';
$random = rand(5, 15);
$process = new Cocur\BackgroundProcess\BackgroundProcess('php -f '.$SETTINGS['cpassman_dir'].'/scripts/process_new_user.php '.$queue_id);
$process->run('/tmp/out_' . $random, true);

//echo sprintf('%s %s %s 2>&1 & echo $!', 'php '.$SETTINGS['cpassman_dir'].'/scripts/process_new_user.php '.$queue_id, '>', '/tmp/out_' . $random);

echo "PROCESS ID:".$process->getPid()."<br>";

DB::update(
    prefixTable('processes'),
    array(
        'process_id' => (int) $process->getPid(),
    ),
    'increment_id = %i',
    $queue_id
);
*/




// Class loader
/*require_once $SETTINGS['cpassman_dir'].'/includes/libraries/BackgroundProcesser/BackgroundProcess.php';
$proc = new BackgroundProcess('exec php '.$SETTINGS['cpassman_dir'].'/scripts/process_new_user.php '.$queue_id, true);
$pid = $proc->getProcessId();
*/

//require_once $SETTINGS['cpassman_dir'].'/includes/libraries/BackgroundProcesser/background_process.php';





function execInBackground($cmd) {
    if (substr(php_uname(), 0, 7) == "Windows"){
        pclose(popen("start /B ". $cmd, "r"));
    }
    else {
        exec($cmd . " > /dev/null &"); 
    }
}

function run_process($cmd, $outputFile = '/dev/null', $append = false){
    $pid=0;
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {//'This is a server using Windows!';
        $cmd = 'wmic process call create "'.$cmd.'" | find "ProcessId"';
        $handle = popen("start /B ". $cmd, "r");
        $read = fread($handle, 200); //Read the output 
        $pid=substr($read,strpos($read,'=')+1);
        $pid=substr($pid,0,strpos($pid,';') );
        $pid = (int)$pid;
        pclose($handle); //Close
}else{
    $pid = (int)shell_exec(sprintf('%s %s %s 2>&1 & echo $!', $cmd, ($append) ? '>>' : '>', $outputFile));
}
return $pid;
}

/*
$cmd='';
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {//'This is a server using Windows!';
    $cmd=  $php_path.'\php.exe '.$path.'\long_process.php' ;	
}else{
    $cmd = 'php -f '.$SETTINGS['cpassman_dir'].'/scripts/process_new_user.php '.$queue_id;
}
$pid=run_process($cmd);
if(is_process_running($pid)){
    echo 'Process running';
    //stop_process($pid);
}else{
    echo 'Process not running';
}
*/



/*
$random = rand(5, 15);
$outPath = ' 1> /tmp/out_' . $random . ' 2> /tmp/error_out_' . $random;
exec('php '.$SETTINGS['cpassman_dir'].'/scripts/process_new_user.php '.$queue_id .' > /dev/null 2>&1 '.$outPath.' & echo $!', $process, $retval);
$pid = $process[0];
print_r($process);
echo "  ; ; ".$retval;
*/


