<?php
namespace SeanMorris\Ids;
class Linker
{
	protected static
		$exposeVars    = []
		, $foreginVars = [];

	public static function link()
	{
		$rootPackage = \SeanMorris\Ids\Package::getRoot();
		$packages = \SeanMorris\Ids\Package::listPackages();
		$links = array();

		foreach($packages as $package)
		{
			$package = \SeanMorris\Ids\Package::get($package);
			$packageSpace = $package->packageSpace();

			if($exposedLinks = $package->getVar('link', NULL))
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
		$rootPackage->setVar(
			'linker:foreignLinks'
			, static::$foreginVars[get_called_class()] ?? []
		);
	}

	public static function get($key = NULL, $package = NULL)
	{
		$rootPackage = \SeanMorris\Ids\Package::getRoot();

		$links = (array) $rootPackage->getVar('linker:links:' . $key);

		if($package && !is_bool($package))
		{
			$package = \SeanMorris\Ids\Package::get($package);
			$packageSpace = $package->packageSpace();

			$links = (array) $rootPackage->getVar('linker:foreignLinks');

			if(array_key_exists($packageSpace, $links))
			{
				$links = (array) $links[$packageSpace];
			}

			if(array_key_exists($key, $links))
			{
				return $links[$key];
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
		return [] + static::$exposeVars[get_called_class()];
	}

	public static function set($key, $value, $package = NULL)
	{
		if($package)
		{
			if(!is_object($package))
			{
				$package = \SeanMorris\Ids\Package::get($package);
			}

			$package = $package->packageSpace();

			static::$foreginVars[get_called_class()][$package][$key] = $value;

			return;
		}

		static::$exposeVars[get_called_class()][$key] = $value;
	}

	public static function inheritance()
	{
		$classes     = \SeanMorris\Ids\Meta::classes();
		$subClasses  = [];
		$classTree   = [];

		foreach($classes as $class)
		{
			if(get_parent_class($class))
			{
				$subClasses[$class] = $class;
				continue;
			}
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
