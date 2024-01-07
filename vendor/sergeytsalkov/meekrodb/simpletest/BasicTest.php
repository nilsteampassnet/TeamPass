<?php
class BasicTest extends SimpleTest {
  function __construct() {
    foreach (DB::tableList() as $table) {
      DB::query("DROP TABLE %b", $table);
    }
  }
  
  
  function test_1_create_table() {
    DB::query("CREATE TABLE `accounts` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
    `profile_id` INT NOT NULL,
    `username` VARCHAR( 255 ) NOT NULL ,
    `password` VARCHAR( 255 ) NULL ,
    `user.age` INT NOT NULL DEFAULT '10',
    `height` DOUBLE NOT NULL DEFAULT '10.0',
    `favorite_word` VARCHAR( 255 ) NULL DEFAULT 'hi',
    `birthday` TIMESTAMP NOT NULL
    ) ENGINE = InnoDB");

    DB::query("CREATE TABLE `profile` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
    `signature` VARCHAR( 255 ) NULL DEFAULT 'donewriting'
    ) ENGINE = InnoDB");

    DB::query("CREATE TABLE `fake%s_table` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
    `name` VARCHAR( 255 ) NULL DEFAULT 'blah'
    ) ENGINE = InnoDB");
  }
  
  function test_1_5_empty_table() {
    $counter = DB::queryFirstField("SELECT COUNT(*) FROM accounts");
    $this->assert($counter === strval(0));
    $this->assert(DB::lastQuery() === 'SELECT COUNT(*) FROM accounts');
    
    $row = DB::queryFirstRow("SELECT * FROM accounts");
    $this->assert($row === null);
    
    $field = DB::queryFirstField("SELECT * FROM accounts");
    $this->assert($field === null);
    
    $field = DB::queryOneField('nothere', "SELECT * FROM accounts");
    $this->assert($field === null);
    
    $column = DB::queryFirstColumn("SELECT * FROM accounts");
    $this->assert(is_array($column) && count($column) === 0);
    
    $column = DB::queryOneColumn('nothere', "SELECT * FROM accounts"); //TODO: is this what we want?
    $this->assert(is_array($column) && count($column) === 0);
  }
  
  function test_2_insert_row() {
    $affected_rows = DB::insert('accounts', array(
      'username' => 'Abe',
      'password' => 'hello'
    ));
    
    $this->assert($affected_rows === 1);
    $this->assert(DB::affectedRows() === 1);
    
    $counter = DB::queryFirstField("SELECT COUNT(*) FROM accounts");
    $this->assert($counter === strval(1));
  }
  
