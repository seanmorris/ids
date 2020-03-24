<?php
namespace SeanMorris\Ids;

/**
 * Loader is a dependency injection handler that allows for the promotion of anonymous
 * classes. Once a claass has been promoted and given a proper name, it can be extended,
 * used in typehints and return types, etc. Normal classes may also be provided by string.
 *
 * The added layers of complexity allow for injected classes to be overridden.
 * act as
 */

class Loader
{
	protected static
	$requested = []
	, $classes = []
	, $classMatch = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\\]*$/';

	use Injectable;

	public static function register()
	{
		spl_autoload_register([static::class, 'load']);
	}

	public static function define($classes)
	{
		foreach($classes as $alias => $target)
		{
			if($alias && !preg_match(static::$classMatch, $alias))
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

			if($target instanceof FactoryMethod)
			{
				$splitAt    = mb_strpos($alias, "\\");
				$aliasSpace = mb_substr($alias, 0, $splitAt);
				$shortAlias = mb_substr($alias, $splitAt + 1);
				$called     = get_called_class();
				$template   = sprintf(
					'namespace %s; function %s(...$args) { return (\%s::load("%s"))(...$args); }'
					, $aliasSpace
					, $shortAlias
					, $called
					, $alias
				);

				if(!function_exists($alias))
				{
					eval($template);
				}
			}
			else if(is_object($target))
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

		$trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);

		static::$requested[$classname] = [
			'file'   => $trace[1]['file']??''
			, 'line' => $trace[1]['line']??''
		];

		if(is_callable($realClass))
		{
			return $realClass;
		}

		class_alias($realClass, $classname);

		return $realClass;
	}

	public static function one($instance)
	{
		return new class( function() use($instance) {

			return $instance;

		}) extends FactoryMethod {};
	}
}
