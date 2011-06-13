<?php

namespace DB;

class Connection {

  //  Connection
  private $mysql = NULL;

  // Statistics tracking
	private $queryCount = 0;
	private $queryTime = 0;

  // Database connection settings
  private $host = NULL;
  private $username = NULL;
  private $password = NULL;
  private $database = NULL;

	// Are we currently in a transaction
	private $inTransaction = False;

  // Prepend SQL_NO_CACHE to select queries
  private $disableCache = False;

  // Callback to log all queries run
  private $cbLogQuery = array();
	
  /***
   * Constructor and destructor
   *
   * Destructor throws exception if transaction was uncommitted
   **/
	function __construct($host, $username, $password, $database=NULL)
	{
		$this->host = $host;
		$this->username = $username;
		$this->password = $password;
		$this->database = $database;
		$this->connect();
	}
	function __destruct() {
    if ($this->inTransaction) {
      throw new DroppedTransactionException();
    }
  }

  /***
   * Connect and disconnect
   **/
	function connect() {
    $this->mysql = mysql_connect($this->host,$this->username,$this->password);

    if (!$this->mysql) {
      throw new ConnectException('Could not connect to the  database on '.$this->host.' with username '.$this->username);
    }

    if ($this->database) {
      if (!mysql_select_db($this->database, $this->mysql)) {
        $this->mysql = NULL;
        throw new ConnectException('Could not select '.$this->database.' on '.$this->host.' as '.$this->username);
      }
    }

    if (!mysql_set_charset('utf8', $this->mysql)) {
      throw new ConnectException('Could not set the charset correctly');
    }

		$this->inTransaction = False;
	}
  function useDatabase($database) {
    $this->database = $database;
    $this->query('USE `'.$database.'`');
  }
	function disconnect()
	{
    if ($this->inTransaction) throw new DroppedTransactionException();
		return mysql_close($this->mysql);
	}

  /***
   * Transaction commands
   **/
  function begin() {
    if ($this->inTransaction) {
      throw new DroppedTransactionException('Begin was called during transaction');
    }
		$this->query('SET AUTOCOMMIT=0');
		$this->query('BEGIN TRANSACTION');
    $this->inTransaction = True;
  }
	function commit() {
    if (!$this->inTransaction) {
      throw new DroppedTransactionException('Commit was called while not in a transaction');
    }
		$this->query('COMMIT');
    $this->inTransaction = False;
	}
	function rollback() {
    if (!$this->inTransaction) {
      throw new DroppedTransactionException('Rollback was called while not in a transaction');
    }
		$this->query('ROLLBACK');
    $this->inTransaction = False;
	}

  /***
   * Add a bunch of callbacks
   **/
  function addQueryLogger($callback) {
    $this->cbLogQuery[] = $callback;
  }

  /***
   * Handy mysql commands
   **/
  function ping() {
    $result = mysql_ping($this->mysql);

    // Throw out an exception if connection dissapeared while in transaction
    if (!$result && $this->inTransaction) {
      throw new DroppedTransactionException();
    }

    return $result;
  }

	function lastInsertedID()
	{
		return mysql_insert_id($this->mysql);
	}

	function affectedRows() {        
		return mysql_affected_rows();
	}

  /***
   * Database query
   **/
	function query($query)
	{
    // Begin timing
		$startTime = microtime_float();

    // Run query and deal with errors
		$res = mysql_query($query, $this->mysql);
		if ($error = mysql_error($this->mysql)) {
      $errno = mysql_errno($this->mysql);

      if ($errno == 1062) return new DuplicateException($error, $errno);
      elseif ($errno == 1205) return new LockTimeoutException($error, $errno);
			throw new Exception($error, $errno);
    }else if (!$res) {
      throw new Exception('mysql_query returned false value, without an error message.');
    }

    // We're done timing
    $timeTaken = microtime_float()-$startTime;

    // Send out useful log information
    foreach ($this->cbLogQuery as $cb) {
      $cb(array(
        'query' => $query,
        'time' => $timeTaken
      ));
    }

    // Keep track of some statistics
		$this->queryTime += $timeTaken;
		$this->queryCount++;

    // We're done
		return $res;
	}


	// Check if an entry exists in a table
	function exists($table, $where=NULL) {
		$res = $this->query('SELECT * FROM '.$table.$this->where($where).' LIMIT 1');
		if (!$res)
		{
			return NULL;
		}elseif (mysql_num_rows($res) == 0)
		{
			return false;
		}else
		{
			return true;
		}
	}

  /***
   * Take full queries without helpers for select/insert/update
   **/
	function querySelect($query)
	{
		$res = $this->query($query);
		if (!$res) return NULL;
		else return new Results($res);
	}

  function queryInsert($query) {
    if (!$this->query($query)) {
      return NULL;
    }

    $id = mysql_insert_id();
    if ($id === 0) return True;
    else if ($id) return $id;
    else return NULL;
  }

  function queryUpdate($query) {
    if (!$this->query($query)) {
      return NULL;
    }

		return mysql_affected_rows();
  }

  function queryDelete($query) {
    if (!$this->query($query)) {
      return NULL;
    }

		return mysql_affected_rows();
  }

  /**
   * Construct a  SELECT query on SnapBill database
   * @param string $fields Fields to SELECT
   * @param string $from Tables to select FROM
   * @param string $where WHERE clause
   * @param string $orderby column name and ASC or DESC
   * @param int $limit LIMIT number
   * @return result 
   * @example
   * $resultset = $mysql->select("id, date", "invoice", "id=client->id", "date ASC");
   */
	function select($fields, $from=NULL, $where=NULL, $orderby=NULL, $limit=NULL) {
    $query  = 'SELECT '.($this->disableCache?'SQL_NO_CACHE ':'');
		$query .= $fields.$this->from($from).$this->where($where);
    $query .= ($orderby ? ' ORDER BY '.$orderby : '');
    $query .= ($limit   ? ' LIMIT '.$limit : '');
		return $this->querySelect($query);
	}

