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

		$links = $rootPackage->getVar('linker:links', (object) [], 'global');

		foreach($packages as $package)
		{
			$package = \SeanMorris\Ids\Package::get($package);
			$packageSpace = $package->packageSpace();

			$linker = $packageSpace . 'Linker';

			if($linker !== __CLASS__ && !class_exists($linker))
			{
				if($exposedLinks = $package->getVar('link', NULL, 'global'))
				{
					foreach($exposedLinks as $key => $value)
					{
						$links[$key][$packageSpace] = $value;
					}
				}

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

		$rootPackage->setVar('linker:links', $links, 'global');
	}

	public static function get($key = NULL, $default = [])
	{
		$rootPackage = \SeanMorris\Ids\Package::getRoot();

		$realKey = 'linker:links:' . $key;

		$links = $rootPackage->getVar($realKey, $default, 'global');

		return (array) $links;
	}

	public static function expose()
	{
		return [] + (static::$exposeVars[get_called_class()] ?? []);
	}

	public static function set($key, $value)
	{
		$rootPackage = \SeanMorris\Ids\Package::getRoot();

		$links = $rootPackage->setVar(
			'linker:links:' . $key
			, $value
			, 'global'
		);

		return (array) $links;
	}

	public static function classes($super = '')
	{
		$rootPackage = \SeanMorris\Ids\Package::getRoot();

		$realKey = 'linker:inheritance:' . $super;

		$classes = $rootPackage->getVar($realKey, [], 'global') ?: [];

		if($super)
		{
			array_unshift($classes, $super);
		}

		return $classes;
	}

	public static function inheritance()
	{
		$classes    = \SeanMorris\Ids\Meta::classes();
		$subClasses = [];
		$classTree  = [];

		foreach($classes as $index => $class)
		{
			try
			{
				$parents = class_parents($class);
			}
			catch(\ErrorException $exception)
			{
				Log::logException($exception);
				unset($classes[$index]);
			}

			if(!$parents)
			{
				continue;
			}

			foreach($parents as $parent)
			{
				$classTree[$parent][] = $class;
			}

			if($classTree[$parent])
			{
				sort($classTree[$parent]);
			}
		}

		$classTree[''] = array_values($classes);

		sort($classTree['']);

		return $classTree;
	}
}
