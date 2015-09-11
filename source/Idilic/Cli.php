<?php
namespace SeanMorris\Ids\Idilic;
class Cli
{
	protected static
		$in, $out;
		
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

	public static function in()
	{
		$in = static::inHandle();

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
		fwrite(static::$out, $line);
	}
}