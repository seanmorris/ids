<?php
namespace SeanMorris\Ids\Idilic;
class Cli
{
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

	protected static
		$in, $out, $error;
		
	public static function option(...$names)
	{
		static $options; 
		$options = static::options();

		while($name = array_shift($names))
		{
			if(isset($options[$name]))
			{
				return $options[$name];
			}
		}
	}

	protected static function options()
	{
		global $argv;

		if(!is_array($argv))
		{
			return [];
		}

		$input = $argv;

		$script = array_shift($input);

		$getConfig = function($name, &$config = NULL)
		{
			static $array;

			if($config !== NULL)
			{
				$array = $config;
			}

			if(isset($array[$name]))
			{
				return $array[$name];
			}
		};

		$config = [];

		while($input && preg_match('/^-/', $input[0]))
		{
			$c = array_shift($input);

			if(preg_match('/^--(.+?)[\s=](.+?)$/', $c, $kv))
			{
				$config[$kv[1]] = $kv[2];
			}
			else if(preg_match('/^--(.+?)$/', $c, $kv))
			{
				$config[$kv[1]] = true;
			}
			else if(preg_match('/^-(.)[\s=](.+?)$/', $c, $kv))
			{
				$config[$kv[1]] = $kv[2];
			}
			else if(preg_match('/^-(.+?)$/', $c, $kv))
			{
				foreach(str_split($kv[1]) as $flag)
				{
					$config[$flag] = true;
				}
			}
		}

		$getConfig(NULL, $config);

		return $config;
	}

	public static function question($question)
	{
		static::out($question . "\n");
		return static::in();
	}

	public static function multiQuestion($question, $answers, $lastLine = NULL)
	{
		static::out($question . "\n");

		foreach($answers as $key => $answer)
		{
			printf("\t%s: %s\n", $key, $answer);
		}

		static::out($lastLine);

		return static::in();
	}

	public static function inHandle()
	{
		if(!static::$in)
		{
			static::$in = fopen('php://stdin', 'r');
		}

		return static::$in;
	}

	public static function in($label = NULL)
	{
		$in = static::inHandle();

		stream_set_blocking ($in, true);

		return trim(fgets($in));
	}

	public static function outHandle()
	{
		if(!static::$out)
		{
			static::$out = fopen('php://stdout', 'w');
		}

		return static::$out;
	}

	public static function out($line)
	{
		$out = static::outHandle();
		fwrite($out, $line);
	}

	public static function errorHandle()
	{
		if(!static::$error)
		{
			static::$error = fopen('php://stderr', 'w');
		}

		return static::$error;
	}

	public static function error($line)
	{
		$error = static::errorHandle();
		fwrite($error, $line);
	}

	public static function color($string, $foreground = NULL, $background = NULL, $terminate = TRUE)
	{
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

	public static function isCli()
	{
		return php_sapi_name() === 'cli';
	}
}