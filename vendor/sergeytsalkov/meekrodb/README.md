MeekroDB -- The Simple PHP MySQL Library
========
Learn more: http://www.meekro.com

MeekroDB is: 

* A PHP MySQL library that lets you **get more done with fewer lines of code**, and **makes SQL injection 100% impossible**.
* Google's #1 search result for "php mysql library" since 2013, with **thousands of deployments worldwide**.
* A library with a **perfect security track record**. No bugs relating to security or SQL injection have ever been discovered.
* Backwards and forwards-compatible, supporting all PHP versions **from PHP 5.3** all the way through the latest release of **PHP 8**.

Installation
========
When you're ready to get started, see the [Quick Start Guide](http://www.meekro.com/quickstart.php) on our website.

### Manual Setup
Include the `db.class.php` file into your project and set it up like this:

```php
require_once 'db.class.php';
DB::$user = 'my_database_user';
DB::$password = 'my_database_password';
DB::$dbName = 'my_database_name';
```

### Composer
Add this to your `composer.json`

```json
{
  "require": {
    "sergeytsalkov/meekrodb": "*"
  }
}
```

Code Examples
========
### Grab some rows from the database and print out a field from each row.

```php
$accounts = DB::query("SELECT * FROM accounts WHERE type = %s AND age > %i", $type, 15);
foreach ($accounts as $account) {
  echo $account['username'] . "\n";
}
```



### Insert a new row.

```php
DB::insert('mytable', array(
  'name' => $name,
  'rank' => $rank,
  'location' => $location,
  'age' => $age,
  'intelligence' => $intelligence
));
```
    
### Grab one row or field

```php
$account = DB::queryFirstRow("SELECT * FROM accounts WHERE username=%s", 'Joe');
$number_accounts = DB::queryFirstField("SELECT COUNT(*) FROM accounts");
```

### Use a list in a query
```php
DB::query("SELECT * FROM tbl WHERE name IN %ls AND age NOT IN %li", array('John', 'Bob'), array(12, 15));
```

### Log all queries and errors
```php
// log all queries and errors to file, or ..
DB::$logfile = '/home/username/logfile.txt';

// log all queries and errors to screen
DB::$logfile = fopen('php://output', 'w');
```

### Nested Transactions
```php
DB::$nested_transactions = true;
DB::startTransaction(); // outer transaction
// .. some queries..
$depth = DB::startTransaction(); // inner transaction
echo $depth . 'transactions are currently active'; // 2
 
// .. some queries..
DB::commit(); // commit inner transaction
// .. some queries..
DB::commit(); // commit outer transaction
```
    
### Lots More - See: http://meekro.com/docs

    
How is MeekroDB better than PDO?
========
### Optional Static Class Mode
Most web apps will only ever talk to one database. This means that 
passing $db objects to every function of your code just adds unnecessary clutter. 
The simplest approach is to use static methods such as DB::query(), and that's how 
MeekroDB works. Still, if you need database objects, MeekroDB can do that too.

### Do more with fewer lines of code
The code below escapes your parameters for safety, runs the query, and grabs 
the first row of results. Try doing that in one line with PDO.

```php
$account = DB::queryFirstRow("SELECT * FROM accounts WHERE username=%s", 'Joe');
```

Or how about just one field?

```php
$created_at = DB::queryFirstField("SELECT created_at FROM accounts WHERE username=%s", 'Joe');
```

### Work with list parameters easily
Using MySQL's IN keyword should not be hard. MeekroDB smooths out the syntax for you, 
PDO does not.

```php
$accounts = DB::query("SELECT * FROM accounts WHERE username IN %ls", array('Joe', 'Frank'));
```


### Simple inserts
Using MySQL's INSERT should not be more complicated than passing in an 
associative array. MeekroDB also simplifies many related commands, including 
the useful and bizarre INSERT .. ON DUPLICATE UPDATE command. PDO does none of this.

```php
DB::insert('accounts', array('username' => 'John', 'password' => 'whatever'));
```

### Nested transactions
MySQL's SAVEPOINT commands lets you create nested transactions, but only 
if you keep track of SAVEPOINT ids yourself. MeekroDB does this for you, 
so you can have nested transactions with no complexity or learning curve.

```php
DB::$nested_transactions = true;
DB::startTransaction(); // outer transaction
// .. some queries..
$depth = DB::startTransaction(); // inner transaction
echo $depth . 'transactions are currently active'; // 2
 
// .. some queries..
DB::commit(); // commit inner transaction
// .. some queries..
DB::commit(); // commit outer transaction
```

### Flexible debug logging and error handling
You can log all queries (and any errors they produce) to a file for debugging purposes. You can also add hooks that let you run your own functions at any point in the query handling process.


My Other Projects
========
A little shameless self-promotion!

  * [Ark Server Hosting](https://arkservers.io) -- Ark: Survival Evolved server hosting by ArkServers.io!
  * [Best Minecraft Server Hosting](https://bestminecraft.org) -- Ranking and recommendations for minecraft server hosting!
  * [brooce](https://github.com/SergeyTsalkov/brooce) - Language-agnostic job queue written in Go! Write your jobs in any language, schedule them from any language, run them anywhere!
