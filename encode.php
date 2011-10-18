<?php

/***
 * Shorthand encoding and decoding
 **/
class Value {
  private $value;
  function __construct($value) { $this->value = $value; }
  function __toString() { return $this->value; }
}

// SQL
class SQL extends Value {
  private static $defaultLock = 'share';

  static function encode($value) {
    if ((is_int($value)) || (is_float($value))) {
      return strval($value);
    }elseif (is_bool($value)) {
      return $value ? '1' : '0';
    }elseif (is_null($value)) {
      return 'NULL';
    }elseif ($value instanceof SQL) {
      return $value->getValue();
    }elseif (is_object($value) || is_string($value)) {
      return '\''.mysql_real_escape_string(strval($value)).'\'';
    }elseif (is_array($value)) {
      return array_map(array('SQL', 'encode'), $value); 
    }else{
      throw new InvalidArgumentException();
    }
  }

  /***
   * Allow changing default value for lock()
   **/
  static function setDefaultLock($lock) {
    self::$defaultLock = $lock;
  }

  /***
   * Handy SQL generation methods
   **/
  static function lock($mode=NULL) {
    if ($mode === NULL) $mode = self::$defaultLock;

    if ($mode == 'update') return ' FOR UPDATE';
    elseif ($mode == 'share') return ' LOCK IN SHARE MODE';
    elseif ($mode == 'none') return '';
    else{
      throw new Exception("Unknown MySQL lock mode: $mode");
    }
  }

  static function interval($interval) {
    if (!preg_match('/^[0-9]+ (DAY|MONTH|YEAR)$/', $interval)) throw new Exception('invalid interval');
    return ' INTERVAL '.$interval;
  }

	static function field($name) {
		$parts = explode('.', $name);
		if (count($parts) > 1) return $parts[0].'.`'.$parts[1].'`';
		else return '`'.$parts[0].'`';
	}

	static function fields($fields) {
		if (is_array($fields)) return '`'.implode('`,`',$fields).'`';
		else return $fields;
	}

	static function in($field, $options) {
		if (is_string($options)) $options = array($options);
    if (!$options) return '0';
    else return self::field($field).' IN ('.implode(',', array_map('SQL', $options)).')';
	}

	static function not_in($field, $options) {
		if (is_string($options)) $options = array($options);
    if (!$options) return '1';
    else return self::field($field).' NOT IN ('.implode(',', array_map('SQL', $options)).')';
	}

  static function table($table) {
    if (strpos($table, '.') === False) return "`$table`";
    else return $table;
  }

  static function from($table) {
		if ($table) {
      if (strpos($table, '.') === False) $table = "`$table`";
			return ' FROM '.$table;
		}else{
			return '';
		}
  }

  static function where($where) {
		if (is_array($where)) {
			$sql = '';
			foreach ($where as $key=>$value) {
				$sql .= ($sql?' AND ':'').'`'.$key.($value===NULL?'` is null':'`='.SQL($value));
			}
			return ' WHERE '.$sql;
		}else if ($where) {
		 	return ' WHERE '.$where;
		}else return '';
	}

  static function removeLock(&$query) {
    foreach (array(' FOR UPDATE', ' LOCK IN SHARE MODE') as $option) {
      if (ends_with($query, $option)) {
        $query = substr($query, 0, strlen($query)-strlen($option));
        return $option;
      }
    }
    return '';
  }
}
function SQL($value) { return SQL::encode($value); }

// HTML
class HTML extends Value {
  static function encode($value, $newlines=False) {
    if (is_array($value) || is_iterator($value)) {
      return map(function($value) use ($newlines) { return HTML::encode($value, $newlines); }, $value); 
    }elseif ($value instanceof Nullable && $value->isNull()) return NULL;
    elseif ($value instanceof HTML) return strval($value);
    elseif ($value === False || $value === NULL) return $value;
    elseif ($newlines) return str_replace("\n",'<br>',HTML($value));
    else return htmlentities(strval($value),ENT_QUOTES|ENT_IGNORE,'UTF-8');
  }
  static function decode($value) {
    return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
  }
}
function HTML($value,$newlines=False) { return HTML::encode($value, $newlines); }
function UNHTML($value) { return HTML::decode($value); }

// URL
class URL extends Value {
  static function encode($value) {
    if (is_array($value)) return array_map('URL',$value); 
    elseif ($value instanceof Iterator) return map('URL', $value);
    elseif ($value instanceof URL) return strval($value);
    else return urlencode(strval($value));
  }
}
function URL($value) { return URL::encode($value); }

// JSON
class JSON extends Value {
  static function encode($value) {
    if ($value instanceof Iterator) return map('JSON', $value);
    elseif ($value instanceof JSON) return strval($value);
    else return json_encode($value);
  }
}
function JSON($value) { return JSON::encode($value); }

// ASCII
function is_ascii($str) {
  return mb_check_encoding($str, 'ASCII');
}

function ASCII($string, $encoding='utf-8') {
  try {
    return iconv($encoding,"ascii//TRANSLIT",$string);
  } catch (Exception $e) {
    // Just strip non-ASCII
    $output = '';
    for ($k = 0; $k < strlen($string); $k++) {
      $l = ord($string[$k]);
      // Visible, \n or \t
      if ($l < 127 && ($l > 31 || $l == 10 || $l == 9)) $output .= chr($l);
    }
    return $output;
  }
}

// UTF8
function UTF8($string, $encoding='auto') {
  $encoding = strtoupper($encoding);

  if ($encoding == 'UTF8' || $encoding == 'UTF-8') return $string;
  elseif ($encoding == 'UNICODE-1-1-UTF-7') $encoding = 'UTF-7';

  // Convert to the encoding with fallback to 'auto' / ASCII
  try {
    return mb_convert_encoding($string, 'UTF-8', strtoupper($encoding));
  } catch (Exception $e) {
    try {
      return mb_convert_encoding($string, 'UTF-8', 'auto');
    } catch (Exception $e) {
      return ASCII($string, $encoding);
    }
  }
}

