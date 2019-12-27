<?php
namespace SeanMorris\Ids;
class Settings
{
	protected static
		$settings
		, $file
		, $defaultFile
		, $env
		, $currentSite
		, $currentPort
		, $callbacks
	;

	protected function __construct(){}

	public static function read(...$names)
	{
		static $cache = [];

		$cacheKey = $scoredName = implode('_', $names);

		if(isset($cache[$cacheKey]))
		{
			return $cache[$cacheKey];
		}

		$envName = static::findEnvVarName(
			$scoredName
			, static::$currentSite
			, static::$currentPort
		);

		if($envName)
		{
			$env = static::getenv();

			$envVar = $env[$envName];

			if($envName[strlen($envName)-1] === '_')
			{
				$envVar = str_getcsv($envVar, ' ');
			}

			return $cache[$cacheKey] = $envVar;
		}

		$settings = static::load(
			static::$currentSite
			, static::$currentPort
		);

		while($name = array_shift($names))
		{
			if(!isset($settings->$name))
			{
				$prefixNames = static::findEnvVarName(
					$scoredName
					, static::$currentSite
					, static::$currentPort
					, TRUE
				);

				$prefix = key($prefixNames);
				$suffix = current($prefixNames);

				if($prefixNames)
				{
					return new SettingsReader($prefix, $suffix);
				}

				return;
			}

			$settings = $settings->$name;
		}

		$cache[$cacheKey] = $settings;

		return $settings;
	}

	public static function load($hostname = NULL, $port = NULL)
	{
		if(static::$settings)
		{
			return static::$settings;
		}

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

			$settingsFile = static::findSettingsFile($hostname, $port);
			$defaultsFile = static::findSettingsFile($hostname, $port, TRUE);

			if(!static::$settings && file_exists($settingsFile))
			{
				static::$currentSite = $hostname;
				static::$currentPort = $port;

				$settings = [];
				$defaults = [];

				if(preg_match('/\.ya?ml$/', $settingsFile) && function_exists('yaml_parse_file'))
				{
					$settings = json_decode(json_encode(yaml_parse_file(
						$settingsFile
					)));
				}
				else if($settingsFile)
				{
					$settings = json_decode(file_get_contents($settingsFile));
				}

				if(preg_match('/\.ya?ml$/', $defaultsFile) && function_exists('yaml_parse_file'))
				{
					$defaults = json_decode(json_encode(yaml_parse_file(
						$defaultsFile
					)));
				}
				else if($defaultsFile)
				{
					$defaults = json_decode(file_get_contents($defaultsFile));
				}

				$merge = function($a, $b) use(&$merge){

					if(is_scalar($b))
					{
						return $b;
					}
					else if(!isset($b) && $a)
					{
						return $a;
					}

					$r = (object) [];

					if(!is_scalar($a))
					{
						foreach($a as $k => $v)
						{
							$r->$k = $v;
						}
					}

					if(!$b)
					{
						return $r;
					}

					foreach($b as $k => $v)
					{
						if(is_scalar($a))
						{
							continue;
						}

						$r->$k = $merge($r->$k ?? [], $v);
					}

					return $r;
				};

				static::$settings = $merge($defaults, $settings);

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

	public static function findEnvVarName($name, $host = NULL, $port = NULL, $prefix = FALSE)
	{
		$cacheKey = $name.'::'.$host.'::'.$port.'::'.$prefix;

		static $cache = [];

		if(isset($cache[$cacheKey]))
		{
			return $cache[$cacheKey];
		}

		$env = static::getenv();

		[$name, $host] = preg_replace(
			'/\W/'
			, '_'
			, [strtoupper($name), strtoupper($host)]
		);

		$envVarPrefix = static::envVarNames(NULL);
		$envVarNames  = static::envVarNames($name, $host, $port);

		foreach($envVarNames as $envVarName)
		{
			if(array_key_exists($envVarName, $env))
			{
				return $cache[$cacheKey] = $envVarName;
			}
		}

		if($prefix)
		{
			$found = [];

			foreach($env as $envK => $envV)
			{
				foreach($envVarNames as $e => $envVarName)
				{
					if(substr($envK, 0, strlen($envVarName)) === $envVarName)
					{
						$bareEnvPrefix = substr($envVarName, strlen($envVarPrefix[$e]));

						$found[$bareEnvPrefix][] = substr($envK, strlen($envVarName));
						break;
					}
				}
			}

			return $cache[$cacheKey] = $found;
		}
	}

	protected static function envVarNames($name, $host = NULL, $port = NULL)
	{
		$cacheKey = $name.'::'.$host.'::'.$port.'::'.$port;

		static $cache = [];

		if(isset($cache[$cacheKey]))
		{
			return $cache[$cacheKey];
		}

		[$name, $host] = preg_replace(
			['/-/'], ['___']
			, array_map('strtoupper', [$name, $host])
		);

		[$name, $host] = preg_replace(
			['/\W/'], ['_']
			, array_map('strtoupper', [$name, $host])
		);

		$hostName = NULL;
		$portName = NULL;

		if($port)
		{
			$portName = sprintf('IDS__%s__%s__%s', $host, $name, $port);
		}

		if($host)
		{
			$hostName = sprintf('IDS__%s__%s', $host, $name);
		}

		$globalName = 'IDS_' . $name;

		return $cache[$cacheKey] = array_filter(
			[
				$portName,     $portName   ? $portName   . '_' : NULL
				, $hostName,   $hostName   ? $hostName   . '_' : NULL
				, $globalName, $globalName ? $globalName . '_' : NULL
			]
		);
	}

	public static function findSettingsFile($hostname, $port, $defaults = FALSE)
	{
		if(!$defaults && isset(static::$file))
		{
			return static::$file;
		}

		if($defaults && isset(static::$defaultFile))
		{
			return static::$defaultFile;
		}

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

			, '_/settings'
			, ':/settings'
			, ';/settings'

			, sprintf('%s:%d', $hostname, $port)
			, sprintf('%s;%d', $hostname, $port)
			, sprintf('%s:', $hostname)
			, sprintf('%s;', $hostname)
			, $hostname

			, sprintf('_:%d', $port)
			, sprintf('_;%d', $port)
			, sprintf(':%d', $port)
			, sprintf(';%d', $port)
			, '_'
			, ':'
			, ';'
		];

		$defaults = $defaults ? '.defaults' : FALSE;

		foreach ($filenames as $filename)
		{
			foreach($settingsFileExtensions as $extension)
			{
				$filepath = sprintf(
					$settingsFilenameFormat
					, $filename . $defaults
					, $extension
				);

				if(file_exists($filepath))
				{
					if($switches['verbose'] ?? $switches['v'] ?? FALSE)
					{
						fwrite(fopen('php://stderr', 'w'), sprintf(
							'Using settings file: %s'
							, $filepath
						));
					}

					if($defaults)
					{
						return static::$defaultFile = $filepath;
					}
					else
					{
						return static::$file = $filepath;
					}
				}
			}
		}

		return static::$file = FALSE;
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

	public static function getenv()
	{
		if(static::$env)
		{
			return static::$env;
		}

		static::$env = getenv();

		return static::$env;
	}
}