  function test_3_more_inserts() {
    DB::insert('`accounts`', array(
      'username' => 'Bart',
      'password' => 'hello',
      'user.age' => 15,
      'height' => 10.371
    ));
    $dbname = DB::$dbName;
    DB::insert("`$dbname`.`accounts`", array(
      'username' => 'Charlie\'s Friend',
      'password' => 'goodbye',
      'user.age' => 30,
      'height' => 155.23,
      'favorite_word' => null,
    ));
    
    $this->assert(DB::insertId() === 3);
    $counter = DB::queryFirstField("SELECT COUNT(*) FROM accounts");
    $this->assert($counter === strval(3));
    
    DB::insert('`accounts`', array(
      'username' => 'Deer',
      'password' => '',
      'user.age' => 15,
      'height' => 10.371
    ));

    $username = DB::queryFirstField("SELECT username FROM accounts WHERE password=%s0", null);
    $this->assert($username === 'Deer');

    $password = DB::queryFirstField("SELECT password FROM accounts WHERE favorite_word IS NULL");
    $this->assert($password === 'goodbye');
    
    DB::insertUpdate('accounts', array(
      'id' => 3,
      'favorite_word' => null,
    ));
    
    DB::$param_char = '###';
    $bart = DB::queryFirstRow("SELECT * FROM accounts WHERE `user.age` IN ###li AND height IN ###ld AND username IN ###ls", 
      array(15, 25), array(10.371, 150.123), array('Bart', 'Barts'));
    $this->assert($bart['username'] === 'Bart');
    DB::insert('accounts', array('username' => 'f_u'));
    DB::query("DELETE FROM accounts WHERE username=###s", 'f_u');
    DB::$param_char = '%';
    
    $charlie_password = DB::queryFirstField("SELECT password FROM accounts WHERE username IN %ls AND username = %s", 
      array('Charlie', 'Charlie\'s Friend'), 'Charlie\'s Friend');
    $this->assert($charlie_password === 'goodbye');
    
    $charlie_password = DB::queryOneField('password', "SELECT * FROM accounts WHERE username IN %ls AND username = %s", 
      array('Charlie', 'Charlie\'s Friend'), 'Charlie\'s Friend');
    $this->assert($charlie_password === 'goodbye');
    
    $passwords = DB::queryFirstColumn("SELECT password FROM accounts WHERE username=%s", 'Bart');
    $this->assert(count($passwords) === 1);
    $this->assert($passwords[0] === 'hello');
    
    $username = $password = $age = null;
    list($age, $username, $password) = DB::queryOneList("SELECT `user.age`,username,password FROM accounts WHERE username=%s", 'Bart');
    $this->assert($username === 'Bart');
    $this->assert($password === 'hello');
    $this->assert($age == 15);
    
    $mysqli_result = DB::queryRaw("SELECT * FROM accounts WHERE favorite_word IS NULL");
    $this->assert($mysqli_result instanceof MySQLi_Result);
    $row = $mysqli_result->fetch_assoc();
    $this->assert($row['password'] === 'goodbye');
    $this->assert($mysqli_result->fetch_assoc() === null);
  }
  
  function test_4_query() {
    $affected_rows = DB::update('accounts', array(
      'birthday' => new DateTime('10 September 2000 13:13:13')
    ), 'username=%s', 'Charlie\'s Friend');
    
    $results = DB::query("SELECT * FROM accounts WHERE username=%s AND birthday IN %lt", 'Charlie\'s Friend', array('September 10 2000 13:13:13'));
    $this->assert($affected_rows === 1);
    $this->assert(count($results) === 1);
    $this->assert($results[0]['user.age'] === '30' && $results[0]['password'] === 'goodbye');
    $this->assert($results[0]['birthday'] == '2000-09-10 13:13:13');
    
    $results = DB::query("SELECT * FROM accounts WHERE username!=%s", "Charlie's Friend");
    $this->assert(count($results) === 3);
    
    $columnList = DB::columnList('accounts');
    $columnKeys = array_keys($columnList);
    $this->assert(count($columnList) === 8);
    $this->assert($columnList['id']['type'] == 'int(11)');
    $this->assert($columnList['height']['type'] == 'double');
    $this->assert($columnKeys[5] == 'height');
    
    $tablelist = DB::tableList();
    $this->assert(count($tablelist) === 3);
    $this->assert($tablelist[0] === 'accounts');
    
    $tablelist = null;
    $tablelist = DB::tableList(DB::$dbName);
    $this->assert(count($tablelist) === 3);
    $this->assert($tablelist[0] === 'accounts');

    $date = DB::queryFirstField("SELECT DATE_FORMAT(birthday, '%%m/%%d/%%Y') FROM accounts WHERE username=%s", "Charlie's Friend");
    $this->assert($date === '09/10/2000');

    $date = DB::queryFirstField("SELECT DATE_FORMAT('2009-10-04 22:23:00', '%m/%d/%Y')");;
    $this->assert($date === '10/04/2009');
  }
  
