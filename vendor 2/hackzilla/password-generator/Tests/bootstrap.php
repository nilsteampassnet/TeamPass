<?php

if (is_file('vendor/autoload.php')) {
    include 'vendor/autoload.php';
} elseif (is_file($autoloadFile = __DIR__.'/../vendor/autoload.php')) {
    require $autoloadFile;
} else {
    throw new \LogicException('Run "composer install --dev" to create autoloader.');
}
