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
		return Session::local($depth);
	}

	public static function classes($super = NULL)
	{
		$path    = IDS_ROOT;
		$classes = [];

		static $allClasses = [];

		if($super && !static::classExists($super))
		{
			return [];
		}

		if($allClasses)
		{
			foreach($allClasses as $class)
			{
				// if(!static::classExists($class))
				// {
				// 	continue;
				// }

				if(!$super || is_a($class, $super, TRUE))
				{
					$classes[] = $class;
				}
				try
				{
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
				sprintf('|^%s/|', preg_quote(IDS_ROOT))
				, ''
				, $phpFile->getPathname()
			);

			foreach($skip as $s)
			{
				if(preg_match(sprintf('|^vendor/%s|', $s), $relativePath))
				{
					continue 2;
				}
			}

			\SeanMorris\Ids\Log::debug(sprintf(
				'Scanning file %s'
				, $relativePath
			));

			$syntaxCheck = new \SeanMorris\Ids\ChildProcess(sprintf(
				'php -l %s 2>&1', escapeshellarg($phpFile->getPathname())
			));

			if(!$syntaxCheck->errorCode())
			{
				continue;
			}

			$aliases   = [];
			$content   = file_get_contents($phpFile->getRealPath());
			$namespace = '';

			try
			{
				$tokens = token_get_all($content, TOKEN_PARSE);
			}
			catch(\Throwable $error)
			{
				Log::logException($error);
				continue;
			}

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
				// if(T_USE === $tokens[$index][0])
				// {
				// 	$aliased = null;
				// 	// $alias[ $tokens[$index+2][1] ] = $tokens[$index+2][1];

				// 	$index     += 2;
				// 	$lastToken = null;

				// 	while (isset($tokens[$index]) && is_array($tokens[$index]))
				// 	{
				// 		$lastToken = $tokens[$index];

				// 		if(is_array($lastToken))
				// 		{
				// 			$lastToken = $lastToken[1];
				// 		}

				// 		$aliased .= $tokens[$index++][1];
				// 	}

				// 	$aliases[$lastToken] = $aliased;
				// }

				if((T_CLASS === $tokens[$index][0]
						|| T_TRAIT === $tokens[$index][0]
						|| T_INTERFACE === $tokens[$index][0]
					)
					&& T_PAAMAYIM_NEKUDOTAYIM !== $tokens[$index-1][0]
				){
					// if(T_IMPLEMENTS === $tokens[$index + 4][0]
					// 	|| T_EXTENDS === $tokens[$index + 4][0]
					// ){
					// 	$subIndex = 6;
					// 	$subNamespace = '';

					// 	while($tokens[$index + $subIndex][0] == T_NAMESPACE
					// 		|| $tokens[$index + $subIndex][0] == T_NS_SEPARATOR
					// 		|| $tokens[$index + $subIndex][0] == T_STRING
					// 		|| $tokens[$index + $subIndex][0] == T_CLASS
					// 		|| $tokens[$index + $subIndex][0] == T_TRAIT
					// 		|| $tokens[$index + $subIndex][0] == T_INTERFACE
					// 	){
					// 		$subNamespace .= $tokens[$subIndex + $index][1];
					// 		$subIndex++;
					// 	}

					// 	if(isset($aliases[$subNamespace]))
					// 	{
					// 		$subNamespace = $aliases[$subNamespace];
					// 	}

					// 	if($subNamespace[0] !== '\\')
					// 	{
					// 		$subNamespace = $namespace . '\\' . $subNamespace;
					// 	}

					// 	if(!static::classExists($subNamespace))
					// 	{
					// 		break;
					// 	}
					// }

					$index += 2;

					if(!$namespace)
					{
						$class = $tokens[$index][1];

						if(!static::classExists($class))
						{
							break;
						}

						if(in_array($class, $allClasses))
						{
							break;
						}

						$allClasses[] = $class;

						if($super && !static::classExists($super))
						{
							break;
						}

						if(!$super || is_a($class, $super, TRUE))
						{
							// if(substr($class, 0, 4) === 'Test' || substr($class, -4) === 'Test')
							// {
							// 	break;
							// }

							$classes[] = $class;
						}

						break;
					}

					if(isset($tokens[$index - 4]) && $tokens[$index - 4][0] === T_NEW)
					{
						$currentClassName = 'anonymous';
					}
					else
					{
						$currentClassName = $tokens[$index][1];

						$class = $namespace . '\\' . $currentClassName;

						try
						{
							if(!class_exists($class)
								&& !interface_exists($class)
								&& !trait_exists($class)
							){
								break;
							}
						}
						catch (\Throwable $e)
						{
							Log::logException($e);
							break;
						}

						$first  = strpos($class, '\\');
						$second = strpos($class, '\\', $first  + 1);
						$third  = strpos($class, '\\', $second + 1);

						// var_dump($class, $first, $second, $third, substr(
						// 	$class
						// 	, $second + 1
						// 	, $third - $second - 1
						// ));

						// if(substr($class, $second + 1, $third - $second - 1) === 'Test')
						// {
						// 	break;
						// }

						try
						{
							if(!$super || is_a($class, $super, TRUE))
							{
								$classes[] = $class;
							}
						}
						catch(\ParseError $e)
						{
							Log::logException($e);
						}
						catch(\Exception $e)
						{
							Log::logException($e);
						}

						$allClasses[] = $class;
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

		$syntaxCheck = new \SeanMorris\Ids\ChildProcess(sprintf(
			'php -l %s 2>&1', escapeshellarg($classFile)
		));

		return $results[$class] = !$syntaxCheck->errorCode();
	}
}
