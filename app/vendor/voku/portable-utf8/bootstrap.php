<?php

use voku\helper\Bootup;
use voku\helper\UTF8;

Bootup::initAll(); // Initialize portable-utf8 bootstrap hooks
UTF8::checkForSupport(); // Check UTF-8 support for PHP
