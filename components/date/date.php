<?php
namespace Date;

// Exceptions
class InvalidDate extends \Exception {
}

# Date
# =============
# The Date class encapsulates a date and a time
#
class Date implements \Nullable {
	private $_second, $_minute, $_hour, $_day, $_month, $_year, $_timezone, $_immutable=False;

  # date_offset
  # -----------
  # The [Date] class allows a manual time offset in seconds, which is set through the 'date_offset' constant definition, normally in config.php
  #
  # Flags
  # -------
  #
  # [PUSHBACK_DATE]: This flag to the [$date->add()] method allows month arithmetic, by equating the last day of the old month with the last day of the new month. 
  const PUSHBACK_DATE = 1;

  # link:__construct
  # def: Date(array $data)
  # param: $data: A complete set of property fields for this Date object.
  # 
  # The constructor should not be called externally.
  # 
  # See [Date::load()], [Date::ymd()], [Date::yd()], [Date::set()]
  # 
	function __construct($data) {
    $this->_timezone = date_default_timezone_get();
		foreach ($data as $k=>$v) {
			$k = "_$k";
			$this->$k = $v;
		}
	}

  static function set_timezone($timezone) {
    date_default_timezone_set($timezone);
  }

  # Creating Date Objects
  # ------------------------

  # link: now
  # def: Date Date::now()
  # usage: $date = Date::now();
  #
  # Returns a new [Date] object representing the current time.
	static function now() {
    return self::timestamp(time() + DEF('date_offset', 0));
	}

  # link:today, yesterday, tommorow
  # def: Date Date::today()
  # cdef: Date Date::yesterday()
  # cdef: Date Date::tommorow()
  # usage: $today = Date::today();
  # 
  # Return a new [Date] object representing midnight of the current day.
  # 
  static function today() {
    $d = static::now();
    $d->seconds = 0;
    return $d;
  }

  static function yesterday() {
    $d = static::today();
    $d->day--;
    return $d;
  }

  static function tommorow() {
    $d = static::today();
    $d->day++;
    return $d;
  }

  static function this_month() {
    $d = static::today();
    $d->day = 1;
    return $d;
  }

  # link:from_string
  # def: Date Date::from_string(string $s)
  # param: $s: A string in the format of [strtotime()]
  # usage: $yesterday = Date::from_string("yesterday");
  # usage: $next_monday = Date::from_string("next monday");
  # 
  # Return a new [Date] object corresponding to the given string, relative to the current time.
  # 
	static function from_string($s) {
		return static::timestamp(strtotime($s, static::now()->timestamp));
	}

  # link:mysql
  # def:Date Date::mysql(string $s)
  # param: $s: A MySQL-style timestamp
  # 
  # Instantiate a Date object from the given string and return it.
  # 
	static function mysql($s) { // can just use strtotime on mysql dates
		return static::timestamp(strtotime($s));
	}

  # link:load
  # def: Date Date::load($d) 
  # 
  # param: $d: A [Date] object, int number of seconds since the epoch or [strtotime()] string.
  # usage: $date = Date::load($seconds_since_epoch);
  # usage: $date_clone = Date::load($date);
  # usage: $yesterday = Date::load("yesterday");
  # Instantiate a new [Date] object from [$d] and return it, or return [NULL] if [$d] evaluates to [NULL].
  # 
	static function load($d) {
		if (!$d) return NULL;
		if ($d instanceof Date) return static::timestamp($d->timestamp);
		if (is_numeric($d)) return static::timestamp($d);
		return static::from_string($d);
	}
  # link:ymd
  # def: Date Date::ymd(int $y[, int $m = 1[, int $d = 1[,int $h = 0[, int $i = 0[, int $s = 0]]]]])
  # param: $y: Year
  # param: $m: Month, defaults to 1 ("January")
  # param: $d: Day of the month, defaults to 1 (the first)
  # param: $h: Hour, defaults to 0
  # param: $i: Minute, defaults to 0
  # param: $s: Second, defaults to 0
  # usage: $date = Date::ymd(2010, 5, 24);
  # usage: $twentyten = Date::ymd(2010);
  # 
  # Create a new [Date] object representing the given year, month and date. The hour, minute and second may also be specified. The month and date are 1-indexed.
  # 
	static function ymd($y,$m=1,$d=1,$h=0,$i=0,$s=0) {
		$data = array();
		list($data['year'], $data['month'], $data['day'], $data['hour'], $data['minute'], $data['second']) = array($y,$m,$d,$h,$i,$s);
		return new Date($data);
	}

