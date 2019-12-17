<?php
namespace SeanMorris\Ids\Mysql;
abstract class Statement
{
	protected
		$table
		, $columns = []
		, $wrappers = []
	;

	protected static $queryCount = 0, $queryTime = 0, $altered;

	public function __construct($table)
	{
		$this->table = $this::cleanTableName($table);
		return $this;
	}

	public static function hasReplacements($format)
	{
		if(preg_match_all('/%+/', $format, $matches))
		{
			$lengths = array_map(
				function($match)
				{
					return strlen($match) % 2;
				}
				, current($matches)
			);

			return (bool)array_filter($lengths);
		}

		return false;
	}

	protected function databaseTier()
	{
		if(\SeanMorris\Ids\Database::get('main'))
		{
			return 'main';
		}
	}

	protected function database()
	{
		return \SeanMorris\Ids\Database::get($this->databaseTier());
	}

	public function prepare()
	{
		$queryString = $this->assemble(...(func_get_args()));

		\SeanMorris\Ids\Log::query(
			'Tier:' . $this->databaseTier()
			, 'Preparing query'
			, $queryString
		);

		$database = $this->database();

		return $database->prepare($queryString);
	}

	public function execute(...$args)
	{
		$queryStartTime = microtime(TRUE);

		$queryObject = $this->prepare();
		\SeanMorris\Ids\Log::debug($args);

		$queryObject->execute($args);

		$queryTime = microtime(TRUE) - $queryStartTime;

		static::$queryCount++;

		static::$queryTime += $queryTime;

		$queryHash = sha1(print_r([
			$queryObject->queryString, $args
		],1));

		\SeanMorris\Ids\Log::query('Query executed.', new \SeanMorris\Ids\LogMeta([
			'query'         => $queryObject->queryString
			, 'query_time'  => $queryTime
			, 'query_tier'  => $this->databaseTier()
			, 'query_type'  => get_called_class()
			, 'query_table' => $this->table
			, 'query_args'  => $args
			, 'query_hash'  => $queryHash
		]));

		$slowQuery  = \SeanMorris\Ids\Settings::read('slowQuery');
		$queryLimit = \SeanMorris\Ids\Settings::read('queryLimit');

		if($slowQuery && $slowQuery <= $queryTime)
		{
			\SeanMorris\Ids\Log::warn(
				sprintf(
					'Following query took %f seconds (slow query cutoff: %f)'
					, $queryTime
					, $slowQuery
				)
				, ''
				, $queryObject->queryString
				, new \SeanMorris\Ids\LogMeta([
					'query'         => $queryObject->queryString
					, 'query_time'  => $queryTime * 1000
					, 'query_tier'  => $this->databaseTier()
					, 'query_type'  => get_called_class()
					, 'query_args'  => $args
					, 'query_table' => $this->table
					, 'query_hash'  => $queryHash
				])
			);
		}

		if($queryLimit > 0 && static::$queryCount == $queryLimit)
		{
			throw new \Exception(sprintf(
				'Query limit of %d reached!'
				, $queryLimit
			));
		}

		\SeanMorris\Ids\Log::debug(
			'Queries Run: ' . static::$queryCount
			, sprintf('Query ran in %f seconds.', $queryTime)
			, sprintf('Total time waiting on database: %f seconds.', static::$queryTime)
		);


		$errorCode = $queryObject->errorCode();

		if($errorCode !== '00000')
		{
			$error = $queryObject->errorInfo();
			throw new \Exception($error[0] . ' ' . $error[2], $error[1]);
		}

		return $queryObject;
	}

	public function columns(...$columns)
	{
		$this->columns = $this::apply([$this, 'cleanColumnName'], ...$columns);

		return $this;
	}

	public function wrappers($wrappers)
	{
		$this->wrappers = $wrappers;

		return $this;
	}

	protected static function cleanColumnName($name)
	{
		return static::cleanWord($name);
	}

	protected static function cleanTableName($name)
	{
		return static::cleanWord($name);
	}

	protected static function cleanWord($name)
	{
		return preg_replace('/\W/', '', $name);
	}

	protected static function apply($callback, ...$args)
	{
		return array_map($callback, $args);
	}

	public static function queryCount()
	{
		return static::$queryCount;
	}

	public static function queryTime()
	{
		return static::$queryTime;
	}

	protected static function altered($table)
	{
		static::$altered[$table] = TRUE;
	}

	protected static function isAltered($table)
	{
		return static::$altered[$table]?? FALSE;
	}

	public function table()
	{
		return $this->table;
	}
}
