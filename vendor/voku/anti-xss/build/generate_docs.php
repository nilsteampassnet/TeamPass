<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/vendor/autoload.php';

$readmeText = (new \voku\PhpReadmeHelper\GenerateApi())->generate(
    __DIR__ . '/../src/voku/helper/AntiXSS.php',
    __DIR__ . '/docs/base.md'
);

file_put_contents(__DIR__ . '/../README.md', $readmeText);
