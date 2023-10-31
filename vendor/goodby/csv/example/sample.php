<?php

require_once __DIR__.'/../vendor/autoload.php'; // load composer

use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\LexerConfig;
use Goodby\CSV\Export\Standard\Exporter;
use Goodby\CSV\Export\Standard\ExporterConfig;

$pdo = new PDO('mysql:host=localhost;dbname=test', 'root', 'root', array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
));
$pdo->query('CREATE TABLE IF NOT EXISTS user (id INT, `name` VARCHAR(255), email VARCHAR(255))');

// Importing
$config = new LexerConfig();
$lexer = new Lexer($config);
$interpreter = new Interpreter();
$interpreter->addObserver(function(array $columns) use ($pdo) {
    $stmt = $pdo->prepare('INSERT INTO user (id, name, email) VALUES (?, ?, ?)');
    $stmt->execute($columns);
});
$lexer->parse('user.csv', $interpreter);

// Exporting
$config = new ExporterConfig();
$exporter = new Exporter($config);
$exporter->export('php://output', array(
    array('1', 'alice', 'alice@example.com'),
    array('2', 'bob', 'bob@example.com'),
    array('3', 'carol', 'carol@example.com'),
));

