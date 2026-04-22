<?php

require_once __DIR__.'/../vendor/autoload.php'; // load composer

use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\LexerConfig;

$pdo = new PDO('mysql:host=localhost;dbname=test', 'root', 'root');
$pdo->query('CREATE TABLE IF NOT EXISTS user2 (id INT, `name` VARCHAR(255), email VARCHAR(255), PRIMARY KEY (`id`))');

$config = new LexerConfig();
$lexer = new Lexer($config);

$interpreter = new Interpreter();

$interpreter->addObserver(function(array $columns) use ($pdo) {
    $stmt = $pdo->prepare('REPLACE user2 (id, name, email) VALUES (?, ?, ?)');
    $stmt->execute($columns);
});

$lexer->parse('user.csv', $interpreter);
