<?php
namespace SeanMorris\Ids;
class Linker
{
	protected static
		$exposeVars = [];

	public static function link()
	{
		$packages = \SeanMorris\Ids\Package::listPackages();
		$links = array();

		foreach($packages as $package)
		{
			$package = \SeanMorris\Ids\Package::get($package);
			$packageSpace = $package->packageSpace();
			
			if($exposedLinks = $package->getVar('link', NULL, 'global'))
			{
				foreach($exposedLinks as $key => $value)
				{
					$links[$key][$packageSpace] = $value;
				}
			}
			else
			{
				$linker = $packageSpace . '\Linker';

				if(!class_exists($linker))
				{
					continue;
				}

				if($exposedLinks = $linker::expose())
				{
					foreach($exposedLinks as $key => $value)
					{
						$links[$key][$packageSpace] = $value;
					}
				}
			}
		}

		$rootPackage = \SeanMorris\Ids\Package::getRoot();

		$rootPackage->setVar('linker:links', $links);
	}

	public static function get($key = NULL, $package = NULL)
	{
		$rootPackage = \SeanMorris\Ids\Package::getRoot();

		$links = (array)$rootPackage->getVar('linker:links:' . $key);

		if($package && !is_bool($package))
		{
			$package = strtolower($package);
			
			if(array_key_exists($package, $links))
			{
				return $links[$package];
			}

			return [];
		}

		if($package === TRUE)
		{
			if(!$links)
			{
				return [];
			}

			return array_merge(...array_values(array_map(
				function($link)
				{
					return (array)$link;
				}
				, $links
			)));
		}

		return $links;
	}

	public static function expose()
	{
		return [] + static::$exposeVars;
	}

	public static function set($key, $value)
	{
		static::$exposeVars[$key] = $value;
	}

	public static function inheritance()
	{
		$classes     = \SeanMorris\Ids\Meta::classes();
		$subClasses  = [];
		$baseClasses = [];
		$classTree   = [];

		foreach($classes as $class)
		{
			if(get_parent_class($class))
			{
				$subClasses[$class] = $class;
				continue;
			}

			$baseClasses[$class] = $class;
		}

		foreach($subClasses as $subClass)
		{
			foreach($classes as $class)
			{
				if(is_subclass_of($subClass, $class, TRUE))
				{
					$classTree[$class][] = $subClass;
				}
			}
		}

		$classTree[''] = $classes;

		return $classTree;
	}
}
