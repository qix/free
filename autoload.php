<?php

class Autoload {
  static $paths = array();

  static function addPath($path) {
    self::$paths[] = $path.'/';
  }

  static function tryLoad($extension) {
    $extension = implode('/', $extension);

    foreach (self::$paths as $path) {
      if (file_exists($path.$extension)) {
        require_once $path.$extension;
        return True;
      }
    }
    return False;
  }
  static function load($class) {
    if (is_array($class)) $split = $class;
    else $split = explode('_', strtolower(str_replace('\\', '_', $class)));
    $final = array_pop($split);

    if (self::tryLoad(array_merge($split, array($final, $final.'.php')))) {
      return True;
    }

    if (self::tryLoad(array_merge($split, array($final.'.php')))) {
      return True;
    }

    if ($split) {
      return self::load($split);
    }

    return False;
  }

  static function preload($classes) {
    foreach ($classes as $class) {
      if (!self::load($class)) {
        throw new Exception('Could not preload requested class: '.$class);
      }
    }
  }
}

spl_autoload_register(array('Autoload', 'load'));
