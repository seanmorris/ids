<?php
namespace	SeanMorris\Ids;
class		Database
{
	static $credentials	= []
		, $connections	= [];

	public static function register()
	{
		$args = func_get_args();

		static::$credentials[ array_shift($args) ] = $args;
	}

	public static function reset()
	{
		static::$credentials = [];
		static::$connections = [];
	}

	public static function get($name)
	{
		if(!isset(static::$credentials[$name][0]))
		{
			throw new \Exception(sprintf(
				'No Database "%s" regsitered for %s.'
				, $name
				, $_SERVER['HTTP_HOST']
			));
		}

		return isset(static::$connections[$name])
			? static::$connections[$name]
			: static::$connections[$name] = new \PDO(
				static::$credentials[$name][0]
				, static::$credentials[$name][1]
				, static::$credentials[$name][2]
				/*
				, isset(static::$credentials[$name][3])
					? isset(static::$credentials[$name][3])
					: array()
				*/
		);
	}

	public static function registerMulti($args)
	{
		foreach($args as $title => $database)
		{
			static::register(
				$title
				, $database->connection
				, $database->username
				, $database->password
			);
		}
	}
}
