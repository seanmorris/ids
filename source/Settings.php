<?php
namespace SeanMorris\Ids;
class Settings
{
	protected static
		$currentSite
		, $settings
	;

	protected function __construct(){}

	public static function read(...$names)
	{
		$settings = static::load(static::$currentSite);

		while($name = array_shift($names))
		{
			if(!isset($settings->$name))
			{
				return;
			}

			$settings = $settings->$name;
		}

		return $settings;
	}

	public static function load($hostname = NULL)
	{
		if(!static::$currentSite || static::$currentSite != $hostname)
		{
			if(isset($_SERVER['HTTP_HOST']))
			{	
				$hostname = $_SERVER['HTTP_HOST'];
			}
			elseif(!$hostname)
			{
				$hostname = \SeanMorris\Ids\Idilic\Cli::option('domain', 'd');
				
				if(!$hostname)
				{
					//$idsPackage = \SeanMorris\Ids\Package::get('SeanMorris\Ids');
					//$hostname = $idsPackage->getVar('defaultDomain');
					if(file_exists(getenv("HOME") . '/.idilicProfile.json'))
					$userFile = new \SeanMorris\Ids\Storage\Disk\File(
						getenv("HOME") . '/.idilicProfile.json'
					);
					$userSettings = json_decode($userFile->slurp());
					$domain = $userSettings->root;
					//var_dump($userSettings);
				}
			}

			$rootPackage = Package::getRoot();
			
			$settingsFile = $rootPackage->localDir()
				. 'sites/'
				. $hostname
				. '.json';

			if(file_exists($settingsFile))
			{
				static::$currentSite = $hostname;
				static::$settings = json_decode(file_get_contents($settingsFile));
			}
		}

		return static::$settings;
	}
}