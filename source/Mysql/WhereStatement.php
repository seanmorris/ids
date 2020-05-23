<?php
namespace SeanMorris\Ids\Mysql;
abstract class WhereStatement extends Statement
{
	protected
		$namedParams     = false
		, $conditions    = []
		, $joins         = []
		, $valueWrappers = []
		, $valueNames    = []
		, $valueRequired = []
	;

	const RETURNS = FALSE;

	public function execute(...$args)
	{
		$queryStartTime = microtime(TRUE);

		if(isset($args[0]) && is_array($args[0]))
		{
			$queryObject = $this->prepare($args[0]);

			$argsDist = [];

			foreach($this->valueNames as $argName)
			{
				if(!isset($args[0][$argName]))
				{
					continue;
				}

				$argsDist[] = $args[0][$argName];
			}

			$args = $argsDist;
		}
		else
		{
			$queryObject = $this->prepare();
		}

		foreach($this->joins() as $join)
		{
			list($sub, $superCol, $subCol, $subType) = $join;

			$this->valueWrappers += array_merge($this->valueWrappers, $sub->valueWrappers);
		}

		$args = array_map(
			function($value, $wrapper)
			{
				if($wrapper && is_array($value))
				{
					return array_map(
						function($v) use($wrapper) { return sprintf($wrapper, $v); }
						, $value
					);
				}

				if($wrapper)
				{
					return sprintf($wrapper, $value);
				}

				return $value;
			}
			, $args
			, $this->valueWrappers
		);

		// \SeanMorris\Ids\Log::debug('Args:', $args);

		if($nonscalar = array_filter($args, function($a) {
			return !is_scalar($a)
				&& !is_null($a)
				&& !is_array($a);
		})) {
			throw new \Exception('Nonscalar argument supplied to WhereStatement.');
		}

		$finalArgs = [];

		foreach($args as $arg)
		{
			if(is_array($arg))
			{
				foreach($arg as $a)
				{
					$finalArgs[] = $a;
				}
				continue;
			}
			$finalArgs[] = $arg;
		}

		$queryObject->execute($finalArgs);

		$queryTime = microtime(TRUE) - $queryStartTime;

		static::$queryCount++;

		static::$queryTime += $queryTime;

		\SeanMorris\Ids\Log::query($queryObject->queryString, $args, new \SeanMorris\Ids\LogMeta([
			'query'         => $queryObject->queryString
			, 'query_time'  => $queryTime
			, 'querty_tier' => $this->databaseTier()
			, 'query_type'  => get_called_class()
			, 'query_args'  => $finalArgs
			, 'query_table' => $this->table
		]));

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
				, ''
				, implode(PHP_EOL, \SeanMorris\Ids\Log::trace(FALSE))
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
			, sprintf('Query ran in %0.3fms.', $queryTime * 1000)
			, sprintf('Total time waiting on database: %f seconds.', parent::$queryTime)
		);

		$errorCode = $queryObject->errorCode();

		if($errorCode !== '00000')
		{
			$error = $queryObject->errorInfo();

			throw new \Exception(sprintf(
				'%s %s %s', ...$error
			));
		}

		return $queryObject;
	}

	public function conditions($conditions)
	{
		// if(!$conditions)
		// {
		// 	return $this;
		// }

		// \SeanMorris\Ids\Log::debug('CONDITIONS!', $conditions, $this->conditions);

		if(is_numeric(key($conditions)))
		{
			$conditions = ['AND' => $conditions];
		}

		// \SeanMorris\Ids\Log::debug($conditions);

		if($this->conditions)
		{
			foreach($conditions as $key => $subconditions)
			{
				// \SeanMorris\Ids\Log::debug($subconditions);

				if(isset($this->conditions[$key]))
				{
					foreach($subconditions as $subkey => $condition)
					{
						$this->conditions[$key][] = $condition;
					}
				}
				else
				{
					$this->conditions[$key] = $subconditions;
				}
			}
		}
		else
		{
			$this->conditions = $conditions;
		}

		// \SeanMorris\Ids\Log::debug($this->conditions);

		return $this;
	}

	public function conditionTree($tree, $operator = null, $alias = null, $namedArgs = [])
	{
		if($operator === NULL)
		{
			$operator = 'AND';
		}

		$strings = [];

		//$this->valueWrappers = [];

		foreach($tree as $key => $condition)
		{
			if(!is_array($condition))
			{
				\SeanMorris\Ids\Log::error(
					'Malformed condition.'
					, $key
					, $condition
					, $tree
					, $namedArgs
				);

				throw new \Exception('Malformed condition.');
				// continue;
			}

			if($key === 'AND' || $key === 'OR')
			{
				if(is_numeric($key))
				{
					$key = 'AND';
				}

				$string = $this->conditionTree($condition, $key, $alias, $namedArgs);

				if($string)
				{
					$strings[] = $string;
				}
			}
			else
			{
				$column   = key($condition);
				$value    = current($condition);
				$compare  = isset($condition[0]) ? $condition[0] : '=';
				$wrapper  = isset($condition[1]) ? $condition[1] : '%s';
				$name     = isset($condition[2]) ? $condition[2] : $column;
				$required = isset($condition[3]) ? $condition[3] : TRUE;

				$this->valueRequired[] = $required;
				$this->valueNames[]    = $name;

				if($alias)
				{
					$column = $alias . '.' . $column;
				}

				if(!$required && !isset($namedArgs[$name]))
				{
					// var_dump($name, $namedArgs);
					continue;
				}

				if(is_array($value))
				{
					if(count($value) == 2)
					{
						$value = call_user_func($value);
					}
				}

				if(preg_match('/\?/', $value))
				{
					$this->valueWrappers[] = $wrapper;
				}

				if(isset($namedArgs[$name]) && is_array($namedArgs[$name]))
				{
					$value = '(' . implode(
						', '
						, array_fill(0, count($namedArgs[$name]), $value)
					) . ')';
				}

				$strings[] = sprintf(
					'%s %s %s'
					, $column
					, $compare
					, $value
				);
			}
		}

		foreach($this->joins() as $join)
		{
			list($sub, $superCol, $subCol, $subType) = $join;

			$this->valueWrappers += array_merge($this->valueWrappers, $sub->valueWrappers);

			$sub->valueWrappers = [];
		}

		return implode(
			sprintf("\n  %s ", $operator)
			, $strings
		);
	}
}
