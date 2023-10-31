<?php
class ObjectTest extends SimpleTest {
  public $mdb;
  
  function __construct() {
    $this->mdb = new MeekroDB();
    
    foreach ($this->mdb->tableList() as $table) {
      $this->mdb->query("DROP TABLE %b", $table);
    }
  }
  
  
  function test_1_create_table() {
    $this->mdb->query("CREATE TABLE `accounts` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
    `username` VARCHAR( 255 ) NOT NULL ,
    `password` VARCHAR( 255 ) NULL ,
    `age` INT NOT NULL DEFAULT '10',
    `height` DOUBLE NOT NULL DEFAULT '10.0',
    `favorite_word` VARCHAR( 255 ) NULL DEFAULT 'hi'
    ) ENGINE = InnoDB");
  }
  
  function test_1_5_empty_table() {
    $counter = $this->mdb->queryFirstField("SELECT COUNT(*) FROM accounts");
    $this->assert($counter === strval(0));
    
    $row = $this->mdb->queryFirstRow("SELECT * FROM accounts");
    $this->assert($row === null);
    
    $field = $this->mdb->queryFirstField("SELECT * FROM accounts");
    $this->assert($field === null);
    
    $field = $this->mdb->queryOneField('nothere', "SELECT * FROM accounts");
    $this->assert($field === null);
    
    $column = $this->mdb->queryFirstColumn("SELECT * FROM accounts");
    $this->assert(is_array($column) && count($column) === 0);
    
    $column = $this->mdb->queryOneColumn('nothere', "SELECT * FROM accounts"); //TODO: is this what we want?
    $this->assert(is_array($column) && count($column) === 0);
  }
  
  function test_2_insert_row() {
    $this->mdb->insert('accounts', array(
      'username' => 'Abe',
      'password' => 'hello'
    ));
    
    $this->assert($this->mdb->affectedRows() === 1);
    
    $counter = $this->mdb->queryFirstField("SELECT COUNT(*) FROM accounts");
    $this->assert($counter === strval(1));
  }
  
  function test_3_more_inserts() {
    $this->mdb->insert('`accounts`', array(
      'username' => 'Bart',
      'password' => 'hello',
      'age' => 15,
      'height' => 10.371
    ));
    $dbname = $this->mdb->dbName;
    $this->mdb->insert("`$dbname`.`accounts`", array(
      'username' => 'Charlie\'s Friend',
      'password' => 'goodbye',
      'age' => 30,
      'height' => 155.23,
      'favorite_word' => null,
    ));
    
    $this->assert($this->mdb->insertId() === 3);
    
    $counter = $this->mdb->queryFirstField("SELECT COUNT(*) FROM accounts");
    $this->assert($counter === strval(3));
    
    $password = $this->mdb->queryFirstField("SELECT password FROM accounts WHERE favorite_word IS NULL");
    $this->assert($password === 'goodbye');
    
    $this->mdb->param_char = '###';
    $bart = $this->mdb->queryFirstRow("SELECT * FROM accounts WHERE age IN ###li AND height IN ###ld AND username IN ###ls", 
      array(15, 25), array(10.371, 150.123), array('Bart', 'Barts'));
    $this->assert($bart['username'] === 'Bart');
    $this->mdb->param_char = '%';
    
    $charlie_password = $this->mdb->queryFirstField("SELECT password FROM accounts WHERE username IN %ls AND username = %s", 
      array('Charlie', 'Charlie\'s Friend'), 'Charlie\'s Friend');
    $this->assert($charlie_password === 'goodbye');
    
    $charlie_password = $this->mdb->queryOneField('password', "SELECT * FROM accounts WHERE username IN %ls AND username = %s", 
      array('Charlie', 'Charlie\'s Friend'), 'Charlie\'s Friend');
    $this->assert($charlie_password === 'goodbye');
    
    $passwords = $this->mdb->queryFirstColumn("SELECT password FROM accounts WHERE username=%s", 'Bart');
    $this->assert(count($passwords) === 1);
    $this->assert($passwords[0] === 'hello');
    
    $username = $password = $age = null;
    list($age, $username, $password) = $this->mdb->queryOneList("SELECT age,username,password FROM accounts WHERE username=%s", 'Bart');
    $this->assert($username === 'Bart');
    $this->assert($password === 'hello');
    $this->assert($age == 15);
    
    $mysqli_result = $this->mdb->queryRaw("SELECT * FROM accounts WHERE favorite_word IS NULL");
    $this->assert($mysqli_result instanceof MySQLi_Result);
    $row = $mysqli_result->fetch_assoc();
    $this->assert($row['password'] === 'goodbye');
    $this->assert($mysqli_result->fetch_assoc() === null);
  }
  
