<?php
namespace SeanMorris\Ids;

class Log
{
	const SECOND_SIGNIFICANCE	= 5
		, TEXT_COLOR			= 'none'
		, TEXT_BACKGROUND		= 'none'
		, HEAD_COLOR			= 'darkGrey'
		, HEAD_BACKGROUND		= 'none'
		, DATA_COLOR			= 'green'
		, DATA_BACKGROUND		= 'black'
		, ERROR_COLOR			= 'black'
		, ERROR_BACKGROUND		= 'yellow'
		, LOG_SEPERATOR			= '########################################'
	;

	static $foregroundColors = array(
		'black'			=> '0;30'
		, 'darkGrey'	=> '1;30'
		, 'blue'		=> '0;34'	, 'lightBlue'	=> '1;34'
		, 'green'		=> '0;32'	, 'lightGreen'	=> '1;32'
		, 'cyan'		=> '0;36'	, 'lightCyan'	=> '1;36'
		, 'red'			=> '0;31'	, 'lightRed'	=> '1;31'
		, 'purple'		=> '0;35'	, 'lightPurple'	=> '1;35'
		, 'brown'		=> '0;33'
		, 'yellow'		=> '1;33'
		, 'lightGrey'	=> '0;37'
		, 'white'		=> '1;37'
	);

	static $backgroundColors = array(
		'black'			=> 40
		, 'red'			=> 41
		, 'green'		=> 42
		, 'yellow'		=> 43
		, 'blue'		=> 44
		, 'magenta'		=> 45
		, 'cyan'		=> 46
		, 'lightGrey'	=> 47
	);

	static $levels = array(
		'off'     => 0
		, 'error' => 1
		, 'warn'  => 2
		, 'info'  => 3
		, 'debug' => 4
		, 'query' => 5
		, 'trace' => 6
	);

	static $colors = [
		'key' => 'lightBlue'
		, 'keyBg' => NULL
		, 'type' => 'green'
		, 'typeBg' => NULL
		, 'value' => NULL
		, 'valueBg' => NULL
		, 'line' => NULL
		, 'lineBg' => NULL
	];

	static $levelColors = [
		'error'   => 'red'
		, 'warn'  => 'yellow'
		, 'info'  => 'white'
		, 'debug' => 'green'
		, 'query' => 'lightBlue'
	];

	protected static
		$started = false
		, $colorOutput = true
		, $suppress = false
	;

	public static function error(...$data)
	{
		static::log(__FUNCTION__, ...$data);
	}

	public static function warn(...$data)
	{
		static::log(__FUNCTION__, ...$data);
	}

	public static function info(...$data)
	{
		static::log(__FUNCTION__, ...$data);
	}

	public static function debug(...$data)
	{
		static::log(__FUNCTION__, ...$data);
	}

	public static function query(...$data)
	{
		$data = array_map(
			function($datum)
			{
				if(is_string($datum))
				{
					$datum = Log::color($datum, 'yellow');
				}
				return $datum;
			}
			, $data
		);
		static::log(__FUNCTION__, ...$data);
	}

	protected static function getColor($type)
	{
		if(isset(static::$colors[$type]))
		{
			return static::$colors[$type];
		}
	}

