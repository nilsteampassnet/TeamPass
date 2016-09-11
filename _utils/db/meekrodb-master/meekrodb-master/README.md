MeekroDB -- The Simple PHP MySQL Library
========
Learn more: http://www.meekro.com

MeekroDB is: 

* A PHP MySQL library that lets you **get more done with fewer lines of code**, and **makes SQL injection 100% impossible**.
* Google's #1 search result for "php mysql library" for over 2 years, with **thousands of deployments worldwide**.
* A library with a **perfect security track record**. No bugs relating to security or SQL injection have ever been discovered.

Installation
========
When you're ready to get started, see the [Quick Start Guide](http://www.meekro.com/quickstart.php) on our website.

### Manual Setup
Include the `db.class.php` file into your project and set it up like this:

    require_once 'db.class.php';
    DB::$user = 'my_database_user';
    DB::$password = 'my_database_password';
    DB::$dbName = 'my_database_name';

### Composer
Add this to your `composer.json`

    {
      "require": {
        "sergeytsalkov/meekrodb": "*"
      }
    }

Code Examples
========
### Grab some rows from the database and print out a field from each row.

    $accounts = DB::query("SELECT * FROM accounts WHERE type = %s AND age > %i", $type, 15);
    foreach ($accounts as $account) {
      echo $account['username'] . "\n";
    }

### Insert a new row.

    DB::insert('mytable', array(
      'name' => $name,
      'rank' => $rank,
      'location' => $location,
      'age' => $age,
      'intelligence' => $intelligence
    ));
    
### Grab one row or field

	$account = DB::queryFirstRow("SELECT * FROM accounts WHERE username=%s", 'Joe');
	$number_accounts = DB::queryFirstField("SELECT COUNT(*) FROM accounts");

### Use a list in a query
	DB::query("SELECT * FROM tbl WHERE name IN %ls AND age NOT IN %li", array('John', 'Bob'), array(12, 15));

### Nested Transactions

    DB::$nested_transactions = true;
    DB::startTransaction(); // outer transaction
    // .. some queries..
    $depth = DB::startTransaction(); // inner transaction
    echo $depth . 'transactions are currently active'; // 2
     
    // .. some queries..
    DB::commit(); // commit inner transaction
    // .. some queries..
    DB::commit(); // commit outer transaction
    
### Lots More - See: http://www.meekro.com/docs.php

    
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

	$account = DB::queryFirstRow("SELECT * FROM accounts WHERE username=%s", 'Joe');

### Work with list parameters easily
Using MySQL's IN keyword should not be hard. MeekroDB smooths out the syntax for you, 
PDO does not.

	$accounts = DB::query("SELECT * FROM accounts WHERE username IN %ls", array('Joe', 'Frank'));


### Simple inserts
Using MySQL's INSERT should not be more complicated than passing in an 
associative array. MeekroDB also simplifies many related commands, including 
the useful and bizarre INSERT .. ON DUPLICATE UPDATE command. PDO does none of this.

	DB::insert('accounts', array('username' => 'John', 'password' => 'whatever'));

### Focus on the goal, not the task
Want to do INSERT yourself rather than relying on DB::insert()? 
It's dead simple. I don't even want to think about how many lines 
you'd need to pull this off in PDO.

    // Insert 2 rows at once
      DB::query("INSERT INTO %b %lb VALUES %?", 'accounts',
      array('username', 'password', 'last_login_timestamp'),
      array(
        array('Joe', 'joes_password', new DateTime('yesterday')),
        array('Frank', 'franks_password', new DateTime('last Monday'))
      )
    );

### Nested transactions
MySQL's SAVEPOINT commands lets you create nested transactions, but only 
if you keep track of SAVEPOINT ids yourself. MeekroDB does this for you, 
so you can have nested transactions with no complexity or learning curve.

    DB::$nested_transactions = true;
    DB::startTransaction(); // outer transaction
    // .. some queries..
    $depth = DB::startTransaction(); // inner transaction
    echo $depth . 'transactions are currently active'; // 2
     
    // .. some queries..
    DB::commit(); // commit inner transaction
    // .. some queries..
    DB::commit(); // commit outer transaction

### Flexible error and success handlers
Set your own custom function run on errors, or on every query that succeeds. 
You can easily have separate error handling behavior for the dev and live 
versions of your application. Want to count up all your queries and their 
runtime? Just add a new success handler.

### More about MeekroDB's design philosophy: http://www.meekro.com/beliefs.php
