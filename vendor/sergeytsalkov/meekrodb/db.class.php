<?php
/*
    Copyright (C) 2008 Sergey Tsalkov (stsalkov@gmail.com)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Lesser General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Lesser General Public License for more details.

    You should have received a copy of the GNU Lesser General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// If the mysqli extension is missing, trying to load MeekroDB will fail
// because the MYSQLI_OPT_CONNECT_TIMEOUT constant will be missing.
// Putting our warning here is the only way to make sure the user sees a sensible
// error message.
if (! extension_loaded('mysqli')) {
  throw new Exception("MeekroDB requires the mysqli extension for PHP");
}

/**
 * @link https://meekro.com/docs/retrieving-data.html Retrieving Data
 * 
 * @method static mixed query(string $query, ...$parameters)
 * @method static mixed queryFirstRow(string $query, ...$parameters)
 * @method static mixed queryFirstField(string $query, ...$parameters)
 * @method static mixed queryFirstList(string $query, ...$parameters)
 * @method static mixed queryFirstColumn(string $query, ...$parameters)
 * @method static mixed queryFullColumns(string $query, ...$parameters)
 * @method static mixed queryWalk(string $query, ...$parameters)
 * 
 * @link https://meekro.com/docs/altering-data.html Altering Data
 * 
 * @method static int insert(string $table_name, array $data, ...$parameters)
 * @method static mixed insertId()
 * @method static int insertIgnore(string $table_name, array $data, ...$parameters)
 * @method static int insertUpdate(string $table_name, array $data, ...$parameters)
 * @method static int replace(string $table_name, array $data, ...$parameters)
 * @method static int update(string $table_name, array $data, ...$parameters)
 * @method static int delete(string $table_name, ...$parameters)
 * @method static int affectedRows()
 * 
 * @link https://meekro.com/docs/transactions.html Transactions
 * 
 * @method static int startTransaction()
 * @method static int commit()
 * @method static int rollback()
 * @method static int transactionDepth()
 * 
 * @link https://meekro.com/docs/hooks.html
 * 
 * @method static int addHook(string $hook_type, callable $fn)
 * @method static void removeHook(string $hook_type, int $hook_id)
 * @method static void removeHooks(string $hook_type)
 * 
 * @link https://meekro.com/docs/misc-methods.html Misc Methods and Variables
 * 
 * @method static void useDB(string $database_name)
 * @method static array tableList(?string $database_name = null)
 * @method static array columnList(string $table_name)
 * @method static void disconnect()
 * @method static mysqli get()
 * @method static string lastQuery()
 * @method static string parse(string $query, ...$parameters)
 * @method static string serverVersion()
 */
class DB {
  // initial connection
  public static $dbName = '';
  public static $user = '';
  public static $password = '';
  public static $host = 'localhost';
  public static $port = 3306; //hhvm complains if this is null
  public static $socket = null;
  public static $encoding = 'latin1';
  
  // configure workings
  public static $param_char = '%';
  public static $named_param_seperator = '_';
  public static $nested_transactions = false;
  public static $ssl = null;
  public static $connect_options = array(MYSQLI_OPT_CONNECT_TIMEOUT => 30);
  public static $connect_flags = 0;
  public static $reconnect_after = 14400;
  public static $logfile;
  
  // internal
  protected static $mdb = null;
  public static $variables_to_sync = array('param_char', 'named_param_seperator', 'nested_transactions', 'ssl', 'connect_options', 'connect_flags', 'reconnect_after', 'logfile');
  
  public static function getMDB() {
    $mdb = DB::$mdb;
    
    if ($mdb === null) {
      $mdb = DB::$mdb = new MeekroDB();
    }

    // Sync everytime because settings might have changed. It's fast.
    $mdb->sync_config(); 
    
    return $mdb;
  }

  public static function __callStatic($name, $args) {
    $fn = array(DB::getMDB(), $name);
    if (! is_callable($fn)) {
      throw new MeekroDBException("MeekroDB does not have a method called $name");
    }

    return call_user_func_array($fn, $args);
  }

  // --- begin deprecated methods (kept for backwards compatability)
  static function debugMode($enable=true) {
    if ($enable) self::$logfile = fopen('php://output', 'w');
    else self::$logfile = null;
  }
}


class MeekroDB {
  // initial connection
  public $dbName = '';
  public $user = '';
  public $password = '';
  public $host = 'localhost';
  public $port = 3306;
  public $socket = null;
  public $encoding = 'latin1';
  
  // configure workings
  public $param_char = '%';
  public $named_param_seperator = '_';
  public $nested_transactions = false;
  public $ssl = null;
  public $connect_options = array(MYSQLI_OPT_CONNECT_TIMEOUT => 30);
  public $connect_flags = 0;
  public $reconnect_after = 14400;
  public $logfile;
  
  // internal
  public $internal_mysql = null;
  public $server_info = null;
  public $insert_id = 0;
  public $num_rows = 0;
  public $affected_rows = 0;
  public $current_db = null;
  public $nested_transactions_count = 0;
  public $last_query;
  public $last_query_at=0;