  # link:yd
  # def: Date Date::yd(int $y[, int $d = 1[, int $h = 0[, int $i = 0[, int $s = 0]]]])
  # param: $y: Year
  # param: $d: Day of the year, from 1 to 365, or 366 for a leap year. Defaults to 1 (first of January).
  # param: $h: Hour, defaults to 0
  # param: $i: Minute, defaults to 0
  # param: $s: Second, defaults to 0
  # 
  # Create a new [Date] object representing the given 1-indexed day of the year for the given year.
  # 
	static function yd($y,$d=1,$h=0,$i=0,$s=0) {
		$data = array();
		list($data['year'], $data['month'], $data['day'], $data['hour'], $data['minute'], $data['second']) = array($y,1,1,$h,$i,$s);
		$date = new Date($data);
		// Push the day to $d
		$date->day = $d;
		return $date;
	}

  # link:timestamp
  # def: Date Date::timestamp(int $ts)
  # usage: $date = Date::timestamp($seconds_since_epoch)
  # 
  # Return a new [Date] representing the given number of seconds since the epoch.
  # 
	static function timestamp($ts) {
    if ($ts <= 0) {
      $data = array('year'=>0, 'month'=>0, 'day'=>0, 'hour'=>0, 'minute'=>0, 'second'=>0);
    }else{
      list($data['second'],$data['minute'],$data['hour'],$data['day'],$data['month'], $data['year']) = localtime($ts);
      // localtime() gives years from 1900
      $data['year'] += 1900;
      // month attribute is 1-indexed
      $data['month']++;
    }

		$class = get_called_class();
		return new $class($data);
	}

  # Null dates
  # -------------
  #
  # A null date has a value of 0000-00-00 00:00:00
  #
  # link: isNull
  # def: bool $date->isNull()
  #
  # Returns whether or not a given date is null
  #
  function isNull() {
    return $this->_year == 0 && $this->_month == 0 && $this->_day == 0 && $this->_hour == 0 && $this->_minute == 0 && $this->_second == 0;
  }

  # Date manipulation/string methods
  # ---------------------------------
  
  # link:delta_text
  # def: string Date::delta_text(int $delta[, bool $full = False[, bool $astext = False]])
  # param: $delta: Number of seconds to represent
  # param: $full: Whether the result is approximate, or precise and longer
  # param: $astext: Whether the numbers are written out as text or as digits
  # 
  # Returns a string representing the given number of seconds in human-readable denominations, from decade to second. Commonly used to indicate the time difference between two [Date] objects.
  # 
	static function delta_text($delta,$full = 0, $astext=False, $_and=False) {
		$pds = array('second','minute','hour','day','week','month','year','decade');
		$lngh = array(1,60,3600,86400,604800,2630880,31570560,315705600); // period lengths
		// Go backwards through different period lengths, until finding one with more than '1 unit' left, or we're left with seconds
		for($v = sizeof($lngh)-1; $v>0; $v--) {
			if ($lngh[$v]*2 <= $delta) break;
		}

		$no = floor($delta/$lngh[$v]);
		// 
		$no = floor($no); if($no <> 1) $pds[$v] .='s'; $x=sprintf("%s %s",($astext?number2text($no):$no),$pds[$v]);
		// Remove this from the delta
		$delta = $delta%$lngh[$v];
		if($full && $v>0 && $delta>0) $x .= ', '.strtolower(static::delta_text($delta,$full,$astext, True));
		else if ($_and) $x = "and $x";
		return $x;
	}

