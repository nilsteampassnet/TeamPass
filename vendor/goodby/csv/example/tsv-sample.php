<?php

require_once __DIR__.'/../vendor/autoload.php'; // load composer

use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\LexerConfig;

// the result comes into this variable
$temperature = array();

// set up lexer
$config = new LexerConfig();
$config->setDelimiter("\t");
$config->setFlags(\SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY | \SplFileObject::READ_CSV);
$lexer = new Lexer($config);

// set up interpreter
$interpreter = new Interpreter();
$interpreter->addObserver(function(array $row) use (&$temperature) {
    $temperature[] = array(
        'temperature' => $row[0],
        'city'        => $row[1],
    );
});

// parse
$lexer->parse('temperature.tsv', $interpreter);

var_dump($temperature);
