<?php
namespace SeanMorris\Ids;
class Meta
{
	protected static
		$session = []
	;

	public static function getInstanceStack($className, $depth)
	{
		return array_filter(
			static::getObjectStack(1 + $depth)
			, function($object) use($className)
			{
				return is_object($object) && $object instanceof $className;
			}
		);
	}

	public static function getObjectStack($depth = 0, $useObjects = true)
	{
		$trace = debug_backtrace();

		do{
			array_shift($trace);
		} while($depth && $depth-- > 0);

		$entries = [];
		$last = null;
		$next = null;

		foreach($trace as $level)
		{
			$next = NULL;

			if(!isset($level['object']) && isset($level['class']))
			{
				$next = $level['class'];
			}
			else if($useObjects && isset($level['object']))
			{
				$next = $level['object'];
			}
			elseif(isset($level['class']))
			{
				$next = $level['class'];
			}			

			if($next === $last)
			{
				continue;
			}

			$last = $next;

			if($next)
			{
				$entries[] = $next;
			}
		}

		return $entries;
	}

	public static function &staticSession($depth = 0)
	{
		if(session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli')
		{
			session_start();
		}

		if(!isset($_SESSION['meta']))
		{
			$_SESSION['meta'] = [];
			$_SESSION['sess_id'] = uniqid();
		}

		static::$session =& $_SESSION['meta'];

		if($objectStack = static::getObjectStack(1, false))
		{
			$class = $objectStack[0];

			if(!is_string($class))
			{
				$class = get_class($objectStack[0]);
			}

			while($depth-- > 0)
			{
				$class = get_parent_class($class);
			}

			if(!isset(static::$session[$class]))
			{
				static::$session[$class] = [];
			}

			return static::$session[$class];
		}

		return false;
	}

	public static function classes($super = NULL)
	{
		$path       = IDS_ROOT;
		$classes    = [];
		
		static $allClasses = [];

		if($allClasses)
		{
			foreach($allClasses as $class)
			{
				if(!$super || is_a($class, $super, TRUE))
				{
					$classes[] = $class;
				}
			}

			return $classes;
		}

		$allFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
		$phpFiles = new \RegexIterator($allFiles, '/\.php$/');

		foreach ($phpFiles as $phpFile)
		{
			$content = file_get_contents($phpFile->getRealPath());
			$tokens = token_get_all($content);
			$namespace = '';

			for($index = 0; isset($tokens[$index]); $index++)
			{
				if(!isset($tokens[$index][0]))
				{
					continue;
				}

				if(T_NAMESPACE === $tokens[$index][0])
				{
					$index += 2; // Skip namespace keyword and whitespace
					while (isset($tokens[$index]) && is_array($tokens[$index]))
					{
						$namespace .= $tokens[$index++][1];
					}
				}

				if(T_CLASS === $tokens[$index][0])
				{
					$index += 2; // Skip class keyword and whitespace

					if(!$namespace)
					{
						break;
					}

					$class = $namespace.'\\'.$tokens[$index][1];

					if(in_array($class, $allClasses))
					{
						break;
					}

					$allClasses[] = $class;

					try
					{
						if(!$super || is_a($class, $super, TRUE))
						{
							$classes[] = $class;
						}
					}
					catch(\ParseError $e)
					{

					}
					catch(\Exception $e)
					{

					}

					# break if you have one class per file (psr-4 compliant)
					# otherwise you'll need to handle class constants (Foo::class)
					break;
				}
			}
		}

		return $classes;
	}
}