  function test_4_query() {
    $results = $this->mdb->query("SELECT * FROM accounts WHERE username=%s", 'Charlie\'s Friend');
    $this->assert(count($results) === 1);
    $this->assert($results[0]['age'] == 30 && $results[0]['password'] == 'goodbye');
    
    $results = $this->mdb->query("SELECT * FROM accounts WHERE username!=%s", "Charlie's Friend");
    $this->assert(count($results) === 2);
    
    $columnList = $this->mdb->columnList('accounts');
    $columnKeys = array_keys($columnList);
    $this->assert(count($columnList) === 6);
    $this->assert($columnList['id']['type'] == 'int(11)');
    $this->assert($columnList['height']['type'] == 'double');
    $this->assert($columnKeys[4] == 'height');
    
    $tablelist = $this->mdb->tableList();
    $this->assert(count($tablelist) === 1);
    $this->assert($tablelist[0] === 'accounts');
    
    $tablelist = null;
    $tablelist = $this->mdb->tableList($this->mdb->dbName);
    $this->assert(count($tablelist) === 1);
    $this->assert($tablelist[0] === 'accounts');
  }
  
  function test_4_1_query() {
    $this->mdb->insert('accounts', array(
      'username' => 'newguy',
      'password' => $this->mdb->sqleval("REPEAT('blah', %i)", '3'),
      'age' => $this->mdb->sqleval('171+1'),
      'height' => 111.15
    ));
    
    $row = $this->mdb->queryOneRow("SELECT * FROM accounts WHERE password=%s", 'blahblahblah');
    $this->assert($row['username'] === 'newguy');
    $this->assert($row['age'] === '172');
    
    $this->mdb->update('accounts', array(
      'password' => $this->mdb->sqleval("REPEAT('blah', %i)", 4),
      'favorite_word' => null,
      ), 'username=%s', 'newguy');
    
    $row = null;
    $row = $this->mdb->queryOneRow("SELECT * FROM accounts WHERE username=%s", 'newguy');
    $this->assert($row['password'] === 'blahblahblahblah');
    $this->assert($row['favorite_word'] === null);
    
    $this->mdb->query("DELETE FROM accounts WHERE password=%s", 'blahblahblahblah');
    $this->assert($this->mdb->affectedRows() === 1);
  }
  
  function test_4_2_delete() {
    $this->mdb->insert('accounts', array(
      'username' => 'gonesoon',
      'password' => 'something',
      'age' => 61,
      'height' => 199.194
    ));
    
    $ct = $this->mdb->queryFirstField("SELECT COUNT(*) FROM accounts WHERE username=%s AND height=%d", 'gonesoon', 199.194);
    $this->assert(intval($ct) === 1);
    
    $ct = $this->mdb->queryFirstField("SELECT COUNT(*) FROM accounts WHERE username=%s1 AND height=%d0 AND height=%d", 199.194, 'gonesoon');
    $this->assert(intval($ct) === 1);
    
    $this->mdb->delete('accounts', 'username=%s AND age=%i AND height=%d', 'gonesoon', '61', '199.194');
    $this->assert($this->mdb->affectedRows() === 1);
    
    $ct = $this->mdb->queryFirstField("SELECT COUNT(*) FROM accounts WHERE username=%s AND height=%d", 'gonesoon', '199.194');
    $this->assert(intval($ct) === 0);
  }
  
