<?php
/**
  * @param $string Haystack
  * @param $start Needle
  * @return boolean Whether $string begins with $start, strict
  */
function starts_with($string, $start) {
  return substr($string,0,strlen($start)) === $start;
}
/**
  * @param $string Haystack
  * @param $end Needle
  * @return boolean Whether $string ends with $start, strict
  */
function ends_with($string, $end) {
  return substr($string, -strlen($end)) === $end;
}

/**
 * Simple english language tools
 **/
function bytesize($sz) {
  foreach (array('b', 'kb', 'mb') as $suffix) {
    if ($sz < 900) return round($sz).$suffix;
    $sz /= 1024;
  }
  return round($sz).'gb';
}