	protected static function log($levelString, ...$data)
	{
		global $switches;
		$output = null;

		$logPackages = (array)Settings::read('logPackages');
		$position    = (object) static::position(2);

		$logBlob = static::logBlob(0);

		$level = 0;

		if(isset(static::$levels[$levelString]))
		{
			$level = static::$levels[$levelString];
		}

		if(($level <= static::$levels['warn']
			&& php_sapi_name() == 'cli'
			&& ($switches['verbose'] ?? $switches['v'] ?? FALSE)
		) || (php_sapi_name() == 'cli'
			&& ($switches['vv'] ?? FALSE)
		)){
			foreach($data as $d)
			{
				if($d instanceof LogMeta)
				{
					continue;
				}
				if(is_scalar($d))
				{
					print $d . PHP_EOL;
					continue;
				}
				print_r($data);
			}
		}

		$maxLevel = NULL;

		if(is_array($logPackages))
		{
			foreach($logPackages as $regex => $logLevel)
			{
				if(!isset($position['class']))
				{
					break;
				}

				if(!preg_match("/$regex/", $position['class']))
				{
					continue;
				}

				if(isset(static::$levels[$logLevel]))
				{
					$maxLevel = static::$levels[$logLevel];
					break;
				}
			}
		}

		if($maxLevel === NULL)
		{
			$maxLevelString = Settings::read('logLevel');

			if(isset(static::$levels[$maxLevelString]))
			{
				$maxLevel = static::$levels[$maxLevelString];
			}
		}

		$_levelString = $levelString;

		if(isset(static::$levelColors[$levelString]))
		{
			$_levelString = static::color(
				$levelString
				, static::$levelColors[$levelString]
			);
		}

		if($level > $maxLevel || $level == 0)
		{
			return;
		}

		static::startLog($maxLevel);

		if($level == 1)
		{
			$colors['line'] = 'lightRed';
			$colors['lineBg'] = 'black';
		}

		if($level == 2)
		{
			$colors['line'] = 'black';
			$colors['lineBg'] = 'yellow';
		}

		$output = '';

		$output .= static::color(
			static::header($_levelString) . static::positionString(2)
			, static::HEAD_COLOR
			, static::HEAD_BACKGROUND
		);

		foreach($data as $datum)
		{
			if($datum instanceof LogMeta)
			{
				foreach($datum as $k => $v)
				{
					if(!is_scalar($v))
					{
						$v = static::dump($v, [], FALSE);
					}
					$logBlob->$k = $v;
				}
				continue;
			}
			if(is_scalar($datum))
			{
				$output .= static::color($datum, static::getColor('line'), static::getColor('lineBg'));
				$output .= PHP_EOL;
				continue;
			}

			$output .= static::dump($datum, [], static::$colors);
		}

		$logBlob->type          = $levelString;
		$logBlob->level         = $level;
		$logBlob->full_message  = $output;
		$logBlob->short_message = is_string($data[0])
			? $data[0]
			: strtok($output, "\n")
			. PHP_EOL
			. strtok("\n");

		if($loggers = Settings::read('loggers'))
		{
			foreach($loggers as $logger)
			{
				$logger::log($logBlob);
			}
		}

		file_put_contents(
			ini_get('error_log')
			, PHP_EOL . $output
			, FILE_APPEND
		);
	}

