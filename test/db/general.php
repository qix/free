<?php

require 'common.php';

class General_TestCase extends PHPUnit_Framework_TestCase
{
  function connect() {
    $config = parse_ini_file('/home/'.posix_getlogin().'/.my.cnf', True);
    extract($config['mysql']);
    return new DB\Connection($host, $user, $password);
  }

  /***
   * Shorthand to connect and create a table with some data
   **/
  function connectCreateTable() {
    $db = $this->connect();

    $db->useDatabase('freetest');
    $db->query('CREATE TEMPORARY TABLE `single` ( `id` INT NOT NULL AUTO_INCREMENT , `name` VARCHAR( 64 ) NOT NULL , PRIMARY KEY ( `id` )) ENGINE = MYISAM ;');

    $this->assertEquals($db->insert('single', array('name' => 'Josh')),
                        1);

    $this->assertEquals($db->insert('single', array('name' => 'Greg')),
                        2);

    return $db;
  }

	function testConnection() {
    $db = $this->connect();

    $this->assertTrue($db->ping());

    $db->disconnect();
	}


  function testQuery() {
    $db = $this->connect();

    $this->assertTrue(is_resource($db->query('SELECT 4')));

    $db->disconnect();
  }

  function testInsert() {
    $db = $this->connect();

    $db->useDatabase('freetest');
    $db->query('CREATE TEMPORARY TABLE `single` ( `id` INT NOT NULL AUTO_INCREMENT , `name` VARCHAR( 64 ) NOT NULL , PRIMARY KEY ( `id` )) ENGINE = MYISAM ;');

    $this->assertEquals($db->insert('single', array('name' => 'Josh')),
                        1);

    $this->assertEquals($db->insert('single', array('name' => 'Greg')),
                        2);

    $this->assertEquals($db->selectSingle('name FROM single WHERE id=2'), 'Greg');
    $this->assertEquals($db->selectSingle('name FROM single WHERE id=1'), 'Josh');

    $db->disconnect();
  }

  function testGetRow() {
    $db = $this->connectCreateTable();

    // While loop style iteration to construct the array
    $names = array();
    $res = $db->select('name FROM single ORDER BY id');
    while (list($firstname) = $res->getRow()) {
      $names[] = $firstname;
    }

    // Make sure that worked
    $this->assertEquals($names, array('Josh', 'Greg'));

    $db->disconnect();
  }

  function testSingleIterator() {
    $db = $this->connectCreateTable();

    $names = array();
    foreach ($db->select('name FROM single ORDER BY id') as $name) {
      $names[] = $name;
    }
    $this->assertEquals($names, array('Josh', 'Greg'));

    // Test done
    $db->disconnect();
  }

  function testFetchRow() {
    $db = $this->connectCreateTable();

    // While loop style iteration to construct the array
    $names = array();
    $res = $db->select('id,name FROM single ORDER BY id');
    while ($row = $res->fetchRow()) {
      $names[$row['id']] = $row['name'];
    }

    // Make sure that worked
    $this->assertEquals($names, array(1=>'Josh', 2=>'Greg'));

    $db->disconnect();
  }
}



