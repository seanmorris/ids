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
		$output = null;

		$logPackages = (array)Settings::read('logPackages');
		$position = static::position(1);

		$level = 0;

		if(isset(static::$levels[$levelString]))
		{
			$level = static::$levels[$levelString];
		}

		if($level <= static::$levels['warn'] && php_sapi_name() == 'cli')
		{
			print_r($data);
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

		if(isset(static::$levelColors[$levelString]))
		{
			$levelString = static::color($levelString, static::$levelColors[$levelString]);
		}

		if($level > $maxLevel || $level == 0)
		{
			return;
		}

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
			static::header($levelString) . static::positionString(1)
			, static::HEAD_COLOR
			, static::HEAD_BACKGROUND
		);

		foreach($data as $datum)
		{
			if(is_scalar($datum))
			{
				$output .= static::color($datum, static::getColor('line'), static::getColor('lineBg'));
				$output .= PHP_EOL;
				continue;
			}

			$output .= static::dump($datum, [], static::$colors);
		}

		static::startLog($maxLevel);

		$fileExists = file_exists(ini_get('error_log'));

		file_put_contents(
			ini_get('error_log')
			, PHP_EOL . $output
			, FILE_APPEND
		);

		if(!$fileExists)
		{
			chmod(ini_get('error_log'), 0666);
		}
	}

	protected static function startLog($maxLevel = 0)
	{
		if(!static::$started)
		{
			static::$started = TRUE;

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

		foreach($detectColors as $color)
		{
			if(!isset($colors[$color]))
			{
				$colors[$color] = 'none';
			}
		}

		if(!is_array($val) && !is_object($val))
		{
			$type = gettype($val);

			if($type == "string")
			{
				$output .= sprintf(
					'(%s) "%s"'
					, static::color($type, $colors['type'], $colors['typeBg'])
					, static::color($val, $colors['value'], $colors['valueBg'])
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
				, static::color($type, $colors['type'], $colors['typeBg'])
				, static::color($val, $colors['value'], $colors['valueBg'])
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

				$output .= static::color(
					sprintf(
						'%s[%s] level %d'
						, get_class($val)
						, count($_val)
						, count($parents)
					)
					, $colors['type']
					, $colors['typeBg']
				);
			}
			else
			{
				$output .= static::color(
					sprintf('Array[%s] level %d'
						, count($val)
						, count($parents)
					)
					, $colors['type']
					, $colors['typeBg']
				);
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
				$key = static::color($key, $colors['key'], $colors['keyBg']);
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

	protected static function file()
	{
		$args = func_get_args();

		if(count($args) == 1)
		{
			$line = array_shift($args);
		}
		else
		{
			$line = $args;
		}

		$depth = 0;

		if(static::$suppress)
		{
			return;
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

		if(count($_REQUEST))
		{
			$path = $path . PHP_EOL . print_r(
				$_REQUEST, 1
			);
		}

		file_put_contents(
			ini_get('error_log')
			,	PHP_EOL . (static::$started
					? (NULL)
					: (static::LOG_SEPERATOR . PHP_EOL . $path. PHP_EOL)
				)
				. static::header() . PHP_EOL
				. static::positionString($depth +2)
				. static::render($line) . PHP_EOL
			, FILE_APPEND
		);

		static::$started = true;
	}

	public static function header($level = '')
	{
		static $start;
		if(!$start)
		{
			$start = microtime(TRUE);
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
				. "d]::[%d]"
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
		);

		
	}

	public static function position($depth = 0)
	{
		$backtrace = debug_backtrace();

		if(isset($backtrace[$depth + 1], $backtrace[$depth + 1]['file']))
		{
			$position = [
				'file'   => $backtrace[$depth + 1]['file']
				, 'line' => $backtrace[$depth + 1]['line']
			];

			if(isset($backtrace[$depth + 2]['class']))
			{
				$position['class'] = $backtrace[$depth + 2]['class'];
			}

			if(isset($backtrace[$depth + 2]['function']))
			{
				$position['function'] = $backtrace[$depth + 2]['function'];
			}

			return $position;
		}
		else
		{
			return false;
		}
	}

	public static function positionString($depth = 0, $glue = FALSE)
	{
		if($glue === FALSE)
		{
			$glue = PHP_EOL;
		}

		$backtrace = debug_backtrace();

		if(isset(
			$backtrace[$depth + 1]
			, $backtrace[$depth + 1]['file']
			, $backtrace[$depth + 2]
			, $backtrace[$depth + 2]['class']
		)){
			$class = NULL;

			if(isset($backtrace[$depth + 2]['object']))
			{
				$class = get_class($backtrace[$depth + 2]['object']);
			}
			else if(isset($backtrace[$depth + 2]['class']))
			{
				$class = $backtrace[$depth + 2]['class'];
			}

			$function = NULL;

			if(isset($backtrace[$depth + 2]['function']))
			{
				$function = $backtrace[$depth + 2]['function'];
			}

			$file = $backtrace[$depth + 1]['file'];

			if(substr($file, 0, strlen(IDS_ROOT)) == IDS_ROOT)
			{
				$file = '.' . substr($file, strlen(IDS_ROOT));
			}

			if($class && $function)
			{
				return sprintf(
					"%s::%s in %s:%d%s"
					, $class
					, $function
					, $file
					, $backtrace[$depth + 1]['line']
					, $glue
				);
			}
			else if($function)
			{
				return sprintf(
					"%s\n%s:%d%s"
					, $function
					, $file
					, $backtrace[$depth + 1]['line']
					, $glue
				);
			}
			else
			{
				return sprintf(
					"%s:%d%s"
					, $file
					, $backtrace[$depth + 1]['line']
					, $glue
				);
			}
		}
		else
		{
			return false;
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

	public static function logException($e)
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
		$superTrace = [];

		foreach ($trace as $level => $frame)
		{
			$renderedArgs = [];

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
					$renderedArg = (is_object($arg)
						? get_class($arg)
						: 'Array'
					) . '[]...';
				}

				$renderedArgs[] = 'Arg #' . $a . ' ' .$renderedArg;
			}

			$superTrace[] = sprintf(
				"#%d %s:(%d)\n    %s::%s()%s\n"
				, $level
				, $frame['file']
				, $frame['line']
				, $frame['class']
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

		$line = static::color(
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
			//. PHP_EOL . $indentedTrace
			. PHP_EOL . $superTrace . PHP_EOL;

		static::startLog();

		error_log(
			$line
			, 3
			, ini_get('error_log')
		);

		if(php_sapi_name() == 'cli')
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
