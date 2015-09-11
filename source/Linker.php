<?php
namespace SeanMorris\Ids;
class Linker
{
	public static function link($composer)
	{
		$packages = \SeanMorris\Ids\Package::listPackages($composer);
		$links = array();

		foreach($packages as $package)
		{
			$package = \SeanMorris\Ids\Package::get($package);
			$packageSpace = $package->packageSpace();

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

		$ids = \SeanMorris\Ids\Package::get();

		$ids->setVar('linker:links', $links);
	}

	public static function get($key = NULL, $package = NULL)
	{
		$ids = \SeanMorris\Ids\Package::get();

		$links = (array)$ids->getVar('linker:links:' . $key);

		if($package && !is_bool($package))
		{
			if(array_key_exists($package, $links))
			{
				return $links[$package];
			}

			return [];
		}

		if($package === TRUE)
		{
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
	}
}