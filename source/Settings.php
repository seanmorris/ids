<?php
namespace SeanMorris\Ids;
class Settings
{
	protected static
		$settings
		, $currentSite
		, $currentPort
	;

	protected function __construct(){}

	public static function read(...$names)
	{
		$settings = static::load(
			static::$currentSite
			, static::$currentPort
		);

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

	public static function load($hostname = NULL, $port = NULL)
	{
		if(!static::$currentSite || static::$currentSite != $hostname)
		{
			if(isset($_SERVER['HTTP_HOST']))
			{	
				$hostname = $_SERVER['HTTP_HOST'];
			}

			if(isset($_SERVER['SERVER_PORT']))
			{
				$port = $_SERVER['SERVER_PORT'];
			}

			if(!$hostname)
			{
				$hostname = \SeanMorris\Ids\Idilic\Cli::option('domain', 'd');

				if(!$hostname)
				{
					if(file_exists(getenv("HOME") . '/.idilicProfile.json'))
					{
						$userFile = new \SeanMorris\Ids\Disk\File(
							getenv("HOME") . '/.idilicProfile.json'
						);
						$userSettings = json_decode($userFile->slurp());
						$hostname = $userSettings->domain;
					}
				}
			}

			$rootPackage = Package::getRoot();

			$settingsFile = static::findSettingsFile($hostname, $port);

			if(file_exists($settingsFile))
			{
				static::$currentSite = $hostname;
				static::$currentPort = $port;

				static::$settings = json_decode(file_get_contents($settingsFile));
			}
		}

		return static::$settings;
	}

	public static function findSettingsFile($hostname, $port)
	{
		$rootPackage = Package::getRoot();

		$settingsFileFormat = $rootPackage->localDir() . 'sites/%s.json';

		$filenames = [
			sprintf('%s:%d', $hostname, $port)
			, sprintf('%s;%d', $hostname, $port)
			, $hostname
			, sprintf(':%d', $port)
			, sprintf(';%d', $port)
			, ':'
			, ';'
		];

		foreach ($filenames as $filename)
		{
			$filepath = sprintf($settingsFileFormat, $filename);

			if(file_exists($filepath))
			{
				return $filepath;
			}
		}

		return FALSE;
	}
}
