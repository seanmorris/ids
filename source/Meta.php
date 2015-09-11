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
		if(session_status() === PHP_SESSION_NONE)
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

	public function caller()
	{
		// todo: Implement or remove
		$objectStack = static::objectStack(1);
	}
}