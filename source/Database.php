<?php
namespace	SeanMorris\Ids;
/**
 * Provides a key/value store of database connections.
 * Each connection is a sinlgeton and will only be instantiated once.
 */
class Database
{
	static $credentials	= []
		, $connections 	= []
		, $initializers = [];

	const DEFAULT_PORT = 3306;

	/**
	 * Register database credentials to a key for use later.
	 *
	 * @param string $key the name for the database connection.
	 * @param string $dsn the data source name as specified here:
	 * 	https://www.php.net/manual/en/ref.pdo-mysql.connection.php
	 * @param string $username the username for the database connection.
	 * @param string $password the password for the database connection.
	 * @param int    $retry the number of times to retry a failes connection.
	 * @param int    $delay the number of seconds between retries.
	 */
	public static function register(...$args)
	{
		static::$credentials[ array_shift($args) ] = $args;
	}

	public static function initialize($name, callable $callback)
	{
		static::$initializers[$name][] = $callback;
	}
	/**
	 * Deregister database credentials & connections.
	 */
	public static function reset()
	{
		static::$credentials = [];
		static::$connections = [];
	}

	/**
	 * Get a database connection object.
	 * Initialize the connection if it hasn't connected yet.
	 */
	public static function get($name)
	{
		if(!isset(static::$credentials[$name]))
		{
			throw new \Exception(sprintf(
				'No Database "%s" regsitered for %s.'
				, $name
				, $_SERVER['HTTP_HOST'] ?? 'null'
			));
		}

		$tries = static::$credentials[$name][3];
		$delay = static::$credentials[$name][4];

		return isset(static::$connections[$name])
			? static::$connections[$name]
			: Fuse::retry($tries, $delay, function() use($name) {
				static::$connections[$name] = new \PDO(
					static::$credentials[$name][0]
					, static::$credentials[$name][1]
					, static::$credentials[$name][2]
				);

				if($db = static::$connections[$name])
				{
					while(static::$initializers[$name] ?? FALSE)
					{
						array_pop(static::$initializers[$name])($db);
					}

					$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

					return $db;
				}
			}
		);
	}

	/**
	 * Register multple databases at once
	 * @param iterable $args associative list of connection names and credentials
	 *   Values should be associative arrays or objects with the followign keys
	 *   database   - the name of the schema being used
	 *   hostname   - the hostname the database lives on
	 *   port       - optional, defaults to 3309
	 *   connection - dsn string. the previous 4 keys may be ommitted
	 *     if this one is specifed. See here for more info:
	 *     https://www.php.net/manual/en/ref.pdo-mysql.connection.php
	 *   username   - ths username for the connection
	 *   password   - ths username for the password
	 */
	public static function registerMulti($args)
	{
		foreach($args as $title => $database)
		{
			$database = (object) $database;

			static::register(
				$title
				, $database->connection ?: sprintf(
					'mysql:dbname=%s;host=%s;port=%d'
					, $database->database
					, $database->hostname
					, $database->port ?? static::DEFAULT_PORT
					)
				, $database->username
				, $database->password
				, $database->retry->tries ?? 0
				, $database->retry->delay ?? 0
			);
		}
	}
}