  # link:is_leapyear
  # def: bool Date::is_leapyear(int $y)
  # param: $y: Year
  # 
  # Calculate whether the given year is a leap year. Return [True] if it is; [False] otherwise.
  # 
	static function is_leapyear($y) {
		return (($y % 4 == 0) and ($y % 100 != 0)) or ($y % 400 == 0);
	}

  # link:days_in_month
  # def: int Date::days_in_month(int $m[, bool $is_leapyear = False])
  # param: $m: Month, 1-indexed
  # param: $isleapyear: [True] if the year is a leap-year; [False] otherwise (default).
  #  
  # Calculate and return the number of days in the given month, taking leap-years into account.
	static function days_in_month($m,$is_leapyear=0) {
		if ($is_leapyear>1) $is_leapyear=static::is_leapyear($is_leapyear); // passed year rather than bool
		if (is_array($m)) return static::days_in_month($m[1],static::is_leapyear($m[0]));
		return ($m==2?28+$is_leapyear:($m<8?30+($m&1):31-($m&1))); // neat days in month algorithm
	}



  # Comparing dates
  # -----------------
  #

  # link:cmp
  # def: int $date->cmp(Date $other_date)
  # param: $other_date: A Date object
  # usage: $difference_in_seconds = Date::now()->cmp(Date::from_string("next monday"));
  # 
  # Return the difference in time, in seconds, between the two Date objects. Positive if [$date] is after [$other_date]; negative if [$date] is before [$other_date]; 0 if they are equivalent.
  # 
  // < 0 if before
	function cmp_date($date) {
		if (!$date instanceof Date) $date = Date::load($date);
    return $this->format('Ymd') - $date->format('Ymd');
	}

	// < 0 if before
	function cmp($date) {
		if (!$date instanceof Date) $date = Date::load($date);
    return $this->timestamp - $date->timestamp;
	}
  # link:before
  # def: bool $date->before($other_date)
  # param: $other_date: A Date object, int seconds since the epoch or [strtotime()] string.
  # 
  # Returns [True] if [$date] is before [$other_date] and [False] otherwise.
  # 
	function before($date) {
		return $this->cmp($date) < 0;
	}
	function before_date($date) {
		return $this->cmp_date($date) < 0;
	}
  # link:after
  # def: bool $date->after($other_date)
  # param: $other_date: A Date object, int seconds since the epoch or [strtotime()] string.
  # 
  # Returns [True] if [$date] is after [$other_date] and [False] otherwise.
  # 
	function after($date) {
		return $this->cmp($date) > 0;
	}
	function after_date($date) {
		return $this->cmp_date($date) > 0;
	}

  # link:eq
  # def: bool $date->eq($other_date)
  # param: $other_date: A Date object, int seconds since the epoch or [strtotime()] string.
  # 
  # Returns [True] if the two Date objects represent the same second in time, and [False] otherwise.
  # 
  # ----------------------
  # link:eq_date
  # def: bool $date->eq_date($other_date)
  # param: $other_date: A Date object, int seconds since the epoch or [strtotime()] string.
  # 
  # Returns [True] if the two Date objects represent the same date, and [False] otherwise. The hour, minute and second fields are ignored.
  # 
	function eq($date) {
		return ($this->cmp($date) == 0);
	}
	function eq_date($date) {
		$date = Date::load($date);
		return $this->year == $date->year && $this->month == $date->month && $this->day == $date->day;
	}