  function test_4_1_query() {
    DB::insert('accounts', array(
      'username' => 'newguy',
      'password' => DB::sqleval("REPEAT('blah', %i)", '3'),
      'user.age' => DB::sqleval('171+1'),
      'height' => 111.15
    ));
    
    $row = DB::queryOneRow("SELECT * FROM accounts WHERE password=%s", 'blahblahblah');
    $this->assert($row['username'] === 'newguy');
    $this->assert($row['user.age'] === '172');
    
    $affected_rows = DB::update('accounts', array(
      'password' => DB::sqleval("REPEAT('blah', %i)", 4),
      'favorite_word' => null,
      ), 'username=%s_name', array('name' => 'newguy'));
    
    $row = null;
    $row = DB::queryOneRow("SELECT * FROM accounts WHERE username=%s", 'newguy');
    $this->assert($affected_rows === 1);
    $this->assert($row['password'] === 'blahblahblahblah');
    $this->assert($row['favorite_word'] === null);
    
    $row = DB::query("SELECT * FROM accounts WHERE password=%s_mypass AND (password=%s_mypass) AND username=%s_myuser", 
      array('myuser' => 'newguy', 'mypass' => 'blahblahblahblah')
    );
    $this->assert(count($row) === 1);
    $this->assert($row[0]['username'] === 'newguy');
    $this->assert($row[0]['user.age'] === '172');

    $row = DB::query("SELECT * FROM accounts WHERE password IN %li AND password IN %li0 AND username=%s", array('blahblahblahblah'), 'newguy');
    $this->assert(count($row) === 1);
    $this->assert($row[0]['username'] === 'newguy');
    $this->assert($row[0]['user.age'] === '172');
    
    $affected_rows = DB::query("DELETE FROM accounts WHERE password=%s", 'blahblahblahblah');
    $this->assert($affected_rows === 1);
    $this->assert(DB::affectedRows() === 1);
  }
  
  function test_4_2_delete() {
    DB::insert('accounts', array(
      'username' => 'gonesoon',
      'password' => 'something',
      'user.age' => 61,
      'height' => 199.194
    ));
    
    $ct = DB::queryFirstField("SELECT COUNT(*) FROM accounts WHERE %ha", array('username' => 'gonesoon', 'height' => 199.194));
    $this->assert(intval($ct) === 1);
    
    $ct = DB::queryFirstField("SELECT COUNT(*) FROM accounts WHERE username=%s1 AND height=%d0 AND height=%d", 199.194, 'gonesoon');
    $this->assert(intval($ct) === 1);
    
    $affected_rows = DB::delete('accounts', 'username=%s AND `user.age`=%i AND height=%d', 'gonesoon', '61', '199.194');
    $this->assert($affected_rows === 1);
    $this->assert(DB::affectedRows() === 1);
    
    $ct = DB::queryFirstField("SELECT COUNT(*) FROM accounts WHERE username=%s AND height=%d", 'gonesoon', '199.194');
    $this->assert(intval($ct) === 0);
  }
  
  function test_4_3_insertmany() {
    $ins[] = array(
      'username' => '1ofmany',
      'password' => 'something',
      'user.age' => 23,
      'height' => 190.194
    );
    $ins[] = array(
      'password' => 'somethingelse',
      'username' => '2ofmany',
      'user.age' => 25,
      'height' => 190.194
    );
    $ins[] = array(
      'password' => NULL,
      'username' => '3ofmany',
      'user.age' => 15,
      'height' => 111.951
    ); 
    
    DB::insert('accounts', $ins);
    $this->assert(DB::affectedRows() === 3);
    
    $rows = DB::query("SELECT * FROM accounts WHERE height=%d ORDER BY `user.age` ASC", 190.194);
    $this->assert(count($rows) === 2);
    $this->assert($rows[0]['username'] === '1ofmany');
    $this->assert($rows[0]['user.age'] === '23');
    $this->assert($rows[1]['user.age'] === '25');
    $this->assert($rows[1]['password'] === 'somethingelse');
    $this->assert($rows[1]['username'] === '2ofmany');
    
    $nullrow = DB::queryOneRow("SELECT * FROM accounts WHERE username LIKE %ss", '3ofman');
    $this->assert($nullrow['password'] === NULL);
    $this->assert($nullrow['user.age'] === '15');
  }
  
  
  
