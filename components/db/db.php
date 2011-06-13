<?php

namespace DB;

require dirname(__FILE__).'/results.php';
require dirname(__FILE__).'/connection.php';

class Exception extends \Exception {}

/***
 * Simple names for database exceptions
 **/
class DuplicateException extends Exception {}
class LockTimeoutException extends Exception {}

/***
 * Non-database exceptiotns
 **/
class DroppedTransactionException extends Exception {}
class UnrecognisedValueException extends Exception {}
class ConnectErrorException extends Exception {}
class FormatException extends Exception {}