  protected $hooks = array(
    'pre_parse' => array(),
    'pre_run' => array(),
    'post_run' => array(),
    'run_success' => array(),
    'run_failed' => array(),
  );

  public function __construct($host=null, $user=null, $password=null, $dbName=null, $port=null, $encoding=null, $socket=null)  {
    if ($host === null) $host = DB::$host;
    if ($user === null) $user = DB::$user;
    if ($password === null) $password = DB::$password;
    if ($dbName === null) $dbName = DB::$dbName;
    if ($port === null) $port = DB::$port;
    if ($socket === null) $socket = DB::$socket;
    if ($encoding === null) $encoding = DB::$encoding;
    
    $this->host = $host;
    $this->user = $user;
    $this->password = $password;
    $this->dbName = $dbName;
    $this->port = $port;
    $this->socket = $socket;
    $this->encoding = $encoding;

    $this->sync_config();
  }

  /**
   * @internal 
   * suck in config settings from static class
   */
  public function sync_config() {
    foreach (DB::$variables_to_sync as $variable) {
      if ($this->$variable !== DB::$$variable) {
        $this->$variable = DB::$$variable;
      }
    }
  }
  
  public function get() {
    $mysql = $this->internal_mysql;
    
    if (!($mysql instanceof MySQLi)) {
      // PHP 8.1+ sets a reporting mode by default, causing it to throw mysqli_sql_exceptions
      // we don't want this because we're checking mysqli->error anyway
      $driver = new mysqli_driver();
      $driver->report_mode = MYSQLI_REPORT_OFF;

      if (! $this->port) $this->port = ini_get('mysqli.default_port');
      $this->current_db = $this->dbName;
      $mysql = new mysqli();

      $connect_flags = $this->connect_flags;
      if (is_array($this->ssl) && isset($this->ssl['key']) === true && empty($this->ssl['key']) === false) {
        // PHP produces a warning when trying to access undefined array keys
        $ssl_default = array('key' => NULL, 'cert' => NULL, 'ca_cert' => NULL, 'ca_path' => NULL, 'cipher' => NULL);
        $ssl = array_merge($ssl_default, $this->ssl);
        $mysql->ssl_set($ssl['key'], $ssl['cert'], $ssl['ca_cert'], $ssl['ca_path'], $ssl['cipher']);
        $connect_flags |= MYSQLI_CLIENT_SSL;
      }

      foreach ($this->connect_options as $key => $value) {
        $mysql->options($key, $value);
      }

      // suppress warnings, since we will check connect_error anyway
      @$mysql->real_connect($this->host, $this->user, $this->password, $this->dbName, $this->port, $this->socket, $connect_flags);
      
      if ($mysql->connect_error) {
        throw new MeekroDBException("Unable to connect to MySQL server! Error: {$mysql->connect_error}");
      }
      
      $mysql->set_charset($this->encoding);
      $this->internal_mysql = $mysql;
      $this->server_info = $mysql->server_info;
    }
    
    return $mysql;
  }
  
  public function disconnect() {
    if ($this->internal_mysql) {
      $this->internal_mysql->close();
    }
    $this->internal_mysql = null; 
  }

  function addHook($type, $fn) {
    if (! array_key_exists($type, $this->hooks)) {
      throw new MeekroDBException("Hook type $type is not recognized");
    }

    if (! is_callable($fn)) {
      throw new MeekroDBException("Second arg to addHook() must be callable");
    }

    $this->hooks[$type][] = $fn;
    end($this->hooks[$type]);
    return key($this->hooks[$type]);
  }

  function removeHook($type, $index) {
    if (! array_key_exists($type, $this->hooks)) {
      throw new MeekroDBException("Hook type $type is not recognized");
    }

    if (! array_key_exists($index, $this->hooks[$type])) {
      throw new MeekroDBException("That hook does not exist");
    }

    unset($this->hooks[$type][$index]);
  }

  function removeHooks($type) {
    if (! array_key_exists($type, $this->hooks)) {
      throw new MeekroDBException("Hook type $type is not recognized");
    }

    $this->hooks[$type] = array();
  }

