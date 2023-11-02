<?php

require_once __DIR__.'/../vendor/autoload.php'; // load composer

use Goodby\CSV\Export\Standard\Exporter;
use Goodby\CSV\Export\Standard\ExporterConfig;

use Goodby\CSV\Export\Standard\Collection\PdoCollection;

$pdo = new PDO('mysql:host=localhost;dbname=test', 'root', 'root');

$pdo->query('CREATE TABLE IF NOT EXISTS user (id INT, `name` VARCHAR(255), email VARCHAR(255))');
$pdo->query("INSERT INTO user VALUES(1, 'alice', 'alice@example.com')");
$pdo->query("INSERT INTO user VALUES(2, 'bob', 'bob@example.com')");
$pdo->query("INSERT INTO user VALUES(3, 'carol', 'carol@example.com')");

$config = new ExporterConfig();
$exporter = new Exporter($config);

$stmt = $pdo->prepare("SELECT * FROM user");
$stmt->execute();

$exporter->export('php://output', new PdoCollection($stmt));