  # Static date comparisons
  # -----------------------
  #
  # link: min max
  # def: Date Date::min(Date $date1, Date $date2, ...)
  # cdef: Date Date::max(Date $date1, Date $date2, ...)
  # param: Accepts any number of Date object parameters  
  # usage: $earliest = Date::min($birthday, $christmas);
  # usage: $latest = Date::max($birthday, $christmas);
  # 
  # Returns the Date object which is the earliest/last of the Date objects given, or [NULL] if no arguments are given. 
	static function min() {
		$min = NULL;
		foreach (func_get_args() as $d) {
      if ($d === NULL) continue;
			$d = static::load($d);
			if (!$min) $min = $d;
			else if ($d && $min->after($d)) $min = $d;
		}
		return $min;
	}
	static function max() {
		$max = NULL;
		foreach (func_get_args() as $d) {
      if ($d === NULL) continue;
			$d = static::load($d);
			if (!$max) $max = $d;
			else if ($d && $max->before($d)) $max = $d;
		}
		return $max;
	}


  function daysAgo() {
    return floor($this->secondsUntil(Date::now()) / (60*60*24));
  }
  function daysUntil() {
    return ceil(Date::now()->secondsUntil($this) / (60*60*24));
  }

  function secondsUntil($next) {
		if (!$next instanceof Date) $next = Date::load($next);

    // Return 0 if the timestamp is negative
    return max(0, $next->timestamp - $this->timestamp);
  }

  # Manipulating Date objects
  # ----------------------
  # link: add subtract
  # def: Date $date->add(int $amount, string $to[, $flags = 0])
  # cdef: Date $date->subtract(int $amount, string $to[, $flags = 0])
  # param: $amount: The number of units to add to the given property.
  # param: $to: 'year', 'month', 'day', 'hour', 'minute' or 'second'
  # param: $flags: Specify 1 to enable month addition. Default '0'.
  # 
  # The given amount is added to the specified property of the [Date] object. Adding to the 'month' property is disabled by default, since the last day of the month may be adjusted. The [Date] is returned for command chaining.
  
  function add($amount, $to, $flags=0) {
    // Special safe add for month
    if ($to == 'month' && ($flags & Date::PUSHBACK_DATE)) {
      $d = $this->day;
      $this->day = 1;
      $this->month += $amount;
      // last day may not exist in the new month. find new last day
      $this->day = min($d, $this->mdays);
    }elseif ($to == 'year') {
      return $this->add($amount*12, 'month', $flags);
    }else{
      $this->$to += $amount;
    }
		return $this;
	}

	function subtract($amount, $to, $flags=0) {
    return $this->add(-$amount, $to, $flags);
	}

  function previousDay() {
    $next = Date::load($this);
    $next->seconds = 0; $next->day--;
    return $next;
  }
  # link:nextDay
  # def: Date $date->nextDay()
  # 
  # Return a new Date object representing the day following this Date object, at midnight.
  # 
  function nextDay() {
    $next = Date::load($this);
    $next->seconds = 0; $next->day++;
    return $next;
  }

  # link:set
  # def: Date $date->set($d)
  # param: $d: [Date] object, int number of seconds since the epoch or [strtotime()] format string or array of property => value pairs.
  # 
  # Mutate the Date object so that its properties are equal to those indicated by the given parameter. In the case of a Date object being supplied, the private properties are copied directly across. In the case of an array, an incomplete array may be passed, sine [__set()] will calculate the correct values for the magical properties.
  # 
	function set($date) {
		if (is_array($date)) {
			foreach ($date as $k=>$v) $this->$k = $v;
		}else{
			$date = Date::load($date);
      // copy the private Date properties into this object directly
			foreach ($date as $k => $v) {
				$this->$k = $v;
			}
		}
		return $this;
	}

  # Office hours
  # ----------------------
  # link:nextWorkTime
  # def: Date $date->nextWorkTime()
  # 
  # Returns a new Date object representing the next working time. Office hours are defined as 9am to 5pm, Monday to Friday.
  function nextWorkTime() {
    $work = Date::load($this);
    if ($work->hour < 9) {
      $work->seconds = 9*3600;
    }else if ($work->seconds > 16*3600) {
      $work->seconds = 9*3600;
      $work->day++;
    }
    while (!$work->isWorkTime()) {
      $work->seconds = 9*3600;
      $work->day++;
    }
    return $work;
  }

