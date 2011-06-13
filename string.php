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