  protected function runHook($type, $args=array()) {
    if (! array_key_exists($type, $this->hooks)) {
      throw new MeekroDBException("Hook type $type is not recognized");
    }

    if ($type == 'pre_parse') {
      $query = $args['query'];
      $args = $args['args'];

      foreach ($this->hooks[$type] as $hook) {
        $result = call_user_func($hook, array('query' => $query, 'args' => $args));
        if (is_null($result)) {
          $result = array($query, $args);
        }
        if (!is_array($result) || count($result) != 2) {
          throw new MeekroDBException("pre_parse hook must return an array of 2 items");
        }
        if (!is_string($result[0])) {
          throw new MeekroDBException("pre_parse hook must return a string as its first item");
        }
        if (!is_array($result[1])) {
          throw new MeekroDBException("pre_parse hook must return an array as its second item");
        }
        
        $query = $result[0];
        $args = $result[1];
      }

      return array($query, $args);
    }
    else if ($type == 'pre_run') {
      $query = $args['query'];

      foreach ($this->hooks[$type] as $hook) {
        $result = call_user_func($hook, array('query' => $query));
        if (is_null($result)) $result = $query;
        if (!is_string($result)) throw new MeekroDBException("pre_run hook must return a string");

        $query = $result;
      }

      return $query;
    }
    else if ($type == 'post_run') {

      foreach ($this->hooks[$type] as $hook) {
        call_user_func($hook, $args);
      }
    }
    else if ($type == 'run_success') {
      
      foreach ($this->hooks[$type] as $hook) {
        call_user_func($hook, $args);
      }
    }
    else if ($type == 'run_failed') {
      
      foreach ($this->hooks[$type] as $hook) {
        $result = call_user_func($hook, $args);
        if ($result === false) return false;
      }
    }
    else {
      throw new MeekroDBException("runHook() type $type not recognized");
    }
  }

  protected function defaultRunHook($args) {
    if (! $this->logfile) return;

    $query = $args['query'];
    $query = preg_replace('/\s+/', ' ', $query);

    $results[] = sprintf('[%s]', date('Y-m-d H:i:s'));
    $results[] = sprintf('QUERY: %s', $query);
    $results[] = sprintf('RUNTIME: %s ms', $args['runtime']);

    if ($args['affected']) {
      $results[] = sprintf('AFFECTED ROWS: %s', $args['affected']);
    }
    if ($args['rows']) {
      $results[] = sprintf('RETURNED ROWS: %s', $args['rows']);
    }
    if ($args['error']) {
      $results[] = 'ERROR: ' . $args['error'];
    }
    
    $results = implode("\n", $results) . "\n\n";

    if (is_resource($this->logfile)) {
      fwrite($this->logfile, $results);
    } else {
      file_put_contents($this->logfile, $results, FILE_APPEND);
    }
  }
  
  /**
   * @deprecated No longer recommended.
   */
  public function count() { return call_user_func_array(array($this, 'numRows'), func_get_args()); }

  /**
   * @deprecated No longer recommended.
   */
  public function numRows() { return $this->num_rows; }

  public function serverVersion() { $this->get(); return $this->server_info; }
  public function transactionDepth() { return $this->nested_transactions_count; }
  public function insertId() { return $this->insert_id; }
  public function affectedRows() { return $this->affected_rows; }
  
  public function lastQuery() { return $this->last_query; }
  
  public function useDB() { return call_user_func_array(array($this, 'setDB'), func_get_args()); }
  public function setDB($dbName) {
    $db = $this->get();
    if (! $db->select_db($dbName)) throw new MeekroDBException("Unable to set database to $dbName");
    $this->current_db = $dbName;
  }
  
  
  public function startTransaction() {
    if (!$this->nested_transactions || $this->nested_transactions_count == 0) {
      $this->query('START TRANSACTION');
      $this->nested_transactions_count = 1;
    } else {
      $this->query("SAVEPOINT LEVEL{$this->nested_transactions_count}");
      $this->nested_transactions_count++;
    }
    
    return $this->nested_transactions_count;
  }
  
  public function commit($all=false) {
    if ($this->nested_transactions && $this->nested_transactions_count > 0)
      $this->nested_transactions_count--;
    
    if (!$this->nested_transactions || $all || $this->nested_transactions_count == 0) {
      $this->nested_transactions_count = 0;
      $this->query('COMMIT');
    } else {
      $this->query("RELEASE SAVEPOINT LEVEL{$this->nested_transactions_count}");
    }
    
    return $this->nested_transactions_count;
  }
  
  public function rollback($all=false) {
    if ($this->nested_transactions && $this->nested_transactions_count > 0)
      $this->nested_transactions_count--;
    
    if (!$this->nested_transactions || $all || $this->nested_transactions_count == 0) {
      $this->nested_transactions_count = 0;
      $this->query('ROLLBACK');
    } else {
      $this->query("ROLLBACK TO SAVEPOINT LEVEL{$this->nested_transactions_count}");
    }
    
    return $this->nested_transactions_count;
  }
  
  protected function formatBackticks($name, $split_dots=true) {
    $name = trim($name, '`');
    
    if ($split_dots && strpos($name, '.')) {
      return implode('.', array_map(array($this, 'formatBackticks'), explode('.', $name)));
    }
    
    return '`' . str_replace('`', '``', $name) . '`'; 
  }

  /**
   * @internal has to be public for PHP 5.3 compatability
   */
  public function formatTableName($table) {
    return $this->formatBackticks($table, true);
  }

  /**
   * @internal has to be public for PHP 5.3 compatability
   */
  public function formatColumnName($column) {
    return $this->formatBackticks($column, false);
  }
  