	protected static function logBlob($depth, $exception = false)
	{
		$position = static::position($depth + 2);

		$trace = $exception
			? $exception->getTrace()
			: (array_slice($position['backtrace'], $depth + 2) ?? []);

		$file = $exception
			? $exception->getFile()
			: ($position['file'] ?? NULL);

		$line = $exception
			? $exception->getLine()
			: ($position['line'] ?? NULL);

		$class = $exception
			? ($trace[0]['class'] ?? NULL)
			: ($position['class'] ?? NULL);

		$function = $exception
			? $trace[0]['function']
			: ($position['function'] ?? NULL);

		$logBlob  = (object) [
			'pid'        => getmypid()
			, 'rid'      => static::$started
			, 'file'     => $file
			, 'line'     => $line
			, 'class'    => $class
			, 'function' => $function
			, 'trace'    => static::renderTrace($trace)
			, 'depth'    => count($trace ?? [])
		];

		if(isset($_SERVER, $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']))
		{
			$logBlob->path   = $_SERVER['REQUEST_URI'];
			$logBlob->method = $_SERVER['REQUEST_METHOD'];
		}

		if(isset($_SERVER, $_SERVER['REMOTE_ADDR']))
		{
			$logBlob->from = $_SERVER['REMOTE_ADDR'];
		}

		if($_REQUEST)
		{
			$logBlob->_GET     = static::dump($_GET, [], FALSE);
			$logBlob->_POST    = static::dump($_POST, [], FALSE);
			$logBlob->_FILES   = static::dump($_FILES, [], FALSE);
			$logBlob->_REQUEST = static::dump($_REQUEST, [], FALSE);
		}

		return $logBlob;
	}

	protected static function startLog($maxLevel = 0)
	{
		if(!static::$started)
		{
			static::$started = uniqid();

			if(Settings::read('clearLogs'))
			{
				file_put_contents(ini_get('error_log'), ' ');
			}

			$path = NULL;

			if(isset($_SERVER['REQUEST_URI']))
			{
				$path = $_SERVER['REQUEST_URI'] . PHP_EOL;
			}

			if(isset($_SERVER['REQUEST_METHOD']))
			{
				$path = $_SERVER['REQUEST_METHOD'] . ':' . $path;
			}

			$request = NULL;

			if(count($_REQUEST) && $maxLevel >= 4)
			{
				$request = 'Request: ' . PHP_EOL
					. static::dump($_REQUEST, [], static::$colors)
					. ($_FILES ? 'Files: ' . static::dump($_FILES, [], static::$colors) : NULL)
				;
			}

			$from = NULL;

			if(isset($_SERVER, $_SERVER['REMOTE_ADDR']))
			{
				$from = 'From: ' . $_SERVER['REMOTE_ADDR'] . PHP_EOL;
			}

			$logBlob = static::logBlob(2);

			$logBlob->shortMessage = 'Request processing started.';

			if($loggers = Settings::read('loggers'))
			{
				foreach($loggers as $logger)
				{
					$logger::start($logBlob);
				}
			}

			$output = static::LOG_SEPERATOR . PHP_EOL . $path . $from . $request . PHP_EOL;

			file_put_contents(
				ini_get('error_log')
				, PHP_EOL . $output
				, FILE_APPEND
			);
		}
	}

	protected static function dump($val, $parents = [], $colors = [])
	{
		$indent = '  ';
		$output = '';

		$detectColors = [
			'key', 'keyBg'
			, 'type', 'typeBg'
			, 'value', 'valueBg'
		];

		if($colors !== FALSE)
		{
			foreach($detectColors as $color)
			{
				if(!isset($colors[$color]))
				{
					$colors[$color] = 'none';
				}
			}
		}


		if(!is_array($val) && !is_object($val))
		{
			$type = gettype($val);

			if($type == "string")
			{
				$output .= sprintf(
					'(%s) "%s"'
					, $colors
						? static::color($type, $colors['type'], $colors['typeBg'])
						: $type
					, $colors
						? static::color($val, $colors['value'], $colors['valueBg'])
						: $val
				);
			}
			else
			{
				if($type == "boolean")
				{
					$val = (int)$val;
				}

				$output .= sprintf(
					'(%s) %s'
					, $colors
						? static::color($type, $colors['type'], $colors['typeBg'])
						: $type
					, $colors
						? static::color($val, $colors['value'], $colors['valueBg'])
						: $val
				);
			}
		}
		else
		{
			$newParents = $parents;
			array_push($newParents, $val);

			$_val = $val;

			if(is_object($val))
			{
				$relflectedVal = new \ReflectionObject($val);
				$relflectedProps = $relflectedVal->getProperties();

				$_val = [];

				foreach($relflectedProps as $relflectedProp)
				{
					$relflectedProp = $relflectedVal->getProperty($relflectedProp->name);
					$relflectedProp->setAccessible(true);

					if($relflectedProp->isStatic())
					{
						continue;
					}

					$access = 'Public';

					if($relflectedProp->isProtected())
					{
						$access = 'Protected';
					}

					if($relflectedProp->isPrivate())
					{
						$access = 'Private';
					}

					$k = $relflectedProp->name . ':' . $access;

					$_val[$k] = $relflectedProp->getValue($val);
				}

				$_output = sprintf(
					'%s[%s] level %d'
					, get_class($val)
					, count($_val)
					, count($parents)
				);

				if($colors !== FALSE)
				{
					$output .= static::color(
						$_output
						, $colors['type']
						, $colors['typeBg']
					);
				}

			}
			else
			{
				$_output = sprintf('Array[%s] level %d'
					, count($val)
					, count($parents)
				);

				if($colors !== FALSE)
				{
					$output .= static::color(
						$_output
						, $colors['type']
						, $colors['typeBg']
					);
				}
			}

			$output .= PHP_EOL;
			$output .= str_repeat($indent, count($parents));
			$output .= '{';

			foreach($parents as $level => $parent)
			{
				if($parent === $val)
				{
					$output .= '*Recursion to Level ' . $level . '.*' . PHP_EOL;

					break;
				}
			}

			$output .= PHP_EOL;

			foreach($_val as $key => $value)
			{
				$output .= str_repeat($indent, count($parents) + 1);
				if($colors !== FALSE)
				{
					$key = static::color($key, $colors['key'], $colors['keyBg']);
				}
				$output .= sprintf('[%s] => ', $key);

				foreach($newParents as $level => $parent)
				{
					if($parent === $value)
					{
						$output .= '*Recursion to Level ' . $level . '.*' . PHP_EOL;

						continue 2;
					}
				}

				$output .= static::dump($value, $newParents, $colors);
			}

			$output .= str_repeat($indent, count($parents)) . '}';
		}

		return $output . PHP_EOL;
	}

	public static function suppress()
	{
		static::$suppress = true;
	}

	public static function colorOutput($on = true)
	{
		$colorOutput = !!$on;
	}

	public static function clear()
	{
		file_put_contents(ini_get('error_log'), null);
	}

	public static function header($level = '')
	{
		static $start;
		if(!$start)
		{
			$start = START;
		}
		$mull = pow(10,static::SECOND_SIGNIFICANCE);
		$mill = microtime(true);
		$mill -= floor($mill);
		$mill = round($mill*$mull);

		return sprintf(
			"[%.0"
				. static::SECOND_SIGNIFICANCE
				. "f]::[%s.%0"
				. static::SECOND_SIGNIFICANCE
				. "d]::[%d]::[%s]"
				. (
					$level
						? (
							'::['
								. $level
								. static::color(
									']'
									, static::HEAD_COLOR
									, static::HEAD_BACKGROUND
									, FALSE
								)
						)
						: NULL
				)
				. PHP_EOL
			, microtime(TRUE) - $start
			, date('Y-m-d h:i:s')
			, $mill
			, getmypid()
			, number_format(memory_get_usage())
		);


	}

	public static function position($depth = 0)
	{
		$backtrace = debug_backtrace();

		$position = [
			'backtrace' => array_slice($backtrace, $depth -1)
			, 'file'      => NULL
			, 'line'      => NULL
			, 'function'  => NULL
			, 'class'     => NULL
		];

		if(isset($backtrace[$depth + 1], $backtrace[$depth + 1]['file']))
		{
			$position = [
				'file'   => $backtrace[$depth + 1]['file']
				, 'line' => $backtrace[$depth + 1]['line']
			] + $position;

			if(isset($backtrace[$depth + 2]['class']))
			{
				$position['class'] = $backtrace[$depth + 2]['class'];
			}

			if(isset($backtrace[$depth + 2]['function']))
			{
				$position['function'] = $backtrace[$depth + 2]['function'];
			}
		}

		return $position;
	}

	public static function positionString($depth = 0, $glue = FALSE)
	{
		if($glue === FALSE)
		{
			$glue = PHP_EOL;
		}

		$position = (object) static::position($depth);

		if($position->class && $position->function)
		{
			return sprintf(
				"%s::%s\n%s:%d%s"
				, $position->class
				, $position->function
				, $position->file
				, $position->backtrace[$depth]['line']
				, $glue
			);
		}
		else if($position->function)
		{
			return sprintf(
				"%s\n%s:%d%s"
				, $position->function
				, $position->file
				, $position->backtrace[$depth]['line']
				, $glue
			);
		}
		else
		{
			return sprintf(
				"%s:%d%s"
				, $position->file
				, $position->backtrace[$depth]['line']
				, $glue
			);
		}
	}

	public static function trace($log = true)
	{
		$i = 0;
		$lines = [];

		while($line = static::positionString(++$i, "\n\n"))
		{
			$lines[] = count($lines) . ":" . $line;
		}

		if($log)
		{
			Log::debug(implode(NULL, $lines));
		}

		return $lines;
	}

	public static function renderTrace($trace)
	{
		$superTrace = [];

		foreach ($trace as $level => $frame)
		{
			$renderedArgs = [];

			if(isset($frame['args']))
			{
				foreach($frame['args'] as $a => $arg)
				{
					if(is_scalar($arg))
					{
						if(is_string($arg))
						{
							$renderedArg = ' "' . $arg . '"';
						}
						else
						{
							$renderedArg = static::render($arg);
						}
					}
					else
					{
						$renderedArg = static::render($arg);
					}

					$renderedArgs[] = 'Arg #' . $a . ' ' .$renderedArg;
				}
			}

			$superTrace[] = sprintf(
				"#%d %s:(%d)\n    %s::%s()%s\n"
				, $level
				, $frame['file'] ?? NULL
				, $frame['line'] ?? NULL
				, isset($frame['object'])
					? get_class($frame['object'])
					: $frame['class'] ?? NULL
				, $frame['function']
				, $renderedArgs
					? PHP_EOL . static::indent(
						implode(PHP_EOL, $renderedArgs)
						, 2
						, '    '
					)
					: NULL
			);
		}

		$superTrace[] = sprintf(
			"#%s {main}"
			, count($trace)
		);

		$superTrace = implode(PHP_EOL, $superTrace);

		return $superTrace;
	}

	public static function renderException($e)
	{
		if($e instanceof \SeanMorris\Ids\Http\HttpException)
		{
			$maxLevel = 0;
			$maxLevelString = Settings::read('logLevel');

			if(isset(static::$levels[$maxLevelString]))
			{
				$maxLevel = static::$levels[$maxLevelString];
			}

			if($maxLevel < 4)
			{
				return;
			}
		}

		$indentedTrace = preg_replace(
			['/^/m', '/\:\s(.+)/']
			, ["\t", "\n\t\t\$1\n"]
			, $e->getTraceAsString()
		);

		$trace = $e->getTrace();

		$superTrace = static::renderTrace($trace);

		return static::color(
			static::header()
			, static::HEAD_COLOR
			, static::HEAD_BACKGROUND
		) . PHP_EOL . static::color(
			sprintf(
				"%s thrown from %s:%d"
					. PHP_EOL
					. '%s'
					. PHP_EOL
				, get_class($e)
				, $e->getFile()
				, $e->getLine()
				, $e->getMessage()
			)
			, static::ERROR_COLOR
			, static::ERROR_BACKGROUND
		)
		. PHP_EOL . $superTrace . PHP_EOL;
	}

	public static function logException($e, $internal = false)
	{
		global $switches;

		$line = static::renderException($e);

		static::startLog();

		$logBlob = static::logBlob(2, $e);

		if($loggers = Settings::read('loggers'))
		{
			foreach($loggers as $logger)
			{
				$logger::log($logBlob);
			}
		}

		error_log(
			$line
			, 3
			, ini_get('error_log')
		);

		if(php_sapi_name() == 'cli' && !$internal && ($switches['vv'] ?? FALSE))
		{
			fwrite(STDERR, $line);
		}
	}

	protected static function indent($string, $level, $char = "\t")
	{
		return  ($indent = str_repeat($char, $level)) . preg_replace(
			'/\n\s*/'
			, PHP_EOL . $indent
			, $string
		);
	}

	public static function color($string, $foreground = NULL, $background = NULL, $terminate = TRUE)
	{
		if(!static::$colorOutput)
		{
			return $string;
		}

		$lines = preg_split('/\r?\n/', is_scalar($string) ? $string : print_r($string, 1));

		$fore = $foreground && isset(static::$foregroundColors[$foreground]);
		$back = $background && isset(static::$backgroundColors[$background]);

		$results = [];

		foreach($lines as $line)
		{
			$results[] = vsprintf(
				($fore ? "\033[%sm" : "\033[0m")
			  . ($back ? "\033[%sm" : NULL)
			  . '%s'
			  . ($terminate ? "\033[0m" : NULL)
			  , array_merge(
					$fore ? [static::$foregroundColors[$foreground]] : []
				  , $back ? [static::$backgroundColors[$background]] : []
				  , [$line]
			  )
		  );
		}

		return implode(PHP_EOL, $results);
	}

	protected static function render($input)
	{
		if(!is_numeric($input) && !is_string($input))
		{
			$color = static::DATA_COLOR;
			$background = static::DATA_BACKGROUND;
			$string = print_r($input,1);
		}
		else
		{
			$string = $input;
			$color = static::TEXT_COLOR;
			$background = static::TEXT_BACKGROUND;
		}

		return static::color($string, $color, $background);
	}
}
