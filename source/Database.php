<?php
namespace	SeanMorris\Ids;
class		Database
{
	static $credentials	= []
		, $connections	= [];

	public static function register(...$args)
	{
		static::$credentials[ array_shift($args) ] = $args;
	}

	public static function reset()
	{
		static::$credentials = [];
		static::$connections = [];
	}

	public static function get($name)
	{
		if(!isset(static::$credentials[$name]))
		{
			throw new \Exception(sprintf(
				'No Database "%s" regsitered for %s.'
				, $name
				, $_SERVER['HTTP_HOST']
			));
		}

		$tries = php_sapi_name() === 'cli' ? 15 : 3;
		$delay = php_sapi_name() === 'cli' ? 5  : 0;

		return Fuse::retry($tries, $delay, function() use($name) {
			$db = isset(static::$connections[$name])
				? static::$connections[$name]
				: static::$connections[$name] = new \PDO(
					static::$credentials[$name][0]
					, static::$credentials[$name][1]
					, static::$credentials[$name][2]
			);

			if($db)
			{
				$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			}

			return $db;
		});
	}

	public static function registerMulti($args)
	{
		foreach($args as $title => $database)
		{
			static::register(
				$title
				, $database->connection ?: sprintf(
					'mysql:dbname=%s;host=%s;'
					, $database->database
					, $database->hostname
				)
				, $database->username
				, $database->password
			);
		}
	}
}
