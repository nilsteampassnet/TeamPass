# Goodby, CSV

[![Build Status](https://secure.travis-ci.org/goodby/csv.png?branch=master)](https://travis-ci.org/goodby/csv)

## What is "Goodby CSV"?

Goodby CSV is a highly memory efficient, flexible and extendable open-source CSV import/export library.

```php
use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\LexerConfig;

$lexer = new Lexer(new LexerConfig());
$interpreter = new Interpreter();
$interpreter->addObserver(function(array $row) {
    // do something here.
	// for example, insert $row to database.
});
$lexer->parse('data.csv', $interpreter);
```


### Features

#### 1. Memory Management Free

This library was designed for low memory usage. It will not accumulate all the rows in the memory. The importer reads a CSV file and executes a callback function line by line.

#### 2. Multibyte support

This library supports mulitbyte input/output: for example, SJIS-win, EUC-JP and UTF-8.

#### 3. Ready to Use for Enterprise Applications

Goodby CSV is fully unit-tested. The library is stable and ready to be used in large projects like enterprise applications.

## Requirements

* PHP 5.3.2 or later
* mbstring

## Installation

Install composer in your project:

```bash
curl -s http://getcomposer.org/installer | php
```

Create a `composer.json` file in your project root:

```json
{
    "require": {
        "goodby/csv": "*"
    }
}
```

Install via composer:

```bash
php composer.phar install
```

## Documentation

### Configuration

Import configuration:

```php
use Goodby\CSV\Import\Standard\LexerConfig;

$config = new LexerConfig();
$config
    ->setDelimiter("\t") // Customize delimiter. Default value is comma(,)
    ->setEnclosure("'")  // Customize enclosure. Default value is double quotation(")
    ->setEscape("\\")    // Customize escape character. Default value is backslash(\)
    ->setToCharset('UTF-8') // Customize target encoding. Default value is null, no converting.
    ->setFromCharset('SJIS-win') // Customize CSV file encoding. Default value is null.
;
```

Export configuration:

```php
use Goodby\CSV\Export\Standard\ExporterConfig;

$config = new ExporterConfig();
$config
    ->setDelimiter("\t") // Customize delimiter. Default value is comma(,)
    ->setEnclosure("'")  // Customize enclosure. Default value is double quotation(")
    ->setEscape("\\")    // Customize escape character. Default value is backslash(\)
    ->setToCharset('SJIS-win') // Customize file encoding. Default value is null, no converting.
    ->setFromCharset('UTF-8') // Customize source encoding. Default value is null.
    ->setFileMode(CsvFileObject::FILE_MODE_WRITE) // Customize file mode and choose either write or append. Default value is write ('w'). See fopen() php docs
;
```

### Unstrict Row Consistency Mode

By default, Goodby CSV throws `StrictViolationException` when it finds a row with a different column count to other columns. In the case you want to import such a CSV, you can call `Interpreter::unstrict()` to disable row consistency check at import.

rough.csv:

```csv
foo,bar,baz
foo,bar
foo
foo,bar,baz
```

```php
use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\LexerConfig;

$interpreter = new Interpreter();
$interpreter->unstrict(); // Ignore row column count consistency

$lexer = new Lexer(new LexerConfig());
$lexer->parse('rough.csv', $interpreter);
```

## Examples

### Import to Database via PDO

user.csv:

```csv
1,alice,alice@example.com
2,bob,bob@example.com
3,carol,carol@eample.com
```

```php
use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\LexerConfig;

$pdo = new PDO('mysql:host=localhost;dbname=test', 'root', 'root');
$pdo->query('CREATE TABLE IF NOT EXISTS user (id INT, `name` VARCHAR(255), email VARCHAR(255))');

$config = new LexerConfig();
$lexer = new Lexer($config);

$interpreter = new Interpreter();

$interpreter->addObserver(function(array $columns) use ($pdo) {
    $stmt = $pdo->prepare('INSERT INTO user (id, name, email) VALUES (?, ?, ?)');
    $stmt->execute($columns);
});

$lexer->parse('user.csv', $interpreter);
```

### Import from TSV (tab separated values) to array

temperature.tsv:

```csv
9	Tokyo
27	Singapore
-5	Seoul
7	Shanghai
```

```php
use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\LexerConfig;

$temperature = array();

$config = new LexerConfig();
$config->setDelimiter("\t");
$lexer = new Lexer($config);

$interpreter = new Interpreter();
$interpreter->addObserver(function(array $row) use (&$temperature) {
    $temperature[] = array(
        'temperature' => $row[0],
        'city'        => $row[1],
    );
});

$lexer->parse('temperature.tsv', $interpreter);

print_r($temperature);
```

### Export from array

```php
use Goodby\CSV\Export\Standard\Exporter;
use Goodby\CSV\Export\Standard\ExporterConfig;

$config = new ExporterConfig();
$exporter = new Exporter($config);

$exporter->export('php://output', array(
    array('1', 'alice', 'alice@example.com'),
    array('2', 'bob', 'bob@example.com'),
    array('3', 'carol', 'carol@example.com'),
));
```


### Export from database via PDO

```php
use Goodby\CSV\Export\Standard\Exporter;
use Goodby\CSV\Export\Standard\ExporterConfig;
use Goodby\CSV\Export\Standard\CsvFileObject;
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
```

### Export with CallbackCollection
```php
use Goodby\CSV\Export\Standard\Exporter;
use Goodby\CSV\Export\Standard\ExporterConfig;

use Goodby\CSV\Export\Standard\Collection\CallbackCollection;

$data = array();
$data[] = array('user', 'name1');
$data[] = array('user', 'name2');
$data[] = array('user', 'name3');

$collection = new CallbackCollection($data, function($row) {
    // apply custom format to the row
    $row[1] = $row[1] . '!';

    return $row;
});

$config = new ExporterConfig();
$exporter = new Exporter($config);

$exporter->export('php://stdout', $collection);
```

### Export in Symfony2 action

```php
namespace AcmeBundle\ExampleBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DefaultController extends Controller
{
	public function csvExportAction()
	{
		$conn = $this->get('database_connection');

		$stmt = $conn->prepare('SELECT * FROM somewhere');
		$stmt->execute();

		$response = new StreamedResponse();
		$response->setStatusCode(200);
		$response->headers->set('Content-Type', 'text/csv');
		$response->setCallback(function() use($stmt) {
			$config = new ExporterConfig();
			$exporter = new Exporter($config);

		    $exporter->export('php://output', new PdoCollection($stmt->getIterator()));
		});
		$response->send();

		return $response;
	}
}
```

## License

Csv is open-sourced software licensed under the MIT License - see the LICENSE file for details


## Contributing

We works under test driven development.

Checkout master source code from github:

```bash
hub clone goodby/csv
```

Install components via composer:

```
# If you don't have composer.phar
./scripts/bundle-devtools.sh .

# If you have composer.phar
composer.phar install --dev
```

Run phpunit:

```bash
./vendor/bin/phpunit
```

## Acknowledgement

Credits are found within composer.json file.