  public function update() {
    $args = func_get_args();
    if (count($args) < 3) {
      throw new MeekroDBException("update(): at least 3 arguments expected");
    }

    $table = array_shift($args);
    $params = array_shift($args);
    if (! is_array($params)) {
      throw new MeekroDBException("update(): second argument must be assoc array");
    }
    $update_part = $this->parse(
      str_replace('%', $this->param_char, "UPDATE %b SET %hc"),
      $table, $params
    );

    if (is_array($args[0])) {
      $where_part = $this->parse(str_replace('%', $this->param_char, "%ha"), $args[0]);
    } else {
      // we don't know if they used named or numbered args, so the where clause
      // must be run through the parser separately
      $where_part = call_user_func_array(array($this, 'parse'), $args);
    }
    
    $query = $update_part . ' WHERE ' . $where_part;
    return $this->query($query);
  }

  public function delete() {
    $args = func_get_args();
    if (count($args) < 2) {
      throw new MeekroDBException("delete(): at least 2 arguments expected");
    }

    $table = $this->formatTableName(array_shift($args));

    if (is_array($args[0])) {
      $where = $this->parse(str_replace('%', $this->param_char, "%ha"), $args[0]);
    } else {
      $where = call_user_func_array(array($this, 'parse'), $args);
    }
    
    $query = "DELETE FROM {$table} WHERE {$where}";
    return $this->query($query);
  }
  
  protected function insertOrReplace($which, $table, $datas, $options=array()) {
    $datas = unserialize(serialize($datas)); // break references within array
    $keys = $values = array();
    
    if (isset($datas[0]) && is_array($datas[0])) {
      $var = '%ll?';
      foreach ($datas as $datum) {
        ksort($datum);
        if (! $keys) $keys = array_keys($datum);
        $values[] = array_values($datum);  
      }
      
    } else {
      $var = '%l?';
      $keys = array_keys($datas);
      $values = array_values($datas);
    }

    if ($which != 'INSERT' && $which != 'INSERT IGNORE' && $which != 'REPLACE') {
      throw new MeekroDBException('insertOrReplace() must be called with one of: INSERT, INSERT IGNORE, REPLACE');
    }
    
    if (isset($options['update']) && is_array($options['update']) && $options['update'] && $which == 'INSERT') {
      if (array_values($options['update']) !== $options['update']) {
        return $this->query(
          str_replace('%', $this->param_char, "INSERT INTO %b %lc VALUES $var ON DUPLICATE KEY UPDATE %hc"), 
          $table, $keys, $values, $options['update']);
      } else {
        $update_str = array_shift($options['update']);
        $query_param = array(
          str_replace('%', $this->param_char, "INSERT INTO %b %lc VALUES $var ON DUPLICATE KEY UPDATE ") . $update_str, 
          $table, $keys, $values);
        $query_param = array_merge($query_param, $options['update']);
        return call_user_func_array(array($this, 'query'), $query_param);
      }
      
    }
    
    return $this->query(
      str_replace('%', $this->param_char, "%l INTO %b %lc VALUES $var"), 
      $which, $table, $keys, $values);
  }
  
  public function insert($table, $data) { return $this->insertOrReplace('INSERT', $table, $data); }
  public function insertIgnore($table, $data) { return $this->insertOrReplace('INSERT IGNORE', $table, $data); }
  public function replace($table, $data) { return $this->insertOrReplace('REPLACE', $table, $data); }
  
  public function insertUpdate() {
    $args = func_get_args();
    $table = array_shift($args);
    $data = array_shift($args);
    
    if (! isset($args[0])) { // update will have all the data of the insert
      if (isset($data[0]) && is_array($data[0])) { //multiple insert rows specified -- failing!
        throw new MeekroDBException("Badly formatted insertUpdate() query -- you didn't specify the update component!");
      }
      
      $args[0] = $data;
    }
    
    if (is_array($args[0])) $update = $args[0];
    else $update = $args;
    
    return $this->insertOrReplace('INSERT', $table, $data, array('update' => $update)); 
  }
  
  public function sqleval() {
    $args = func_get_args();
    $text = call_user_func_array(array($this, 'parse'), $args);
    return new MeekroDBEval($text);
  }
  
  public function columnList($table) {
    $data = $this->query("SHOW COLUMNS FROM %b", $table);
    $columns = array();
    foreach ($data as $row) {
      $columns[$row['Field']] = array(
        'type' => $row['Type'],
        'null' => $row['Null'],
        'key' => $row['Key'],
        'default' => $row['Default'],
        'extra' => $row['Extra']
      );
    }

    return $columns;
  }
  
  public function tableList($db = null) {
    if ($db) {
      $olddb = $this->current_db;
      $this->useDB($db);
    }

    $result = $this->queryFirstColumn('SHOW TABLES');
    if (isset($olddb)) $this->useDB($olddb);
    return $result;
  }