  # link:isWorkTime
  # def: bool $date->isWorkTime()
  # 
  # Returns [True] if this Date object represents a time within office hours. Office hours are defined as 9am to 5pm, Monday to Friday.
  # 
  function isWorkTime() {
    // Monday => Friday
    if ($this->weekday >= 1 && $this->weekday <= 5) {
      if ($this->seconds >= 9*3600 && $this->seconds <= 16*3600) {
        return True;
      }
    } 
    return False;
  }

  /***
   * Update the objects timezone to a given zone
   * @internal
   */
  private function updateTimezone() {
    $timezone = date_default_timezone_get();
    if ($this->_timezone == $timezone) return;

    // Null times stay as they are
    if (!$this->isNull()) {
      $datetime = new DateTime("$this->_year-$this->_month-$this->_day $this->_hour:$this->_minute:$this->_second", new DateTimeZone($this->_timezone));
      $datetime->setTimezone(new DateTimeZone($timezone));
      list($this->_year, $this->_month, $this->_day, $this->_hour, $this->_minute, $this->_second) = 
        explode(',', $datetime->format('Y,m,d,H,i,s'));
    }

    $this->_timezone = $timezone;
  }

  # Magical properties
  # 
  # These properties are defined through [__get()] and [__set()].
  # 
  # h3: Main properties
  # 
  # These properties define the date and time indicated by this option. They may be read to and written from. Writing
  # to one of these properties will cause the others to change accordingly.
  # 
  # link:main_properties
  # def: int $date->year
  # def: int $date->month
  # 
  # The month is 1-indexed.
  # 
  # def: int $date->day
  # 
  # The day of the week is 1-indexed.
  # 
  # def: int $date->hour
  # def: int $date->minute
  # def: int $date->second
  # 
  # def: int $date->seconds
  # 
  # Number of seconds since the previous day.
  # 
  # h3: Read-only properties
  # 
  # ----------------------
  # link:leapyear
  # def: bool $date->leapyear
  # usage: $is_leap_year = Date::ymd(2000)->leapyear;
  # 
  # Calculate whether the year represented by this [Date] object is a leap year. This value is read-only.
  # 
  # ----------------------
  # link:mdays
  # def: int $date->mdays
  # usage: $days_in_month = Date::ymd(2011, 02)->mdays;
  # 
  # Calculate the number of days in the month represented by the [Date] object. Leap years are taken into account. This property is read-only.
  # 
  # ----------------------
  # link:days
  # def: int $date->days
  # 
  # Calculate the day of the year represented by this [Date] object. This property is read-only. See [Date::yd()]
  # 
  # ----------------------
  # link:weekday
  # def: string $date->weekday
  # 
  # Return the string name of the day of the week represented by this [Date] object. Read-only.
  # 
  # ----------------------
  # link:week
  # def: int $date->week
  # 
  # Calculate and return the number of the week represented by this [Date] object. Read-only.
  # 
  # ----------------------
  # link:week_start
  # def: int $date->week_start
  # 
  # Return a new [Date] object representing the first day of the week represented by this [Date] object. Read-only. 
  # 
  # ----------------------
  # link:ym
  # def: string $date->week_end
  # 
  # Return a new [Date] object representing the last day of the week represented by this [Date] object. Read-only.
  # 
  # ----------------------
  # link: ym
  # def: string $date->ym
  # 
  # Alias for $date->format('Y-m')
  # 
  # ----------------------
  # link: ymd
  # def: string $date->ymd
  # 
  # Alias for $date->format('Y-m-d')
  # 
  # ----------------------
  # 
  # link: ymdhis
  # def: string $date->ymdhis
  # 
  # Alias for $date->format("Y-m-d H:i:s")
  # 
  # ----------------------
  # link: timestamp
  # def: string $date->timestamp
  # 
  # Return the number of seconds which this [Date] represents since the epoch.
  # 
  # ----------------------
  # link: mysql
  # def: string $date->mysql
  # 
  # Return [$date->ymdhis] enclosed in double-quotation marks (").
  # 
  # ----------------------
  # link: month_name
  # def: string $date->month_name
  # 
  # Return the name of the month represented by this [Date] object.
  # 
  # ----------------------
  # link: seconds
  # def: int $date->seconds
  # 
  # Return the number of seconds since the middle of the previous night.

