<?php
namespace SeanMorris\Ids;

class Log
{
	const SECOND_SIGNIFICANCE	= 5
		, TEXT_COLOR			= 'none'
		, TEXT_BACKGROUND		= 'none'
		, HEAD_COLOR			= 'lightGrey'
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
		static::log(__FUNCTION__, ...$data);
	}

	protected static function log($levelString, ...$data)
	{
		$colors = [
			'key' => 'yellow'
			, 'keyBg' => 'black'
			, 'type' => 'green'
			, 'typeBg' => 'black'
			, 'value' => 'white'
			, 'valueBg' => 'black'
			, 'line' => 'white'
			, 'lineBg' => 'black'
		];

		$output = null;

		$logPackages = (array)Settings::read('logPackages');
		$position = static::position(1);

		$level = 0;

		if(isset(static::$levels[$levelString]))
		{
			$level = static::$levels[$levelString];
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

		if($level > $maxLevel)
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

		if(!static::$started)
		{
			static::$started = TRUE;

			if(Settings::read('clearLogs'))
			{
				file_put_contents(IDS_LOG_PATH, ' ');
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

			if(count($_REQUEST) && $level <= 4)
			{
				$path = $path . PHP_EOL
					. 'Request: ' . PHP_EOL
					. static::dump($_REQUEST, [], $colors)
				;
			}

			$output = static::LOG_SEPERATOR . PHP_EOL . $path. PHP_EOL;
		}

		$output .= static::header($levelString). static::positionString(1);

		foreach($data as $datum)
		{
			if(is_scalar($datum))
			{
				$output .= static::color($datum, $colors['line'], $colors['lineBg']);
				$output .= PHP_EOL;
				continue;
			}

			$output .= static::dump($datum, [], $colors);
		}

		file_put_contents(
			IDS_LOG_PATH
			, PHP_EOL . $output
			, FILE_APPEND
		);
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

	/*
	public static function printData($struct, $colors = array(), $depth = 0)
	{
		print_r($struct);
		$structString = print_r($struct, 1);

		$colors['key'] = 'red';

		$structString = preg_replace_callback(
			'/^(\s+)\[(.+?)\] =>(.+?)/ms'
			, function($match) use($colors)
			{
				static::debug($match);
				if(!isset($colors['key']))
				{
					$colors['key'] = 'none';
				}

				if(!isset($colors['keyBg']))
				{
					$colors['keyBg'] = 'none';
				}

				if(!isset($colors['bracket']))
				{
					$colors['bracket'] = 'none';
				}

				if(!isset($colors['bracketBg']))
				{
					$colors['bracketBg'] = 'none';
				}

				if(!isset($colors['text']))
				{
					$colors['text'] = 'none';
				}

				if(!isset($colors['textBg']))
				{
					$colors['textBg'] = 'none';
				}

				$key = static::color($match[2], $colors['key'], $colors['keyBg']);

				return $match[1] . '[' . $key . '] => ';
			}
			, $structString
		);

		static::debug($struct);
		static::debug($structString);
	}*/

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
		file_put_contents(IDS_LOG_PATH, null);
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
			IDS_LOG_PATH
			,	PHP_EOL . (static::$started
					? (NULL)
					: (static::LOG_SEPERATOR . PHP_EOL . $path. PHP_EOL)
				)
				. static::header()
				. static::positionString($depth +2)
				. static::render($line) . PHP_EOL
			, FILE_APPEND
		);

		static::$started = true;
	}

	public static function header($level = '')
	{
		$mull = pow(10,static::SECOND_SIGNIFICANCE-1);
		$mill = microtime(true);
		$mill -= floor($mill);
		$mill = round($mill*$mull);

		return sprintf(
			static::color(
				"[%s.%04d]::[%d]"
				 . (
					$level
						? sprintf('[%s]', $level)
						: NULL
				) 
				, static::HEAD_COLOR
				, static::HEAD_BACKGROUND
			)
			, date('Y-m-d h:i:s')
			, $mill
			, getmypid()
		) . PHP_EOL;
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

		if(isset($backtrace[$depth + 1], $backtrace[$depth + 1]['file']))
		{
			return $backtrace[$depth + 1]['file']
				. ':' . $backtrace[$depth + 1]['line'] . $glue
				. (isset($backtrace[$depth + 2]['class']) ? (
					$backtrace[$depth + 2]['class'] . '::'
				) : NULL)
				. (isset($backtrace[$depth + 2]['function']) ? (
					$backtrace[$depth + 2]['function']  . PHP_EOL
				) : NULL);
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

		while($line = static::positionString(++$i, "\n\t"))
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
		$indentedTrace = preg_replace(
			'/^/m'
			, "\t"
			, $e->getTraceAsString()
		);

		$line = static::header() . PHP_EOL . static::color(
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
		). PHP_EOL . $indentedTrace . PHP_EOL;

		error_log(
			$line
			, 3
			, IDS_LOG_PATH
		);
	}

	public static function color($string, $foreground = NULL, $background = NULL, $terminate = TRUE)
	{
		if(!static::$colorOutput)
		{
			return $string;
		}

		$lines = preg_split('/\r?\n/', $string);

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
