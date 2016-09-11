<?php
class BasicTest extends SimpleTest {
    function __construct() {
        foreach ( DB::tableList () as $table ) {
            DB::query ( "DROP TABLE $table" );
        }
    }
    function test_1_create_table() {
        DB::query ( "CREATE TABLE `accounts` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
    `profile_id` INT NOT NULL,
    `username` VARCHAR( 255 ) NOT NULL ,
    `password` VARCHAR( 255 ) NULL ,
    `age` INT NOT NULL DEFAULT '10',
    `height` DOUBLE NOT NULL DEFAULT '10.0',
    `favorite_word` VARCHAR( 255 ) NULL DEFAULT 'hi',
    `birthday` TIMESTAMP NOT NULL
    ) ENGINE = InnoDB" );

        DB::query ( "CREATE TABLE `profile` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
    `signature` VARCHAR( 255 ) NULL DEFAULT 'donewriting'
    ) ENGINE = InnoDB" );

        $mysqli = DB::get ();
        DB::disconnect ();
        @$this->assert ( $mysqli->server_info === null );
    }
    function test_1_5_empty_table() {
        $counter = DB::queryFirstField ( "SELECT COUNT(*) FROM accounts" );
        $this->assert ( $counter === strval ( 0 ) );

        $row = DB::queryFirstRow ( "SELECT * FROM accounts" );
        $this->assert ( $row === null );

        $field = DB::queryFirstField ( "SELECT * FROM accounts" );
        $this->assert ( $field === null );

        $field = DB::queryOneField ( 'nothere', "SELECT * FROM accounts" );
        $this->assert ( $field === null );

        $column = DB::queryFirstColumn ( "SELECT * FROM accounts" );
        $this->assert ( is_array ( $column ) && count ( $column ) === 0 );

        $column = DB::queryOneColumn ( 'nothere', "SELECT * FROM accounts" ); // TODO: is this what we want?
        $this->assert ( is_array ( $column ) && count ( $column ) === 0 );
    }
    function test_2_insert_row() {
        $true = DB::insert ( 'accounts', array (
                'username' => 'Abe',
                'password' => 'hello'
        ) );

        $this->assert ( $true === true );
        $this->assert ( DB::affectedRows () === 1 );

        $counter = DB::queryFirstField ( "SELECT COUNT(*) FROM accounts" );
        $this->assert ( $counter === strval ( 1 ) );
    }
    function test_3_more_inserts() {
        DB::insert ( '`accounts`', array (
                'username' => 'Bart',
                'password' => 'hello',
                'age' => 15,
                'height' => 10.371
        ) );
        $dbname = DB::$dbName;
        DB::insert ( "`$dbname`.`accounts`", array (
                'username' => 'Charlie\'s Friend',
                'password' => 'goodbye',
                'age' => 30,
                'height' => 155.23,
                'favorite_word' => null
        ) );

        $this->assert ( DB::insertId () === 3 );
        $counter = DB::queryFirstField ( "SELECT COUNT(*) FROM accounts" );
        $this->assert ( $counter === strval ( 3 ) );

        DB::insert ( '`accounts`', array (
                'username' => 'Deer',
                'password' => '',
                'age' => 15,
                'height' => 10.371
        ) );

        $username = DB::queryFirstField ( "SELECT username FROM accounts WHERE password=%s0", null );
        $this->assert ( $username === 'Deer' );

        $password = DB::queryFirstField ( "SELECT password FROM accounts WHERE favorite_word IS NULL" );
        $this->assert ( $password === 'goodbye' );

        DB::$usenull = false;
        DB::insertUpdate ( 'accounts', array (
                'id' => 3,
                'favorite_word' => null
        ) );

        $password = DB::queryFirstField ( "SELECT password FROM accounts WHERE favorite_word=%s AND favorite_word=%s", null, '' );
        $this->assert ( $password === 'goodbye' );

        DB::$usenull = true;
        DB::insertUpdate ( 'accounts', array (
                'id' => 3,
                'favorite_word' => null
        ) );

        DB::$param_char = '###';
        $bart = DB::queryFirstRow ( "SELECT * FROM accounts WHERE age IN ###li AND height IN ###ld AND username IN ###ls", array (
                15,
                25
        ), array (
                10.371,
                150.123
        ), array (
                'Bart',
                'Barts'
        ) );
        $this->assert ( $bart ['username'] === 'Bart' );
        DB::$param_char = '%';

        $charlie_password = DB::queryFirstField ( "SELECT password FROM accounts WHERE username IN %ls AND username = %s", array (
                'Charlie',
                'Charlie\'s Friend'
        ), 'Charlie\'s Friend' );
        $this->assert ( $charlie_password === 'goodbye' );

        $charlie_password = DB::queryOneField ( 'password', "SELECT * FROM accounts WHERE username IN %ls AND username = %s", array (
                'Charlie',
                'Charlie\'s Friend'
        ), 'Charlie\'s Friend' );
        $this->assert ( $charlie_password === 'goodbye' );

        $passwords = DB::queryFirstColumn ( "SELECT password FROM accounts WHERE username=%s", 'Bart' );
        $this->assert ( count ( $passwords ) === 1 );
        $this->assert ( $passwords [0] === 'hello' );

        $username = $password = $age = null;
        list ( $age, $username, $password ) = DB::queryOneList ( "SELECT age,username,password FROM accounts WHERE username=%s", 'Bart' );
        $this->assert ( $username === 'Bart' );
        $this->assert ( $password === 'hello' );
        $this->assert ( $age == 15 );

        $mysqli_result = DB::queryRaw ( "SELECT * FROM accounts WHERE favorite_word IS NULL" );
        $this->assert ( $mysqli_result instanceof MySQLi_Result );
        $row = $mysqli_result->fetch_assoc ();
        $this->assert ( $row ['password'] === 'goodbye' );
        $this->assert ( $mysqli_result->fetch_assoc () === null );
    }
    function test_4_query() {
        DB::update ( 'accounts', array (
                'birthday' => new DateTime ( '10 September 2000 13:13:13' )
        ), 'username=%s', 'Charlie\'s Friend' );

        $results = DB::query ( "SELECT * FROM accounts WHERE username=%s AND birthday IN %lt", 'Charlie\'s Friend', array (
                'September 10 2000 13:13:13'
        ) );
        $this->assert ( count ( $results ) === 1 );
        $this->assert ( $results [0] ['age'] === '30' && $results [0] ['password'] === 'goodbye' );
        $this->assert ( $results [0] ['birthday'] == '2000-09-10 13:13:13' );

        $results = DB::query ( "SELECT * FROM accounts WHERE username!=%s", "Charlie's Friend" );
        $this->assert ( count ( $results ) === 3 );

        $columnlist = DB::columnList ( 'accounts' );
        $this->assert ( count ( $columnlist ) === 8 );
        $this->assert ( $columnlist [0] === 'id' );
        $this->assert ( $columnlist [5] === 'height' );

        $tablelist = DB::tableList ();
        $this->assert ( count ( $tablelist ) === 2 );
        $this->assert ( $tablelist [0] === 'accounts' );

        $tablelist = null;
        $tablelist = DB::tableList ( DB::$dbName );
        $this->assert ( count ( $tablelist ) === 2 );
        $this->assert ( $tablelist [0] === 'accounts' );
    }
    function test_4_1_query() {
        DB::insert ( 'accounts', array (
                'username' => 'newguy',
                'password' => DB::sqleval ( "REPEAT('blah', %i)", '3' ),
                'age' => DB::sqleval ( '171+1' ),
                'height' => 111.15
        ) );

        $row = DB::queryOneRow ( "SELECT * FROM accounts WHERE password=%s", 'blahblahblah' );
        $this->assert ( $row ['username'] === 'newguy' );
        $this->assert ( $row ['age'] === '172' );

        $true = DB::update ( 'accounts', array (
                'password' => DB::sqleval ( "REPEAT('blah', %i)", 4 ),
                'favorite_word' => null
        ), 'username=%s', 'newguy' );

        $row = null;
        $row = DB::queryOneRow ( "SELECT * FROM accounts WHERE username=%s", 'newguy' );
        $this->assert ( $true === true );
        $this->assert ( $row ['password'] === 'blahblahblahblah' );
        $this->assert ( $row ['favorite_word'] === null );

        $row = DB::query ( "SELECT * FROM accounts WHERE password=%s_mypass AND (password=%s_mypass) AND username=%s_myuser", array (
                'myuser' => 'newguy',
                'mypass' => 'blahblahblahblah'
        ) );
        $this->assert ( count ( $row ) === 1 );
        $this->assert ( $row [0] ['username'] === 'newguy' );
        $this->assert ( $row [0] ['age'] === '172' );

        $true = DB::query ( "DELETE FROM accounts WHERE password=%s", 'blahblahblahblah' );
        $this->assert ( $true === true );
        $this->assert ( DB::affectedRows () === 1 );
    }
    function test_4_2_delete() {
        DB::insert ( 'accounts', array (
                'username' => 'gonesoon',
                'password' => 'something',
                'age' => 61,
                'height' => 199.194
        ) );

        $ct = DB::queryFirstField ( "SELECT COUNT(*) FROM accounts WHERE username=%s AND height=%d", 'gonesoon', 199.194 );
        $this->assert ( intval ( $ct ) === 1 );

        $ct = DB::queryFirstField ( "SELECT COUNT(*) FROM accounts WHERE username=%s1 AND height=%d0 AND height=%d", 199.194, 'gonesoon' );
        $this->assert ( intval ( $ct ) === 1 );

        DB::delete ( 'accounts', 'username=%s AND age=%i AND height=%d', 'gonesoon', '61', '199.194' );
        $this->assert ( DB::affectedRows () === 1 );

        $ct = DB::queryFirstField ( "SELECT COUNT(*) FROM accounts WHERE username=%s AND height=%d", 'gonesoon', '199.194' );
        $this->assert ( intval ( $ct ) === 0 );
    }
    function test_4_3_insertmany() {
        $ins [] = array (
                'username' => '1ofmany',
                'password' => 'something',
                'age' => 23,
                'height' => 190.194
        );
        $ins [] = array (
                'password' => 'somethingelse',
                'username' => '2ofmany',
                'age' => 25,
                'height' => 190.194
        );
        $ins [] = array (
                'password' => NULL,
                'username' => '3ofmany',
                'age' => 15,
                'height' => 111.951
        );

        DB::insert ( 'accounts', $ins );
        $this->assert ( DB::affectedRows () === 3 );

        $rows = DB::query ( "SELECT * FROM accounts WHERE height=%d ORDER BY age ASC", 190.194 );
        $this->assert ( count ( $rows ) === 2 );
        $this->assert ( $rows [0] ['username'] === '1ofmany' );
        $this->assert ( $rows [0] ['age'] === '23' );
        $this->assert ( $rows [1] ['age'] === '25' );
        $this->assert ( $rows [1] ['password'] === 'somethingelse' );
        $this->assert ( $rows [1] ['username'] === '2ofmany' );

        $nullrow = DB::queryOneRow ( "SELECT * FROM accounts WHERE username LIKE %ss", '3ofman' );
        $this->assert ( $nullrow ['password'] === NULL );
        $this->assert ( $nullrow ['age'] === '15' );
    }
    function test_5_insert_blobs() {
        DB::query ( "CREATE TABLE `storedata` (
      `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
      `picture` BLOB
    ) ENGINE = InnoDB" );

        $smile = file_get_contents ( 'smile1.jpg' );
        DB::insert ( 'storedata', array (
                'picture' => $smile
        ) );
        DB::query ( "INSERT INTO storedata (picture) VALUES (%s)", $smile );

        $getsmile = DB::queryFirstField ( "SELECT picture FROM storedata WHERE id=1" );
        $getsmile2 = DB::queryFirstField ( "SELECT picture FROM storedata WHERE id=2" );
        $this->assert ( $smile === $getsmile );
        $this->assert ( $smile === $getsmile2 );
    }
    function test_6_insert_ignore() {
        DB::insertIgnore ( 'accounts', array (
                'id' => 1, // duplicate primary key
                'username' => 'gonesoon',
                'password' => 'something',
                'age' => 61,
                'height' => 199.194
        ) );
    }
    function test_7_insert_update() {
        $true = DB::insertUpdate ( 'accounts', array (
                'id' => 2, // duplicate primary key
                'username' => 'gonesoon',
                'password' => 'something',
                'age' => 61,
                'height' => 199.194
        ), 'age = age + %i', 1 );

        $this->assert ( $true === true );
        $this->assert ( DB::affectedRows () === 2 ); // a quirk of MySQL, even though only 1 row was updated

        $result = DB::query ( "SELECT * FROM accounts WHERE age = %i", 16 );
        $this->assert ( count ( $result ) === 1 );
        $this->assert ( $result [0] ['height'] === '10.371' );

        DB::insertUpdate ( 'accounts', array (
                'id' => 2, // duplicate primary key
                'username' => 'blahblahdude',
                'age' => 233,
                'height' => 199.194
        ) );

        $result = DB::query ( "SELECT * FROM accounts WHERE age = %i", 233 );
        $this->assert ( count ( $result ) === 1 );
        $this->assert ( $result [0] ['height'] === '199.194' );
        $this->assert ( $result [0] ['username'] === 'blahblahdude' );

        DB::insertUpdate ( 'accounts', array (
                'id' => 2, // duplicate primary key
                'username' => 'gonesoon',
                'password' => 'something',
                'age' => 61,
                'height' => 199.194
        ), array (
                'age' => 74
        ) );

        $result = DB::query ( "SELECT * FROM accounts WHERE age = %i", 74 );
        $this->assert ( count ( $result ) === 1 );
        $this->assert ( $result [0] ['height'] === '199.194' );
        $this->assert ( $result [0] ['username'] === 'blahblahdude' );

        $multiples [] = array (
                'id' => 3, // duplicate primary key
                'username' => 'gonesoon',
                'password' => 'something',
                'age' => 61,
                'height' => 199.194
        );
        $multiples [] = array (
                'id' => 1, // duplicate primary key
                'username' => 'gonesoon',
                'password' => 'something',
                'age' => 61,
                'height' => 199.194
        );

        DB::insertUpdate ( 'accounts', $multiples, array (
                'age' => 914
        ) );
        $this->assert ( DB::affectedRows () === 4 );

        $result = DB::query ( "SELECT * FROM accounts WHERE age=914 ORDER BY id ASC" );
        $this->assert ( count ( $result ) === 2 );
        $this->assert ( $result [0] ['username'] === 'Abe' );
        $this->assert ( $result [1] ['username'] === 'Charlie\'s Friend' );

        $true = DB::query ( "UPDATE accounts SET age=15, username='Bart' WHERE age=%i", 74 );
        $this->assert ( $true === true );
        $this->assert ( DB::affectedRows () === 1 );
    }
    function test_8_lb() {
        $data = array (
                'username' => 'vookoo',
                'password' => 'dookoo'
        );

        $true = DB::query ( "INSERT into accounts %lb VALUES %ls", array_keys ( $data ), array_values ( $data ) );
        $result = DB::query ( "SELECT * FROM accounts WHERE username=%s", 'vookoo' );
        $this->assert ( $true === true );
        $this->assert ( count ( $result ) === 1 );
        $this->assert ( $result [0] ['password'] === 'dookoo' );
    }
    function test_9_fullcolumns() {
        $true = DB::insert ( 'profile', array (
                'id' => 1,
                'signature' => 'u_suck'
        ) );
        DB::query ( "UPDATE accounts SET profile_id=1 WHERE id=2" );

        $r = DB::queryFullColumns ( "SELECT accounts.*, profile.*, 1+1 FROM accounts
      INNER JOIN profile ON accounts.profile_id=profile.id" );

        $this->assert ( $true === true );
        $this->assert ( count ( $r ) === 1 );
        $this->assert ( $r [0] ['accounts.id'] === '2' );
        $this->assert ( $r [0] ['profile.id'] === '1' );
        $this->assert ( $r [0] ['profile.signature'] === 'u_suck' );
        $this->assert ( $r [0] ['1+1'] === '2' );
    }
    function test_901_updatewithspecialchar() {
        $data = 'www.mysite.com/product?s=t-%s-%%3d%%3d%i&RCAID=24322';
        DB::update ( 'profile', array (
                'signature' => $data
        ), 'id=%i', 1 );
        $signature = DB::queryFirstField ( "SELECT signature FROM profile WHERE id=%i", 1 );
        $this->assert ( $signature === $data );

        DB::update ( 'profile', array (
                'signature' => "%li "
        ), "id = %d", 1 );
        $signature = DB::queryFirstField ( "SELECT signature FROM profile WHERE id=%i", 1 );
        $this->assert ( $signature === "%li " );
    }
}

?>
