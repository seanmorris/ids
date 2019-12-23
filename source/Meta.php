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
		if(session_status() === PHP_SESSION_NONE
			&& php_sapi_name() !== 'cli'
			&& !\SeanMorris\Ids\Http\Http::disconnected()
		){
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
				if(!static::classExists($class))
				{
					continue;
				}

				if(!static::classExists($super))
				{
					continue;
				}

				try
				{
					if(!$super || is_a($class, $super, TRUE))
					{
						$classes[] = $class;
					}
				}
				catch(\Exception $e)
				{
					// Log::logException($e);
				}
			}

			return $classes;
		}

		$allFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
			$path, \RecursiveDirectoryIterator::FOLLOW_SYMLINKS
		));

		$phpFiles = new \RegexIterator($allFiles, '/\.php$/');

		$skip = \SeanMorris\Ids\Settings::read('scan', 'exclude') ?? [];

		foreach ($phpFiles as $phpFile)
		{
			$relativePath = preg_replace(
				sprintf('|^%s|', preg_quote(IDS_ROOT . '/vendor/'))
				, ''
				, $phpFile->getPath()
			);

			foreach($skip as $s)
			{
				if(preg_match(sprintf('|^%s|', $s), $relativePath))
				{
					continue 2;
				}
			}

			\SeanMorris\Ids\Log::debug(sprintf(
				'Scanning file %s'
				, $phpFile
			));

			$aliases = [];

			if(preg_match('/(simple)?[Tt]est(s)?/', $phpFile->getRealPath()))
			{
				continue;
			}

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
					$index += 2;
					while (isset($tokens[$index]) && is_array($tokens[$index]))
					{
						$namespace .= $tokens[$index++][1];
					}
				}

				// @TODO: Account for use aliases when doing class lookups.

				if(T_USE === $tokens[$index][0])
				{
					$aliased = null;
					// $alias[ $tokens[$index+2][1] ] = $tokens[$index+2][1];

					$index     += 2;
					$lastToken = null;

					while (isset($tokens[$index]) && is_array($tokens[$index]))
					{
						$lastToken = $tokens[$index];

						if(is_array($lastToken))
						{
							$lastToken = $lastToken[1];
						}

						$aliased .= $tokens[$index++][1];
					}

					$aliases[$lastToken] = $aliased;
				}

				if(T_CLASS === $tokens[$index][0]
					&& T_PAAMAYIM_NEKUDOTAYIM !== $tokens[$index-1][0]
				){
					if(T_IMPLEMENTS === $tokens[$index + 4][0]
						|| T_EXTENDS === $tokens[$index + 4][0]
					){
						$subIndex = 6;
						$subNamespace = '';

						while($tokens[$index + $subIndex][0] == T_NAMESPACE
							|| $tokens[$index + $subIndex][0] == T_NS_SEPARATOR
							|| $tokens[$index + $subIndex][0] == T_STRING
							|| $tokens[$index + $subIndex][0] == T_CLASS
						){
							$subNamespace .= $tokens[$subIndex + $index][1];
							$subIndex++;
						}

						if(isset($aliases[$subNamespace]))
						{
							$subNamespace = $aliases[$subNamespace];
						}

						if(!static::classExists($subNamespace))
						{
							break;
						}

						// if(!static::classExists($subNamespace, $phpFile->getRealPath()))
						// {
						// 	break;
						// }
					}

					$index += 2;

					if(!$namespace)
					{
						$class = $tokens[$index][1];

						$allClasses[] = $class;

						if(!static::classExists($class))
						{
							break;
						}

						if(in_array($class, $allClasses))
						{
							break;
						}

						if(!static::classExists($super))
						{
							break;
						}

						if(!$super || is_a($class, $super, TRUE))
						{
							$classes[] = $class;
						}

						break;
					}

					$class = $namespace . '\\' . $tokens[$index][1];

					try
					{
						if(!class_exists($class))
						{
							break;
						}
					}
					catch (\Exception $e)
					{
						break;
					}


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
						print $e->getMessage();
					}
					catch(\Exception $e)
					{
						print $e->getMessage();
					}

					break;
				}
			}
		}

		return $classes;
	}

	public static function classExists($class, $classFile = NULL)
	{
		global $composer;

		static $results = [];

		if(isset($results[$class]))
		{
			return $results[$class];
		}

		if(!$classFile)
		{
			if($class[0] == '\\')
			{
				$class = substr($class, 1);
			}

			$classFile = $composer->findFile($class);

			if(!$classFile)
			{
				return FALSE;
			}
		}

		$escapedClassFile = escapeshellarg($classFile);

		exec(sprintf("php -l %s", $escapedClassFile), $out, $statusCode);

		return $results[$class] = ($statusCode === 0);
	}
}