  function test_5_insert_blobs() {
    DB::query("CREATE TABLE `store data` (
      `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
      `picture` BLOB
    ) ENGINE = InnoDB");

    $columns = DB::columnList('store data');
    $this->assert(count($columns) === 2);
    $this->assert($columns['picture']['type'] === 'blob');
    $this->assert($columns['picture']['null'] === 'YES');
    $this->assert($columns['picture']['key'] === '');
    $this->assert($columns['picture']['default'] === NULL);
    $this->assert($columns['picture']['extra'] === '');
    
    $smile = file_get_contents(__DIR__ . '/smile1.jpg');
    DB::insert('store data', array(
      'picture' => $smile,
    ));
    DB::queryOneRow("INSERT INTO %b (picture) VALUES (%s)", 'store data', $smile);
    
    $getsmile = DB::queryFirstField("SELECT picture FROM %b WHERE id=1", 'store data');
    $getsmile2 = DB::queryFirstField("SELECT picture FROM %b WHERE id=2", 'store data');
    $this->assert($smile === $getsmile);
    $this->assert($smile === $getsmile2);
  }
  
  function test_6_insert_ignore() {
    $affected_rows = DB::insertIgnore('accounts', array(
      'id' => 1, //duplicate primary key
      'username' => 'gonesoon',
      'password' => 'something',
      'user.age' => 61,
      'height' => 199.194
    ));
    $this->assert($affected_rows === 0);
  }
  
  function test_7_insert_update() {
    $affected_rows = DB::insertUpdate('accounts', array(
      'id' => 2, //duplicate primary key
      'username' => 'gonesoon',
      'password' => 'something',
      'user.age' => 61,
      'height' => 199.194
    ), '`user.age` = `user.age` + %i', 1);
    
    $this->assert($affected_rows === 2);
    $this->assert(DB::affectedRows() === 2); // a quirk of MySQL, even though only 1 row was updated
    
    $result = DB::query("SELECT * FROM accounts WHERE `user.age` = %i", 16);
    $this->assert(count($result) === 1);
    $this->assert($result[0]['height'] === '10.371');
    
    DB::insertUpdate('accounts', array(
      'id' => 2, //duplicate primary key
      'username' => 'blahblahdude',
      'user.age' => 233,
      'height' => 199.194
    ));
    
    $result = DB::query("SELECT * FROM accounts WHERE `user.age` = %i", 233);
    $this->assert(count($result) === 1);
    $this->assert($result[0]['height'] === '199.194');
    $this->assert($result[0]['username'] === 'blahblahdude');
    
    DB::insertUpdate('accounts', array(
      'id' => 2, //duplicate primary key
      'username' => 'gonesoon',
      'password' => 'something',
      'user.age' => 61,
      'height' => 199.194
    ), array(
      'user.age' => 74,
    ));
    
    $result = DB::query("SELECT * FROM accounts WHERE `user.age` = %i", 74);
    $this->assert(count($result) === 1);
    $this->assert($result[0]['height'] === '199.194');
    $this->assert($result[0]['username'] === 'blahblahdude');
    
    $multiples[] = array(
      'id' => 3, //duplicate primary key
      'username' => 'gonesoon',
      'password' => 'something',
      'user.age' => 61,
      'height' => 199.194
    );
    $multiples[] = array(
      'id' => 1, //duplicate primary key
      'username' => 'gonesoon',
      'password' => 'something',
      'user.age' => 61,
      'height' => 199.194
    );
    
    DB::insertUpdate('accounts', $multiples, array('user.age' => 914));
    $this->assert(DB::affectedRows() === 4);
    
    $result = DB::query("SELECT * FROM accounts WHERE `user.age`=914 ORDER BY id ASC");
    $this->assert(count($result) === 2);
    $this->assert($result[0]['username'] === 'Abe');
    $this->assert($result[1]['username'] === 'Charlie\'s Friend');
    
    $affected_rows = DB::query("UPDATE accounts SET `user.age`=15, username='Bart' WHERE `user.age`=%i", 74);
    $this->assert($affected_rows === 1);
    $this->assert(DB::affectedRows() === 1);
  }
  
  function test_8_lb() {
    $data = array(
      'username' => 'vookoo',
      'password' => 'dookoo',
    );
    
    $affected_rows = DB::query("INSERT into accounts %lb VALUES %ls", array_keys($data), array_values($data));
    $result = DB::query("SELECT * FROM accounts WHERE username=%s", 'vookoo');
    $this->assert($affected_rows === 1);
    $this->assert(count($result) === 1);
    $this->assert($result[0]['password'] === 'dookoo');
  }

  function test_9_fullcolumns() {
    $affected_rows = DB::insert('profile', array(
      'id' => 1,
      'signature' => 'u_suck'
    ));
    DB::query("UPDATE accounts SET profile_id=1 WHERE id=2");

    $r = DB::queryFullColumns("SELECT accounts.*, profile.*, 1+1 FROM accounts
      INNER JOIN profile ON accounts.profile_id=profile.id");

    $this->assert($affected_rows === 1);
    $this->assert(count($r) === 1);
    $this->assert($r[0]['accounts.id'] === '2');
    $this->assert($r[0]['profile.id'] === '1');
    $this->assert($r[0]['profile.signature'] === 'u_suck');
    $this->assert($r[0]['1+1'] === '2');
  }

  function test_901_updatewithspecialchar() {
    $data = 'www.mysite.com/product?s=t-%s-%%3d%%3d%i&RCAID=24322';
    DB::update('profile', array('signature' => $data), 'id=%i', 1);
    $signature = DB::queryFirstField("SELECT signature FROM profile WHERE id=%i", 1);
    $this->assert($signature === $data);

    DB::update('profile', array('signature'=> "%li "), array('id' => 1));
    $signature = DB::queryFirstField("SELECT signature FROM profile WHERE id=%i", 1);
    $this->assert($signature === "%li ");
  }

  function test_902_faketable() {
    DB::insert('fake%s_table', array('name' => 'karen'));
    $count = DB::queryFirstField("SELECT COUNT(*) FROM %b", 'fake%s_table');
    $this->assert($count === '1');
    DB::update('fake%s_table', array('name' => 'haren%s'), 'name=%s_name', array('name' => 'karen'));
    $affected_rows = DB::delete('fake%s_table', array('name' => 'haren%s'));
    $count = DB::queryFirstField("SELECT COUNT(*) FROM %b", 'fake%s_table');
    $this->assert($affected_rows === 1);
    $this->assert($count === '0');
  }

  function test_10_parse() {
    $parsed_query = DB::parse("SELECT * FROM %b WHERE id=%i AND name=%s", 'accounts', 5, 'Joe');
    $correct_query = "SELECT * FROM `accounts` WHERE id=5 AND name='Joe'";
    $this->assert($parsed_query === $correct_query);

    $parsed_query = DB::parse("SELECT DATE_FORMAT(birthday, '%%Y-%%M-%%d %%h:%%i:%%s') AS mydate FROM accounts WHERE id=%i", 5);
    $correct_query = "SELECT DATE_FORMAT(birthday, '%Y-%M-%d %h:%i:%s') AS mydate FROM accounts WHERE id=5";
    $this->assert($parsed_query === $correct_query);
  }

  function test_11_timeout() {
    $default = DB::$reconnect_after;
    DB::$reconnect_after = 1;
    DB::query("SET SESSION wait_timeout=1");
    sleep(2);
    DB::query("SELECT * FROM accounts");
    DB::$reconnect_after = $default;
  }



}


?>
