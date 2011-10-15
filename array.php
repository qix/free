<?php

/***
 * Handy functions to retrieve value from array (or default value)
 **/
function ARR($arr,$var=NULL,$alt=NULL) {
  if (is_array($var)) {
    $res = array();
    foreach ($var as $c => $v) {
      if (is_int($c)) $res[$v] = ARR($arr,$v,NULL);
      else $res[$c] = ARR($arr,$c,$v);
    }
    return $res;
  }
  
  if (is_object($arr)) {
    return property_exists($arr, $var) || isset($arr->$var) ? $arr->$var : $alt;
  }elseif ($arr && array_key_exists($var, $arr)) {
    return $arr[$var];
  }else{
    // Check array before matching for brackets (only if it looks like its possible
    if ($var && $var[strlen($var)-1] == ']') { 
      if (preg_match('/(.*)?\[(.*)\]$/',$var,$m)) {
        // Array search
        $sub = ARR($arr,$m[1]);
        if (!is_array($sub)) return $alt;
        if ($m[2] === '') {
          // Empty [], returns the entire array
          return $sub;
        }else{
          return ARR($sub,$m[2],$alt);
        }
      }
    }
    return $alt;
  }
}
function DEF($var,$alt=NULL) {
  return (defined($var))?constant($var):$alt;
}

function REQ($var,$alt=NULL) { return ARR($_REQUEST,$var,$alt); }
function POST($var,$alt=NULL) { return ARR($_POST,$var,$alt); }
function GET($var,$alt=NULL) { return ARR($_GET,$var,$alt); }

/***
 * Additional array methods on top of php's set
 **/

function array_first_key($array) {
  reset($array);
  return key($array);
}
function array_last_key($array) {
  end($array);
  return key($array);
}

/* @todo refactor return references out (confusing) */
function &array_first(&$array) {
  reset($array);
  return $array[key($array)];
}
function &array_last(&$array) {
  end($array);
  return $array[key($array)];
}

function options($in, $defaults) {
  $out = array();
  if (!is_array($in)) $in = array($in);


  foreach ($in as $key=>$value) {
    if (is_numeric($key)) {
      foreach ($defaults as $k=>$v) {
        if (is_numeric($k)) { // 0 => 'key'
          if (!isset($out[$v])) {
            $out[$v] = $value;
            break;
          }
        }else{ // 'key' => 'string'
          if (!isset($out[$k]) && gettype($value) == $v) {
            $out[$k] = $value;
            break;
          }
        }
      }
    }else $out[$key] = $value;
  }

  return $out;
}

// parse data-xxy => $val; to xxy => $val
function data_prefix(&$data, $prefix, $strip = True, $remove = False) {
  $return = array();
  $L = strlen($prefix);
  foreach ($data as $k => $v) {
    if (substr($k, 0, $L) === $prefix) {
      $return[$strip ? substr($k,$L) : $k] = $v;
      if ($remove) unset($data[$k]);
    }
  }
  return $return;
}

/***
  * Extracts a given member from every object in an array
  **/
function array_map_member($member, $array) {
  return array_map(
    function($o) use ($member) { return $o->$member; },
    $array
  );
}

/***
  * Flattens a multidimensional array into a flat singe dimensional array
  **/
function array_flatten($array) {
  $flat = array();
  foreach ($array as $value) {
    if (is_array($value)) $flat = array_merge($flat, array_flatten($value));
    else array_push($flat, $value);
  }
  return $flat;
}

/***
  * Filters an array by key instead of value
  **/
function array_filter_key($input, $callback) {
  $return = array();
  foreach ($input as $k=>$v) {
    if ($callback($k)) {
      $return[$k] = $v;
    }
  }
  return $return;
}

/***
  * Adds a prefix to each key for new $prefix.$key => $value
  **/
function array_prefix_key($data, $prefix) {
  $return = array();
  foreach ($data as $k=>$v) {
    $return[$prefix.$k] = $v;
  }
  return $return;
}

/***
 * Checks if an array is standard (as per normal arrays)
 **/
function array_is_standard($array) {
  $index = 0;
  foreach ($array as $k => $v) {
    if ($k !== $index++) return False;
  }
  return True;
}

/***
  * Maps an array to a new set of keys by array/function
  **/
function array_map_keys($array, $fn) {
  $output = array();
  if (is_array($fn)) {
    $keys = $fn;
    $fn = function ($key) use ($keys) {
      if (isset($keys[$key])) return $keys[$key];
      else return NULL;
    };
  }

  foreach ($array as $key => $value) {
    $key = $fn($key);
    if ($key !== NULL) {
      $output[$key] = $value;
    }
  }

  return $output;
}

/***
 * Map both key and values
 **/
function array_map_kv($fn, $array) {
  return array_map($fn, array_keys($array), array_values($array));
}

/***
  * Combines an array with itself for $value => $value
  **/
function array_combine_same($input) {
  return array_combine($input, $input);
}

/***
 * Convert an array into <option> html tags
 **/
function html_options($options, $default=NULL, $multiple=False) {
  $output = '';
  $group = NULL;
  foreach ($options as $key => $option) {
    $option = options($option, array('caption', 'group'));
    $option['key'] = ARR($option, 'key', $key);

    if (($g = ARR($option, 'group')) && ($g != $group)) {
      if ($group) $output .= '</optgroup>';
      $output .= '<optgroup label="'.HTML($g).'">';
      $group = $g;
    }
    $value = ARR($option, 'key');
    // is this option selected?
    $selected = NULL;
    if ($multiple) {
      $selected = $default && in_array($value, $default);  
    } else {
      $selected = $default == $value;
    }
    $output .= '<option'.html_attributes(array(
      'value'=>$value,
      'data'=>ARR($option, 'data'),
      'selected'=>$selected?'selected':NULL
    )).'>'.HTML(ARR($option,'caption')).'</option>';
  }
  if ($group) $output .= '</optgroup>';

  return $output;
}

