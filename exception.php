<?php

class ContextException extends Exception {
  protected $context = null;

  function __construct($message='', $context=array(), $code=0) {
    parent::__construct($message);
    $this->context = $context;
  }

  function getContext($key=NULL) {
    if ($key !== NULL) return isset($this->context[$key]) ? $this->context[$key] : NULL;
    else return $this->context;
  }
}

class UndefinedPropertyException extends Exception {
  function __construct($object, $name) {
    parent::__construct("Undefined property '$name' of ".get_class($object));
  }
}
