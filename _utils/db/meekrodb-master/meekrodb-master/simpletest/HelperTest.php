<?
class HelperTest extends SimpleTest {
    function test_1_verticalslice() {
        $all = DB::query ( "SELECT * FROM accounts ORDER BY id ASC" );
        $names = DBHelper::verticalSlice ( $all, 'username' );
        $this->assert ( count ( $names ) === 5 );
        $this->assert ( $names [0] === 'Abe' );

        $ages = DBHelper::verticalSlice ( $all, 'age', 'username' );
        $this->assert ( count ( $ages ) === 5 );
        $this->assert ( $ages ['Abe'] === '700' );
    }
    function test_2_reindex() {
        $all = DB::query ( "SELECT * FROM accounts ORDER BY id ASC" );
        $names = DBHelper::reIndex ( $all, 'username' );
        $this->assert ( count ( $names ) === 5 );
        $this->assert ( $names ['Bart'] ['username'] === 'Bart' );
        $this->assert ( $names ['Bart'] ['age'] === '15' );

        $names = DBHelper::reIndex ( $all, 'username', 'age' );
        $this->assert ( $names ['Bart'] ['15'] ['username'] === 'Bart' );
        $this->assert ( $names ['Bart'] ['15'] ['age'] === '15' );
    }
    function test_3_empty() {
        $none = DB::query ( "SELECT * FROM accounts WHERE username=%s", 'doesnotexist' );
        $this->assert ( is_array ( $none ) && count ( $none ) === 0 );
        $names = DBHelper::verticalSlice ( $none, 'username', 'age' );
        $this->assert ( is_array ( $names ) && count ( $names ) === 0 );

        $names_other = DBHelper::reIndex ( $none, 'username', 'age' );
        $this->assert ( is_array ( $names_other ) && count ( $names_other ) === 0 );
    }
    function test_4_null() {
        DB::query ( "UPDATE accounts SET password = NULL WHERE username=%s", 'Bart' );

        $all = DB::query ( "SELECT * FROM accounts ORDER BY id ASC" );
        $ages = DBHelper::verticalSlice ( $all, 'age', 'password' );
        $this->assert ( count ( $ages ) === 5 );
        $this->assert ( $ages [''] === '15' );

        $passwords = DBHelper::reIndex ( $all, 'password' );
        $this->assert ( count ( $passwords ) === 5 );
        $this->assert ( $passwords [''] ['username'] === 'Bart' );
        $this->assert ( $passwords [''] ['password'] === NULL );
    }
}
?>
