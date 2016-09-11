<?
function new_error_callback($params) {
    global $error_callback_worked;

    if (substr_count ( $params ['error'], 'You have an error in your SQL syntax' ))
        $error_callback_worked = 1;
}
function my_debug_handler($params) {
    global $debug_callback_worked;
    if (substr_count ( $params ['query'], 'SELECT' ))
        $debug_callback_worked = 1;
}
class ErrorTest extends SimpleTest {
    function test_1_error_handler() {
        global $error_callback_worked, $static_error_callback_worked, $nonstatic_error_callback_worked;

        DB::$error_handler = 'new_error_callback';
        DB::query ( "SELET * FROM accounts" );
        $this->assert ( $error_callback_worked === 1 );

        DB::$error_handler = array (
                'ErrorTest',
                'static_error_callback'
        );
        DB::query ( "SELET * FROM accounts" );
        $this->assert ( $static_error_callback_worked === 1 );

        DB::$error_handler = array (
                $this,
                'nonstatic_error_callback'
        );
        DB::query ( "SELET * FROM accounts" );
        $this->assert ( $nonstatic_error_callback_worked === 1 );
    }
    public static function static_error_callback($params) {
        global $static_error_callback_worked;
        if (substr_count ( $params ['error'], 'You have an error in your SQL syntax' ))
            $static_error_callback_worked = 1;
    }
    public function nonstatic_error_callback($params) {
        global $nonstatic_error_callback_worked;
        if (substr_count ( $params ['error'], 'You have an error in your SQL syntax' ))
            $nonstatic_error_callback_worked = 1;
    }
    function test_2_exception_catch() {
        $dbname = DB::$dbName;
        DB::$error_handler = '';
        DB::$throw_exception_on_error = true;
        try {
            DB::query ( "SELET * FROM accounts" );
        } catch ( MeekroDBException $e ) {
            $this->assert ( substr_count ( $e->getMessage (), 'You have an error in your SQL syntax' ) );
            $this->assert ( $e->getQuery () === 'SELET * FROM accounts' );
            $exception_was_caught = 1;
        }
        $this->assert ( $exception_was_caught === 1 );

        try {
            DB::insert ( "`$dbname`.`accounts`", array (
                    'id' => 2,
                    'username' => 'Another Dude\'s \'Mom"',
                    'password' => 'asdfsdse',
                    'age' => 35,
                    'height' => 555.23
            ) );
        } catch ( MeekroDBException $e ) {
            $this->assert ( substr_count ( $e->getMessage (), 'Duplicate entry' ) );
            $exception_was_caught = 2;
        }
        $this->assert ( $exception_was_caught === 2 );
    }
    function test_3_debugmode_handler() {
        global $debug_callback_worked;

        DB::debugMode ( 'my_debug_handler' );
        DB::query ( "SELECT * FROM accounts WHERE username!=%s", "Charlie's Friend" );

        $this->assert ( $debug_callback_worked === 1 );

        DB::debugMode ( false );
    }
}

?>
