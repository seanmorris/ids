<?php
namespace SeanMorris\Ids\Mysql;
abstract class Statement
{
	protected
		$table
		, $columns = []
		, $wrappers = []
	;

	protected static $queryCount = 0;

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

		$queryObject->execute($args);
		static::$queryCount++;
		\SeanMorris\Ids\Log::debug('Queries Run: ' . static::$queryCount);
		
		$errorCode = $queryObject->errorCode();

		if($errorCode !== '00000')
		{
			$error = $queryObject->errorInfo();
			throw new \Exception($error[0] . ' ' . $error[2], $error[1]);
		}

		static::$queryCount++;

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
}