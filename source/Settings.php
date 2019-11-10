<?php
namespace SeanMorris\Ids;
class Settings
{
	protected static
		$settings
		, $currentSite
		, $currentPort
		, $callbacks
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
				$hostname = parse_url('//' . $_SERVER['HTTP_HOST'], PHP_URL_HOST);
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

				if(preg_match('/\.ya?ml$/', $settingsFile) && function_exists('yaml_parse_file'))
				{
					static::$settings = json_decode(json_encode(yaml_parse_file(
						$settingsFile
					)));
				}
				else
				{
					static::$settings = json_decode(file_get_contents($settingsFile));
				}

				if(!static::$settings)
				{
					throw new \Exception(sprintf(
						'Settings file at %s is invalid.'
						, static::$settings
					));
				}
			}
		}

		return static::$settings;
	}

	public static function findSettingsFile($hostname, $port)
	{
		global $switches;

		$rootPackage = Package::getRoot();

		$settingsFileExtensions = ['json'];

		if(function_exists('yaml_parse_file'))
		{
			$settingsFileExtensions = ['yml', 'yaml', 'json'];
		}

		$settingsFilenameFormat = $rootPackage->localDir() . 'sites/%s.%s';

		$filenames = [
			sprintf('%s:%d/settings', $hostname, $port)
			, sprintf('%s;%d/settings', $hostname, $port)
			, sprintf('%s:/settings', $hostname)
			, sprintf('%s;/settings', $hostname)
			, $hostname . '/settings'
			, sprintf(':%d/settings', $port)
			, sprintf(';%d/settings', $port)
			, ':/settings'
			, ';/settings'

			, sprintf('%s:%d', $hostname, $port)
			, sprintf('%s;%d', $hostname, $port)
			, sprintf('%s:', $hostname)
			, sprintf('%s;', $hostname)
			, $hostname
			, sprintf(':%d', $port)
			, sprintf(';%d', $port)
			, ':'
			, ';'
		];

		$checked = [];

		foreach ($filenames as $filename)
		{
			foreach($settingsFileExtensions as $extension)
			{
				$filepath = sprintf($settingsFilenameFormat, $filename, $extension);

				$checked[] = $filepath;

				if(file_exists($filepath))
				{
					if($switches['verbose'] ?? $switches['v'] ?? FALSE)
					{
						fwrite(fopen('php://stderr'), sprintf(
							'Using settings file: %s'
							, $filepath
						));
					}

					return $filepath;
				}
			}
		}

		return FALSE;
	}

	public static function register(...$name)
	{
		$callback = array_pop($name);

		static::$callbacks[implode('::', $name)] = $callback;
	}

	public static function get(...$name)
	{
		if($c = static::$callbacks[implode('::', $name)])
		{
			return $c(static::read(...$name));
		}

		return FALSE;
	}
}