  protected function paramsMap() {
    $t = $this;

    return array(
      's' => function($arg) use ($t) { return $t->escape($arg); },
      'i' => function($arg) use ($t) { return $t->intval($arg); },
      'd' => function($arg) use ($t) { return doubleval($arg); },
      'b' => function($arg) use ($t) { return $t->formatTableName($arg); },
      'c' => function($arg) use ($t) { return $t->formatColumnName($arg); },
      'l' => function($arg) use ($t) { return strval($arg); },
      't' => function($arg) use ($t) { return $t->escapeTS($arg); },
      'ss' => function($arg) use ($t) { return $t->escape("%" . str_replace(array('%', '_'), array('\%', '\_'), $arg) . "%"); },

      'ls' => function($arg) use ($t) { return array_map(array($t, 'escape'), $arg); },
      'li' => function($arg) use ($t) { return array_map(array($t, 'intval'), $arg); },
      'ld' => function($arg) use ($t) { return array_map('doubleval', $arg); },
      'lb' => function($arg) use ($t) { return array_map(array($t, 'formatTableName'), $arg); },
      'lc' => function($arg) use ($t) { return array_map(array($t, 'formatColumnName'), $arg); },
      'll' => function($arg) use ($t) { return array_map('strval', $arg); },
      'lt' => function($arg) use ($t) { return array_map(array($t, 'escapeTS'), $arg); },

      '?' => function($arg) use ($t) { return $t->sanitize($arg); },
      'l?' => function($arg) use ($t) { return $t->sanitize($arg, 'list'); },
      'll?' => function($arg) use ($t) { return $t->sanitize($arg, 'doublelist'); },
      'hc' => function($arg) use ($t) { return $t->sanitize($arg, 'hash'); },
      'ha' => function($arg) use ($t) { return $t->sanitize($arg, 'hash', ' AND '); },
      'ho' => function($arg) use ($t) { return $t->sanitize($arg, 'hash', ' OR '); },

      $this->param_char => function($arg) use ($t) { return $t->param_char; },
    );
  }

  protected function paramsMapArrayTypes() {
    return array('ls', 'li', 'ld', 'lb', 'lc', 'll', 'lt', 'l?', 'll?', 'hc', 'ha', 'ho');
  }

  protected function nextQueryParam($query) {
    $keys = array_keys($this->paramsMap());

    $first_position = PHP_INT_MAX;
    $first_param = null;
    $first_type = null;
    $arg = null;
    $named_arg = null;
    foreach ($keys as $key) {
      $fullkey = $this->param_char . $key;
      $pos = strpos($query, $fullkey);
      if ($pos === false) continue;

      if ($pos <= $first_position) {
        $first_position = $pos;
        $first_param = $fullkey;
        $first_type = $key;
      }
    }

    if (is_null($first_param)) return;

    $first_position_end = $first_position + strlen($first_param);
    $named_seperator_length = strlen($this->named_param_seperator);
    $arg_mask = '0123456789';
    $named_arg_mask = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_';
    
    if ($arg_number_length = strspn($query, $arg_mask, $first_position_end)) {
      $arg = intval(substr($query, $first_position_end, $arg_number_length));
      $first_param = substr($query, $first_position, strlen($first_param) + $arg_number_length);
    }
    else if (substr($query, $first_position_end, $named_seperator_length) == $this->named_param_seperator) {
      $named_arg_length = strspn($query, $named_arg_mask, $first_position_end + $named_seperator_length);

      if ($named_arg_length > 0) {
        $named_arg = substr($query, $first_position_end + $named_seperator_length, $named_arg_length);
        $first_param = substr($query, $first_position, strlen($first_param) + $named_seperator_length + $named_arg_length);
      }
    }

    return array(
      'param' => $first_param,
      'type' => $first_type,
      'pos' => $first_position,
      'arg' => $arg,
      'named_arg' => $named_arg,
    );
  }

  protected function preParse($query, $args) {
    $arg_ct = 0;
    $max_numbered_arg = 0;
    $use_numbered_args = false;
    $use_named_args = false;
    
    $queryParts = array();
    while ($Param = $this->nextQueryParam($query)) {
      if ($Param['pos'] > 0) {
        $queryParts[] = substr($query, 0, $Param['pos']);
      }

      if ($Param['type'] != $this->param_char && is_null($Param['arg']) && is_null($Param['named_arg'])) {
        $Param['arg'] = $arg_ct++;
      }

      if (! is_null($Param['arg'])) {
        $use_numbered_args = true;
        $max_numbered_arg = max($max_numbered_arg, $Param['arg']);
      }
      if (! is_null($Param['named_arg'])) {
        $use_named_args = true;
      }

      $queryParts[] = $Param;
      $query = substr($query, $Param['pos'] + strlen($Param['param']));
    }

    if (strlen($query) > 0) {
      $queryParts[] = $query;
    }

    if ($use_named_args) {
      if ($use_numbered_args) {
        throw new MeekroDBException("You can't mix named and numbered args!");
      }

      if (count($args) != 1 || !is_array($args[0])) {
        throw new MeekroDBException("If you use named args, you must pass an assoc array of args!");
      }
    }

    if ($use_numbered_args) {
      if ($max_numbered_arg+1 > count($args)) {
        throw new MeekroDBException(sprintf('Expected %d args, but only got %d!', $max_numbered_arg+1, count($args)));
      }
    }
    
    return $queryParts;
  }

