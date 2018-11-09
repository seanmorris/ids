<?php
namespace SeanMorris\Ids\Mysql;
abstract class Statement
{
	protected
		$table
		, $columns = []
		, $wrappers = []
	;

	protected static $queryCount = 0, $queryTime = 0;

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

	public function prepare()
	{
		$queryString = $this->assemble(...(func_get_args()));

		\SeanMorris\Ids\Log::query($queryString);

		$database = \SeanMorris\Ids\Database::get('main');

		return $database->prepare($queryString);
	}

	public function execute(...$args)
	{
		$queryObject = $this->prepare();
		\SeanMorris\Ids\Log::debug($args);

		$queryStartTime = microtime(TRUE);

		$queryObject->execute($args);

		$queryTime = microtime(TRUE) - $queryStartTime;

		$slowQuery = \SeanMorris\Ids\Settings::read('slowQuery');
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
			);
		}

		if($queryLimit > 0 && static::$queryCount == $queryLimit)
		{
			throw new \Exception(sprintf(
				'Query limit of %d reached!'
				, $queryLimit
			));
		}

		static::$queryCount++;

		static::$queryTime += $queryTime;

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
}
