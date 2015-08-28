<?php
// load library
require_once './includes/libraries/Authentication/Duo/duo_web.php';

$sig_request = Duo::signRequest(IKEY, SKEY, AKEY, $_POST['login']);