  function parse($query) {
    $args = func_get_args();
    array_shift($args);
    $query = trim($query);

    if (! $args) return $query;
    $queryParts = $this->preParse($query, $args);

    $array_types = $this->paramsMapArrayTypes();
    $Map = $this->paramsMap();
    $query = '';
    foreach ($queryParts as $Part) {
      if (is_string($Part)) {
        $query .= $Part;
        continue;
      }

      $fn = $Map[$Part['type']];
      $is_array_type = in_array($Part['type'], $array_types, true);

      $val = null;
      if (!is_null($Part['named_arg'])) {
        $key = $Part['named_arg'];
        if (! array_key_exists($key, $args[0])) {
          throw new MeekroDBException("Couldn't find named arg {$key}!");
        }

        $val = $args[0][$key];
      }
      else if (!is_null($Part['arg'])) {
        $key = $Part['arg'];
        $val = $args[$key];
      }

      if ($is_array_type && !is_array($val)) {
        throw new MeekroDBException("Expected an array for arg $key but didn't get one!");
      }
      if ($is_array_type && count($val) == 0) {
        throw new MeekroDBException("Arg {$key} array can't be empty!");
      }
      if (!$is_array_type && is_array($val)) {
        $val = '';
      }

      if (is_object($val) && ($val instanceof WhereClause)) {
        if ($Part['type'] != 'l') {
          throw new MeekroDBException("WhereClause must be used with l arg, you used {$Part['type']} instead!");
        }

        list($clause_sql, $clause_args) = $val->textAndArgs();
        array_unshift($clause_args, $clause_sql); 
        $result = call_user_func_array(array($this, 'parse'), $clause_args);
      }
      else {
        $result = $fn($val);
        if (is_array($result)) $result = '(' . implode(',', $result) . ')';
      }
      
      $query .= $result;
    }

    return $query;
  }
  
  /**
   * @internal has to be public for PHP 5.3 compatability
   */
  public function escape($str) { return "'" . $this->get()->real_escape_string(strval($str)) . "'"; }
  
  /**
   * @internal has to be public for PHP 5.3 compatability
   */
  public function sanitize($value, $type='basic', $hashjoin=', ') {
    if ($type == 'basic') {
      if (is_object($value)) {
        if ($value instanceof MeekroDBEval) return $value->text;
        else if ($value instanceof DateTime) return $this->escape($value->format('Y-m-d H:i:s'));
        else return $this->escape($value); // use __toString() value for objects, when possible
      }
      
      if (is_null($value)) return 'NULL';
      else if (is_bool($value)) return ($value ? 1 : 0);
      else if (is_int($value)) return $value;
      else if (is_float($value)) return $value;
      else if (is_array($value)) return "''";
      else return $this->escape($value);

    } else if ($type == 'list') {
      if (is_array($value)) {
        $value = array_values($value);
        return '(' . implode(', ', array_map(array($this, 'sanitize'), $value)) . ')';
      } else {
        throw new MeekroDBException("Expected array parameter, got something different!");
      }
    } else if ($type == 'doublelist') {
      if (is_array($value) && array_values($value) === $value && is_array($value[0])) {
        $cleanvalues = array();
        foreach ($value as $subvalue) {
          $cleanvalues[] = $this->sanitize($subvalue, 'list');
        }
        return implode(', ', $cleanvalues);

      } else {
        throw new MeekroDBException("Expected double array parameter, got something different!");
      }
    } else if ($type == 'hash') {
      if (is_array($value)) {
        $pairs = array();
        foreach ($value as $k => $v) {
          $pairs[] = $this->formatColumnName($k) . '=' . $this->sanitize($v);
        }
        
        return implode($hashjoin, $pairs);
      } else {
        throw new MeekroDBException("Expected hash (associative array) parameter, got something different!");
      }
    } else {
      throw new MeekroDBException("Invalid type passed to sanitize()!");
    }
    
  }

  /**
   * @internal has to be public for PHP 5.3 compatability
   */
  public function escapeTS($ts) {
    if (is_string($ts)) {
      $str = date('Y-m-d H:i:s', strtotime($ts));
    }
    else if (is_object($ts) && ($ts instanceof DateTime)) {
      $str = $ts->format('Y-m-d H:i:s');
    }

    return $this->escape($str);
  }
  
  /**
   * @internal has to be public for PHP 5.3 compatability
   */
  public function intval($var) {
    if (PHP_INT_SIZE == 8) return intval($var);
    return floor(doubleval($var));
  }

  public function query() { return $this->queryHelper(array('assoc' => true), func_get_args()); }

