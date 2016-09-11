<?
class ErrorTest_53 extends SimpleTest {
    function test_1_error_handler() {
        global $anonymous_error_callback_worked;

        DB::$throw_exception_on_error = false;
        DB::$error_handler = function ($params) {
            global $anonymous_error_callback_worked;
            if (substr_count ( $params ['error'], 'You have an error in your SQL syntax' ))
                $anonymous_error_callback_worked = 1;
        };
        DB::query ( "SELET * FROM accounts" );
        $this->assert ( $anonymous_error_callback_worked === 1 );
    }
}

?>