	function __get($var) {
    // Automatically update timezone if needed
    $this->updateTimezone();

		switch ($var) {
		case 'leapyear':		return self::is_leapyear($this->_year);
		case 'mdays':				return self::days_in_month($this->_month, $this->leapyear);
		case 'days':
			$days = 0;
			for ($k = 1; $k < $this->_month; $k++) $days += self::days_in_month($k, $this->_year);
			return $days + $this->_day;

		case 'weekday':
			return INT($this->format('w')); // 0 = Sunday, 6 = Saturday

		case 'week':
			$d = Date::load($this);
			$d->month = 1; $d->day = 1;
			$first_week = 1 - $d->weekday; // When did the first week start (1=1st, -1=2nd last day of december last year)
			return INT(($this->days - $first_week) / 7) + 1;

		case 'week_start':
			$d = Date::load($this);
			$d->day -= $this->weekday;
			return $d;

		case 'week_end':
			$d = Date::load($this);
			$d->day += 6-$this->weekday;
			return $d;

		case 'ym':
			return $this->format('Y-m');

		case 'ymd':
			return $this->format('Y-m-d');

		case 'ymdhis':
			return $this->format('Y-m-d H:i:s');

    // Neat date format
    case 'neat':
      return $this->format('F jS');

		case 'timestamp':
			return strtotime("$this->_year-$this->_month-$this->_day $this->_hour:$this->_minute:$this->_second");

		// Special
		case 'mysql':     return "\"$this->ymdhis\"";
		case 'mysql_ymd': return "\"$this->ymd\"";
    case 'js': return "Date.UTC($this->_year, ".($this->_month-1).", $this->_day, $this->_hour, $this->_minute, $this->_second)";

		// Language
		case 'month_name':
			$months = array('January','February','March','April','May','June','July','August','September','October','November','December');
			return $months[$this->_month-1];

		case 'seconds':
			return $this->_hour * 3600 + $this->_minute * 60 + $this->_second;

    case 'when_short':
      return $this->whenShort();

		// Standard units
		case 'second':			return $this->_second;
		case 'minute':			return $this->_minute;
		case 'hour':				return $this->_hour;
		case 'day':					return $this->_day;
		case 'month':				return $this->_month;
		case 'year':				return $this->_year;
		case 'timezone':		return $this->_timezone;
		}
    throw new MissingVariableException($var, 'Date');
	}

  function __isset($var) {
    try {
      $this->__get($var);
      return True;
    } catch (MissingVariableException $e) {
      return False;
    }
  }

	function __toString() {
		return $this->format('Y-m-d H:i:s') ?: '0000-00-00 00:00:00';
	}

