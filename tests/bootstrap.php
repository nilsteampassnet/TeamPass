<?php

declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload) === false) {
    $autoload = __DIR__ . '/../app/vendor/autoload.php';
}

require_once $autoload;