  /***
   * Execute a  SELECT query with support for paging
   * @param string $query The actual query to run
   * @param array &$paging Reference to array created by build_paging
   * @param bool $optimise Use an SQL_CALC_FOUND_ROWS optimisation rather than two queries (see http://bugs.mysql.com/bug.php?id=18454)
   **/
	function selectPaged($query, &$paging, $optimise=True) {
		if ($paging === False) {
			return $this->select($query);
		}
		$page = max(1, ARR($paging,'page'));
		$perpage = ARR($paging,'perpage',25);

    if ($optimise) {
      $res = $this->select('SQL_CALC_FOUND_ROWS '.$query.' LIMIT '.(($page-1)*$perpage).','.$perpage);
      $paging['total'] = $this->selectSingle('FOUND_ROWS()');
    }else{
      // Re-write the query stripping out original fields
      $from_position = stripos($query, ' from ');
      $paging['total'] = $this->selectSingle('count(*)'.substr($query, $from_position));
      // Run actual query after we check for sane page number
      $res = NULL;
    }

		$paging['numpages'] = ceil($paging['total'] / $perpage);
		if ($paging['total'] && $page > $paging['numpages']) {
			// Chosen page too high, force re-run of query
			$page = $paging['numpages'];
      $res = NULL;
    }
    // If we need to actually query the data still
    if (!$res) {
			$res = $this->select($query.' LIMIT '.(($page-1)*$perpage).','.$perpage);
		}

		$paging['page'] = $page;
		return $res;
	}

  /***
   * Insert / update / delete shorthands
   **/
	function insert($table, $data)
	{
		$fields = '';
		$values = '';
		foreach ($data as $key => $value)
		{
			if ($fields != '') $fields .= ',';
			$fields .= '`'.$key.'`';

			if ($values != '') $values .= ',';
			$values .= SQL($value);
		}
    return $this->queryInsert('INSERT INTO `'.$table.'` ('.$fields.') VALUES ('.$values.')');
	}

	function update($table, $actions, $where=False) {
		if (!$where) throw new FatalException('/db/update','$where must be set');

		$action_sql = '';
		foreach ($actions as $key => $value) {
      $action_sql .= ($action_sql ? ', ' : '') . "`$key`=".SQL($value);
		}
		
		return $this->queryUpdate('UPDATE `'.$table.'` SET '.$action_sql.$this->where($where));
	}

	function delete($table, $where=False) {
    return $this->queryDelete('DELETE '.$this->from($table).$this->where($where));
	}
	
	function insertUpdate($table,$data,$keyData) {
		$keyfields = '';
		$keyvalues = '';
		$fields = '';
		$values = '';
		$actions = '';
		foreach ($keyData as $key => $value) {
      if ($keyfields != '') $keyfields .= ',';
      $keyfields .= '`'.$key.'`';
      if ($keyvalues != '') $keyvalues .= ',';
      $keyvalues .= SQL($value);
    }
		foreach ($data as $key => $value) {
      if ($fields != '') $fields .= ',';
      $fields .= '`'.$key.'`';
      if ($values != '') $values .= ',';
      $values .= SQL($value);

      if ($actions != '') $actions .= ',';
      $actions .= '`'.$key.'` = '.SQL($value);
		}
		$this->query('INSERT INTO '.$table.' ('.$keyfields.','.$fields.') VALUES('.$keyvalues.','.$values.') ON DUPLICATE KEY UPDATE '.$actions);

		return mysql_affected_rows();
	}

  /***
   * Some helper methods
   **/
  function selectAll($query) {
    return $this->select($query)->asArray();
  }
  function selectSingle($query) {
    return $this->select($query)->current();
  }

	function count($table, $where=NULL) {
		return $this->selectSingle('count(*)', $table, $where);
	}

  /***
   * SQL Processing
   **/
	function fields($fields) {
		if (is_array($fields)) return '`'.implode('`,`',$fields).'`';
		else return $fields;
	}
	function where($where=NULL) {
		if (is_array($where)) {
			$sql = '';
			foreach ($where as $key=>$value) {
				$sql .= ($sql?' AND ':'').'`'.$key.($value===NULL?'` is null':'`='.$this->_($value));
			}
			return ' WHERE '.$sql;

		}elseif ($where) {
		 	return ' WHERE '.$where;
    }elseif ($where === NULL) {
      return '';
    }else{
      throw new Exception('where field is required (was set to false)');
    }
	}
	function interval($interval) {
    if (!preg_match('/^[0-9]+ (DAY|MONTH|YEAR)$/', $interval)) {
      throw new FormatException();
    }
		return ' INTERVAL '.$interval;
	}
	function field($name) {
		$parts = explode('.', $name);
		if (count($parts) > 1) return $parts[0].'.`'.$parts[1].'`';
		else return '`'.$parts[0].'`';
	}
	function in($field, $options=NULL) {
    // If we were passed both a field and options
    if ($options !== NULL) {
      return $this->field($field).$this->in($options);
    }else $options = $field;

		if (is_string($options)) $options = array($options);
		return ' IN ('.implode(',', array_map('SQL', $options)).')';
	}
	function from($from) {
		if ($from) {
      if (strpos($from, '.') === False) return " FROM `$from`";
			return " FROM $from";
		}elseif ($from === NULL) {
			return '';
    }else{
      throw new Exception('Unrecognised from pattern: '.$from);
    }
	}
}