  /**
   * @deprecated
   */
  public function queryAllLists() { return $this->queryHelper(array(), func_get_args()); }  
  public function queryFullColumns() { return $this->queryHelper(array('fullcols' => true), func_get_args()); }
  public function queryWalk() { return $this->queryHelper(array('walk' => true), func_get_args()); }
  
  protected function queryHelper($opts, $args) {
    $query = array_shift($args);

    $opts_fullcols = (isset($opts['fullcols']) && $opts['fullcols']);
    $opts_raw = (isset($opts['raw']) && $opts['raw']);
    $opts_unbuf = (isset($opts['unbuf']) && $opts['unbuf']);
    $opts_assoc = (isset($opts['assoc']) && $opts['assoc']);
    $opts_walk = (isset($opts['walk']) && $opts['walk']);
    $is_buffered = !($opts_unbuf || $opts_walk);

    if ($this->reconnect_after > 0 && time() - $this->last_query_at >= $this->reconnect_after) {
      $this->disconnect();
    }

    list($query, $args) = $this->runHook('pre_parse', array('query' => $query, 'args' => $args));    
    $sql = call_user_func_array(array($this, 'parse'), array_merge(array($query), $args));
    $sql = $this->runHook('pre_run', array('query' => $sql));
    $this->last_query = $sql;
    $this->last_query_at = time();
    
    $db = $this->get();
    $starttime = microtime(true);
    $result = $db->query($sql, $is_buffered ? MYSQLI_STORE_RESULT : MYSQLI_USE_RESULT);
    $runtime = microtime(true) - $starttime;
    $runtime = sprintf('%f', $runtime * 1000);

    $this->insert_id = $db->insert_id;
    $this->affected_rows = $db->affected_rows;

    // mysqli_result->num_rows won't initially show correct results for unbuffered data
    if ($is_buffered && ($result instanceof MySQLi_Result)) $this->num_rows = $result->num_rows;
    else $this->num_rows = null;

    $Exception = null;
    if ($db->error) {
      $Exception = new MeekroDBException($db->error, $sql, $db->errno);
    }

    $hookHash = array(
      'query' => $sql,
      'runtime' => $runtime,
      'exception' => null,
      'error' => null,
      'rows' => null,
      'affected' => null
    );
    if ($Exception) {
      $hookHash['exception'] = $Exception;
      $hookHash['error'] = $Exception->getMessage();
    } else if ($this->num_rows) {
      $hookHash['rows'] = $this->num_rows;
    } else {
      $hookHash['affected'] = $db->affected_rows;
    }

    $this->defaultRunHook($hookHash);
    $this->runHook('post_run', $hookHash);
    if ($Exception) {
      $result = $this->runHook('run_failed', $hookHash);
      if ($result !== false) throw $Exception;
    }
    else {
      $this->runHook('run_success', $hookHash);
    }
    
    if ($opts_walk) {
      return new MeekroDBWalk($db, $result);
    }
    if (!($result instanceof MySQLi_Result)) {
      // query was not a SELECT
      return $result ? $this->affected_rows : $result;
    }
    if ($opts_raw) {
      return $result;
    }
    
    $return = array();

    if ($opts_fullcols) {
      $infos = array();
      foreach ($result->fetch_fields() as $info) {
        if (strlen($info->table)) $infos[] = $info->table . '.' . $info->name;
        else $infos[] = $info->name;
      }
    }

    while ($row = ($opts_assoc ? $result->fetch_assoc() : $result->fetch_row())) {
      if ($opts_fullcols) $row = array_combine($infos, $row);
      $return[] = $row;
    }

    // free results
    $result->free();
    while ($db->more_results()) {
      $db->next_result();
      if ($result = $db->use_result()) $result->free();
    }
    
    return $return;
  }

  
  public function queryFirstRow() {
    $args = func_get_args();
    $result = call_user_func_array(array($this, 'query'), $args);
    if (!$result || !is_array($result)) return null;
    return reset($result);
  }

  
  public function queryFirstList() {
    $args = func_get_args();
    $result = call_user_func_array(array($this, 'queryAllLists'), $args);
    if (!$result || !is_array($result)) return null;
    return reset($result);
  }
  
  public function queryFirstColumn() { 
    $args = func_get_args();
    $results = call_user_func_array(array($this, 'queryAllLists'), $args);
    $ret = array();
    
    if (!count($results) || !count($results[0])) return $ret;
    
    foreach ($results as $row) {
      $ret[] = $row[0];
    }
    
    return $ret;
  }
  
  public function queryFirstField() { 
    $args = func_get_args();
    $row = call_user_func_array(array($this, 'queryFirstList'), $args);
    if ($row == null) return null;    
    return $row[0];
  }

  // --- begin deprecated methods (kept for backwards compatability)
  public function debugMode($enable=true) {
    if ($enable) $this->logfile = fopen('php://output', 'w');
    else $this->logfile = null;
  }

