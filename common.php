<?php
require dirname(__FILE__).'/autoload.php';
require dirname(__FILE__).'/array.php';
require dirname(__FILE__).'/encode.php';
require dirname(__FILE__).'/string.php';
require dirname(__FILE__).'/exception.php';

/***
 * A few interfaces that I feel should be important
 **/
interface Nullable {
  public function isNull();
}

class MapIterator extends IteratorIterator {
  private $fn;

  function __construct($fn, $iterator) {
    parent::__construct($iterator);
    $this->fn = $fn;
  }

  function current() {
    $fn = $this->fn;
    return $fn(parent::current());
  }
}

/***
 * Functions applied to either array or iterator
 **/
function is_iterator($value) {
  return $value instanceof Iterator;
}
function map($callback, $list) {
  if (is_array($list)) return array_map($callback, $list);
  elseif ($list instanceof Iterator) return new MapIterator($callback, $list);
  else throw new InvalidArgumentException();
}

/***
 * Maths
 **/
function sqr($n) {
  return $n*$n;
}


/***
 * Various helper functions
 **/
function microtime_float() {
  list($a, $b) = explode(' ', microtime());
  return floatval($a) + floatval($b);
}

function valid_ip($ip) {
  if (empty($ip) || ip2long($ip) == -1) return False;

  $reserved = array(array('0.0.0.0','2.255.255.255'),
                    array('10.0.0.0','10.255.255.255'),
                    array('127.0.0.0','127.255.255.255'),
                    array('169.254.0.0','169.254.255.255'),
                    array('172.16.0.0','172.31.255.255'),
                    array('192.0.2.0','192.0.2.255'),
                    array('192.168.0.0','192.168.255.255'),
                    array('255.255.255.0','255.255.255.255'));

  foreach ($reserved as $r) {
    if ((ip2long($ip) >= ip2long($r[0])) && (ip2long($ip) <= ip2long($r[1]))) return False;
  }
  return True;
}

function find_remote_ip() {
  foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'HTTP_X_FORWARDED') as $header) {
    if ($values = ARR($_SERVER, $header)) {
      foreach (explode(',', $values) as $ip) {
        if (valid_ip($ip)) return $ip;
      }
    }
  }

  return ARR($_SERVER,'REMOTE_ADDR','0.0.0.0');
}

function file_download_headers($filename, $mime_type, $length=NULL) {
  // Fixes for internet explorer
  session_write_close();
  header("Cache-Control: private, max-age=1, pre-check=1");
  header("Pragma: none");

  // Content-type and disposition
  header('Content-type: '.$mime_type);
  header('Content-Disposition: attachment; filename="'.addslashes($filename).'"');
  if ($length !== NULL) header('Content-length: '.$length);
}

function html_attributes($attrib) {
  $html = '';
  if (isset($attrib['if'])) {
    $attrib['data']['if'] = $attrib['if'];
    $attrib['class'][] = 'if';
    unset($attrib['if']);
  }
  foreach ($attrib as $k => $v) {
    if (is_array($v)) {
      if ($k == 'class') $v = implode(' ', array_flatten($v));
      else if ($k == 'data') {
        $html .= html_attributes(array_prefix_key($v, 'data-'));
        continue;
      }else throw InvalidArgumentException('html_attributes can only have array value for class or data');
    }else if ($v === NULL) {
      continue;
    }
    $html .= ' '.HTML($k).'="'.HTML($v).'"';
  }
  return $html;
}

