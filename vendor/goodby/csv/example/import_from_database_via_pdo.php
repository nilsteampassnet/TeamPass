<?php

require_once __DIR__.'/../vendor/autoload.php'; // load composer

use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\LexerConfig;

$pdo = new PDO('mysql:host=localhost;dbname=test', 'root', 'root');
$pdo->query('CREATE TABLE IF NOT EXISTS user (id INT, `name` VARCHAR(255), email VARCHAR(255))');

$config = new LexerConfig();
$lexer = new Lexer($config);

$interpreter = new Interpreter();

$interpreter->addObserver(function(array $columns) use ($pdo) {
    $checkStmt = $pdo->prepare('SELECT count(*) FROM user WHERE id = ?');
    $checkStmt->execute(array(($columns[0])));

    $count = $checkStmt->fetchAll()[0][0];

    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO user (id, name, email) VALUES (?, ?, ?)');
        $stmt->execute($columns);
    }
});

$lexer->parse('user.csv', $interpreter);
