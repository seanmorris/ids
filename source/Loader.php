<?php
namespace SeanMorris\Ids;
class Loader
{
	protected static
	$requested = []
	, $classes = [];

	use Injectable;

	public static function register()
	{
		spl_autoload_register([static::class, 'load']);
	}

	public static function inject($classes)
	{
		foreach($classes as $alias => $target)
		{
			if(static::$requested[$alias]??0)
			{
				throw new \Exception(sprintf(
					'Cannot override injection %s after usage. Consider moving your injections up,'. PHP_EOL . '%s:%s'
					, $alias
					, static::$requested[$alias]['file']
					, static::$requested[$alias]['line']
				));
			}

			if(is_object($target))
			{
				$target = get_class($target);
			}

			static::$classes[$alias] = $target;
		}

		return static::$classes;
	}

	public static function load($classname)
	{
		if(!$parts = explode('\\', $classname))
		{
			return;
		}

		$injectSpace = \SeanMorris\Ids\Settings::read('injectSpace');

		if($injectSpace && ($parts[0] !== $injectSpace))
		{
			return;
		}

		if(!$realClass = static::$classes[$classname]??0)
		{
			return;
		}

		$trace = debug_backtrace();

		if(!isset($trace[1], $trace[1]['function']))
		{
			return;
		}

		if($trace[1]['function'] !== 'spl_autoload_call')
		{
			throw new \Exception('Cannot call autoloader directly.');
		}

		static::$requested[$classname] = [
			'file'   => $trace[1]['file']
			, 'line' => $trace[1]['line']
		];

		class_alias($realClass, $classname);
	}
}