  function test_4_3_insertmany() {
    $ins[] = array(
      'username' => '1ofmany',
      'password' => 'something',
      'age' => 23,
      'height' => 190.194
    );
    $ins[] = array(
      'password' => 'somethingelse',
      'username' => '2ofmany',
      'age' => 25,
      'height' => 190.194
    );
    
    $this->mdb->insert('accounts', $ins);
    $this->assert($this->mdb->affectedRows() === 2);
    
    $rows = $this->mdb->query("SELECT * FROM accounts WHERE height=%d ORDER BY age ASC", 190.194);
    $this->assert(count($rows) === 2);
    $this->assert($rows[0]['username'] === '1ofmany');
    $this->assert($rows[0]['age'] === '23');
    $this->assert($rows[1]['age'] === '25');
    $this->assert($rows[1]['password'] === 'somethingelse');
    $this->assert($rows[1]['username'] === '2ofmany');
    
  }
  
  
  
  function test_5_insert_blobs() {
    $this->mdb->query("CREATE TABLE `storedata` (
      `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
      `picture` BLOB
    ) ENGINE = InnoDB");
    
    
    $smile = file_get_contents(__DIR__ . '/smile1.jpg');
    $this->mdb->insert('storedata', array(
      'picture' => $smile,
    ));
    $this->mdb->query("INSERT INTO storedata (picture) VALUES (%s)", $smile);
    
    $getsmile = $this->mdb->queryFirstField("SELECT picture FROM storedata WHERE id=1");
    $getsmile2 = $this->mdb->queryFirstField("SELECT picture FROM storedata WHERE id=2");
    $this->assert($smile === $getsmile);
    $this->assert($smile === $getsmile2);
  }
  
  function test_6_insert_ignore() {
    $this->mdb->insertIgnore('accounts', array(
      'id' => 1, //duplicate primary key
      'username' => 'gonesoon',
      'password' => 'something',
      'age' => 61,
      'height' => 199.194
    ));
  }
  
  function test_7_insert_update() {
    $this->mdb->insertUpdate('accounts', array(
      'id' => 2, //duplicate primary key
      'username' => 'gonesoon',
      'password' => 'something',
      'age' => 61,
      'height' => 199.194
    ), 'age = age + %i', 1);
    
    $this->assert($this->mdb->affectedRows() === 2); // a quirk of MySQL, even though only 1 row was updated
    
    $result = $this->mdb->query("SELECT * FROM accounts WHERE age = %i", 16);
    $this->assert(count($result) === 1);
    $this->assert($result[0]['height'] === '10.371');
    
    $this->mdb->insertUpdate('accounts', array(
      'id' => 2, //duplicate primary key
      'username' => 'blahblahdude',
      'age' => 233,
      'height' => 199.194
    ));
    
    $result = $this->mdb->query("SELECT * FROM accounts WHERE age = %i", 233);
    $this->assert(count($result) === 1);
    $this->assert($result[0]['height'] === '199.194');
    $this->assert($result[0]['username'] === 'blahblahdude');
    
    $this->mdb->insertUpdate('accounts', array(
      'id' => 2, //duplicate primary key
      'username' => 'gonesoon',
      'password' => 'something',
      'age' => 61,
      'height' => 199.194
    ), array(
      'age' => 74,
    ));
    
    $result = $this->mdb->query("SELECT * FROM accounts WHERE age = %i", 74);
    $this->assert(count($result) === 1);
    $this->assert($result[0]['height'] === '199.194');
    $this->assert($result[0]['username'] === 'blahblahdude');
    
    $multiples[] = array(
      'id' => 3, //duplicate primary key
      'username' => 'gonesoon',
      'password' => 'something',
      'age' => 61,
      'height' => 199.194
    );
    $multiples[] = array(
      'id' => 1, //duplicate primary key
      'username' => 'gonesoon',
      'password' => 'something',
      'age' => 61,
      'height' => 199.194
    );
    
    $this->mdb->insertUpdate('accounts', $multiples, array('age' => 914));
    $this->assert($this->mdb->affectedRows() === 4);
    
    $result = $this->mdb->query("SELECT * FROM accounts WHERE age=914 ORDER BY id ASC");
    $this->assert(count($result) === 2);
    $this->assert($result[0]['username'] === 'Abe');
    $this->assert($result[1]['username'] === 'Charlie\'s Friend');
    
    $this->mdb->query("UPDATE accounts SET age=15, username='Bart' WHERE age=%i", 74);
    $this->assert($this->mdb->affectedRows() === 1);
  }

}


?>