  /**
   * @deprecated
   */
  public function queryRaw() { 
    return $this->queryHelper(array('raw' => true), func_get_args());
  }

  /**
   * @deprecated
   */
  public function queryRawUnbuf() { 
    return $this->queryHelper(array('raw' => true, 'unbuf' => true), func_get_args());
  }

  /**
   * @deprecated
   */
  public function queryOneList() { 
    return call_user_func_array(array($this, 'queryFirstList'), func_get_args());
  }

  /**
   * @deprecated
   */
  public function queryOneRow() { 
    return call_user_func_array(array($this, 'queryFirstRow'), func_get_args());
  }

  /**
   * @deprecated
   */
  public function queryOneField() {
    $args = func_get_args();
    $column = array_shift($args);
    
    $row = call_user_func_array(array($this, 'queryOneRow'), $args);
    if ($row == null) { 
      return null;
    } else if ($column === null) {
      $keys = array_keys($row);
      $column = $keys[0];
    }  
    
    return $row[$column];
  }

  /**
   * @deprecated
   */
  public function queryOneColumn() {
    $args = func_get_args();
    $column = array_shift($args);
    $results = call_user_func_array(array($this, 'query'), $args);
    $ret = array();
    
    if (!count($results) || !count($results[0])) return $ret;
    if ($column === null) {
      $keys = array_keys($results[0]);
      $column = $keys[0];
    }
    
    foreach ($results as $row) {
      $ret[] = $row[$column];
    }
    
    return $ret;
  }

}

class MeekroDBWalk {
  protected $mysqli;
  protected $result;

  function __construct(MySQLi $mysqli, $result) {
    $this->mysqli = $mysqli;
    $this->result = $result;
  }

  function next() {
    // $result can be non-object if the query was not a SELECT
    if (! ($this->result instanceof MySQLi_Result)) return;
    if ($row = $this->result->fetch_assoc()) return $row;
    else $this->free();
  }

  function free() {
    if (! ($this->result instanceof MySQLi_Result)) return;

    $this->result->free();
    while ($this->mysqli->more_results()) {
      $this->mysqli->next_result();
      if ($result = $this->mysqli->use_result()) $result->free();
    }

    $this->result = null;
  }

  function __destruct() {
    $this->free();
  }
}

class WhereClause {
  public $type = 'and'; //AND or OR
  public $negate = false;
  public $clauses = array();
  
  function __construct($type) {
    $type = strtolower($type);
    if ($type !== 'or' && $type !== 'and') throw new MeekroDBException('you must use either WhereClause(and) or WhereClause(or)');
    $this->type = $type;
  }
  
  function add() {
    $args = func_get_args();
    $sql = array_shift($args);
    
    if ($sql instanceof WhereClause) {
      $this->clauses[] = $sql;
    } else {
      $this->clauses[] = array('sql' => $sql, 'args' => $args);
    }
  }
  
  function negateLast() {
    $i = count($this->clauses) - 1;
    if (!isset($this->clauses[$i])) return;
    
    if ($this->clauses[$i] instanceof WhereClause) {
      $this->clauses[$i]->negate();
    } else {
      $this->clauses[$i]['sql'] = 'NOT (' . $this->clauses[$i]['sql'] . ')';
    }
  }
  
  function negate() {
    $this->negate = ! $this->negate;
  }
  
  function addClause($type) {
    $r = new WhereClause($type);
    $this->add($r);
    return $r;
  }
  
  function count() {
    return count($this->clauses);
  }
  
  function textAndArgs() {
    $sql = array();
    $args = array();
    
    if (count($this->clauses) == 0) return array('(1)', $args);
    
    foreach ($this->clauses as $clause) {
      if ($clause instanceof WhereClause) { 
        list($clause_sql, $clause_args) = $clause->textAndArgs();
      } else {
        $clause_sql = $clause['sql'];
        $clause_args = $clause['args'];
      }
      
      $sql[] = "($clause_sql)";
      $args = array_merge($args, $clause_args);
    }
    
    if ($this->type == 'and') $sql = sprintf('(%s)', implode(' AND ', $sql));
    else $sql = sprintf('(%s)', implode(' OR ', $sql));
    
    if ($this->negate) $sql = '(NOT ' . $sql . ')';
    return array($sql, $args);
  }
}

class DBTransaction {
  private $committed = false;
  
  function __construct() { 
    DB::startTransaction(); 
  }
  function __destruct() { 
    if (! $this->committed) DB::rollback(); 
  }
  function commit() {
    DB::commit();
    $this->committed = true;
  }
  
  
}

class MeekroDBException extends Exception {
  protected $query = '';
  
  function __construct($message='', $query='', $code = 0) {
    parent::__construct($message);
    $this->query = $query;
    $this->code = $code;
  }
  
  public function getQuery() { return $this->query; }
}

class MeekroDBEval {
  public $text = '';
  
  function __construct($text) {
    $this->text = $text;
  }
}

?>
