<?php

namespace DB;

class Results implements \SeekableIterator, \Countable {
  protected $mysql_rs = NULL;

  private $rows = 0;
  private $position = 0;
  private $fields = NULL;

  function __construct($mysql_rs) {
    $this->mysql_rs = $mysql_rs;
    $this->rows = $this->mysql_rs ? mysql_num_rows($this->mysql_rs) : 0;

    // For tracking iteration
    $this->position = 0;
    $this->current = NULL;
    $this->ahead = False;
  }

  /***
   * Iterator methods
   **/
	public function seek($index) {
    if (!$this->mysql_rs) {
      if ($index == 0) return;
      else throw new OutOfBoundsException('Index out of range');
    }
		if ($index < 0 || $index >= $this->rows) throw new OutOfBoundsException('Index out of range');
    elseif ($index == $this->position) return;
    else{
      $this->position = $index;
      $this->current = NULL;
      mysql_data_seek($this->mysql_rs,$index);
    }
	}

  public function rewind() { $this->seek(0); }
  public function next() {
    if ($this->ahead) {
      $this->ahead = False;
      $this->current = NULL;
      $this->position++;
    }else{
      $this->seek($this->position+1);
    }
  }

  public function current() {
    if (!$this->current) {
      // If we were already ahead, seek backwards
      if ($this->ahead) {
        $this->seek($this->position);
      }
      $this->current = mysql_fetch_row($this->mysql_rs);
      $this->ahead = True;
    }

    // Don't return an array if theres only one item
    if (count($this->current) == 1) return $this->current[0];
    else return $this->current;
  }
  public function key() { return $this->position; }
  public function valid() { return ($this->position < $this->rows); }
  public function count() { return $this->rows; }

  /***
   * While loop style iterators
   **/
  public function getRow() {
    if ($this->current && $this->ahead) {
      $row = $this->current;
      $this->position++;
      $this->ahead = False;
      $this->current = NULL;
      return $row;
    }elseif ($this->ahead) {
      $this->seek($this->position);
      return $this->getRow();
    }else{
      $this->position++;
      return mysql_fetch_row($this->mysql_rs);
    }
  }

  public function fetchRow() {
    if (!$row = $this->getRow()) {
      return NULL;
    }
    
    return array_combine($this->getFields(), $row);

  }

  /***
   * Handy mysql commands
   **/
	function getFields()
	{
    if ($this->fields === NULL) {
      $this->fields = array();
      if ($this->mysql_rs) {
        $N = mysql_num_fields($this->mysql_rs);
        for ($k = 0; $k < $N; $k++) {
          $this->fields[] = mysql_field_name($this->mysql_rs, $k);
        }
      }
    }
		return $this->fields;
  }

  /***
   * Results helper functions
   **/
  function asArray() {
    $array = array();
    foreach ($this as $entry) $array[] = $entry;
    return $array;
  }
}