	function __set($var, $val) {
		if ($this->_immutable) throw new FatalException();
		switch ($var) {
		case 'second':
			$val = intval($val);

			if ($val < 0) {
        $minutes = intval((-$val) / 60 + 1);
        $this->minute -= $minutes;
        $val += 60 * $minutes;
      }
      if ($val >= 60) {
        $minutes = intval($val / 60);
        $this->minute += $minutes;
        $val -= $minutes * 60;
			}

      $this->_second = $val;
			break;
		case 'minute':
			$val = intval($val);

			if ($val < 0) {
        $hours = intval((-$val) / 60 + 1);
        $this->hour -= $hours;
        $val += 60 * $hours;
      }
      if ($val >= 60) {
        $hours = intval($val / 60);
        $this->hour += $hours;
        $val -= $hours * 60;
			}

      $this->_minute = $val;
			break;
		case 'hour':
			$val = intval($val);

			if ($val < 0) {
        $days = intval((-$val) / 24 + 1);
        $this->day -= $days;
        $val += 24 * $days;
      }
      if ($val >= 24) {
        $days = intval($val / 24);
        $this->day += $days;
        $val -= $days * 24;
			}

      $this->_hour = $val;
			break;
		case 'day':
			$val = intval($val);

			// Set the day to 1 while calculating correct months to avoid odd effects (31 feb!?)
			$this->_day = 1;

			while ($val < 1) {
				// Deal with negative days
				$this->month--;
				$val += $this->mdays;
			}

			while ($val > $this->mdays) {
				$val -= $this->mdays;
				$this->month++;
			}

			$this->_day = $val;
			break;
		case 'month':
			$val = intval($val);

			while ($val < 1) {
				$val += 12;
				$this->_year--;
			}
			while ($val > 12) {
				$val -= 12;
				$this->_year++;
			}
			$this->_month = $val;

			if ($this->_day > $this->mdays) {
				// Uh-oh, day doesnt exist this month
				throw new InvalidDate();
			}
			break;
		case 'year':
			$this->_year = intval($val);

			if ($this->_day > $this->mdays) {
				// Uh-oh, day doesnt exist this year (non leap year?)
				throw new InvalidDate();
			}
			break;

		case 'seconds':
			$this->_hour = intval($val/3600);
			$this->_minute = intval($val/60)%60;
			$this->_second = intval($val)%60;
			break;
		}
	}

  # link: format
  # def: string $date->format($format)
  # param: $format [date()]-style format used to produce the string
  # 
  # Invokes the [date()] function on this [Date] object with the given format string and returns the output. 
  # 
	function format($format) {
    if ($this->isNull()) return NULL;
		return date($format, $this->timestamp);
	}

  # link:about_time_ago
  # def: string $date->about_time_ago()
  # 
  # Returns a Facebook-style approximation of the difference in time between the [Date] object and the current time.
  # 
	function aboutTimeAgo() {
		$now = static::now();

		$diff = $now->timestamp - $this->timestamp;

    if ($diff < 0) { return 'in the future'; }
    elseif ($diff < 2) return 'a second ago';
		else if ($diff < 10) return "$diff seconds ago";
		elseif ($diff <= 90) {
			$diff = max(5,$diff-$diff%5);
			return "about $diff seconds ago";
		}else if ($diff < 27*60) {
			$diff = max(2,round($diff/60));
			return "about $diff minutes ago";
		}else if ($diff < 33*60) {
			return 'about half an hour ago';
		}else if ($now->_year == $this->_year && $now->_month == $this->month) {
			// TODO: Check for end of year rounding

			$ddiff = $now->days - $this->days;
			if ($ddiff == 0) {
				return date('\a\t h:ia',$this->timestamp);
			}else if ($ddiff == 1) {
				return date('\y\e\s\t\e\r\d\a\y \a\t g:ia',$this->timestamp);
			}else if ($ddiff < 7) {
				return date('l \a\t g:ia',$this->timestamp);
			}else if ($ddiff < 13) {
				return date('\l\a\s\t l \(\t\h\e jS\) \a\t g:ia',$this->timestamp);
			}
		}

		// Easiest to work with timestamps

		return date('\o\n F jS'.($now->_year==$this->_year?'':' Y').', g:ia', $this->timestamp);
		//return 'a while ago';
	}


  function timeAgo() {
    $result = $this->aboutTimeAgo();

    if (substr($result,0,3) == 'on ') $result = substr($result,3);
    if (substr($result,0,3) == 'at ') $result = substr($result,3);
    if (substr($result,0,6) == 'about ') $result = substr($result,6);

    return ucfirst($result);
  }
  /**
   * Show time if today. Show short date if not today. Show year if not this year
   */
  public function whenShort() {
    $today = Date::today();
    if ($this->eq_date($today)) {
      return $this->format("H:ia");
    } else if ($this->year == $today->year) {
      return $this->format("M j");
    } else {
      return $this->format("M j Y");
    }
  }

};

###
# Immutable
class ImmutableDate extends Date {
	function __set($var, $val) { throw new FatalException('Date is immutable', array('date'=>$this)); }
}

