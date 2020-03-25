<?php
namespace SeanMorris\Ids;

/**
 * Loader allows aliased classnames to be mapped to actual classes. It defers the actual
 * alias operation until the autoloader runs, so that the aliased names may be overridden
 * during configuration.
 *
 * Built-in classes may be used as well as user defined and anonymous classes.
 *
 */

class Loader
{
	protected static
	$requested = []
	, $classes = []
	, $___classMatch = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\\]*$/';

	use Injectable;

	public static function register()
	{
		spl_autoload_register([static::class, 'load']);
	}

	public static function define($classes)
	{
		foreach($classes as $alias => $target)
		{
			if($alias && !preg_match(static::$___classMatch, $alias))
			{
				continue;
			}

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
		if(!$parts = mb_split('\\\\', $classname))
		{
			return;
		}

		// $injectSpace = \SeanMorris\Ids\Settings::read('injectSpace') ?: '___';
		$injectSpace = '___';

		if($parts[0] !== $injectSpace)
		{
			if($injectSpace && ($parts[2] !== $injectSpace))
			{
				return;
			}
		}

		if(!$realClass = static::$classes[$classname]??0)
		{
			return;
		}

		$trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);

		static::$requested[$classname] = [
			'file'   => $trace[1]['file']??''
			, 'line' => $trace[1]['line']??''
		];

		if(is_callable($realClass))
		{
			return $realClass;
		}

		static::cloneClass($realClass, $classname);

		return $realClass;
	}

	public static function one($instance)
	{
		return new class( function() use($instance) {

			return $instance;

		}) extends FactoryMethod {};
	}
}
