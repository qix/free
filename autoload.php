<?php

class Autoload {
  static $paths = array();

  static function addPath($path, $split=array('\\', '_')) {
    self::$paths[] = array($path.'/', $split);
  }

  static function testFile($path, $extension) {
    $extension = implode('/', $extension);
    if (file_exists($path.$extension)) {
      return $path.$extension;
    }
    return NULL;
  }

  static function testPath($path, $parts) {
    $final = array_pop($parts);

    if ($result = self::testFile($path, array_merge($parts, array($final, $final.'.php')))) {
      return $result;
    }

    if ($result = self::testFile($path, array_merge($parts, array($final.'.php')))) {
      return $result;
    }

    if ($parts) {
      return self::testPath($path, $parts);
    }
  }

  static function findPath($class) {
    foreach (self::$paths as $path) {
      if (is_array($class)) {
        $parts = $class;
      }else{
        $parts = explode('\\', strtolower(str_replace($path[1], '\\', $class)));
      }

      if ($result = self::testPath($path[0], $parts)) {
        return $result;
      }
    }
    return NULL;
  }

  static function load($class) {
    if ($path = self::findPath($class)) {
      require_once $path;
      return True;
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
