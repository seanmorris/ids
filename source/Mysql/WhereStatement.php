<?php
namespace SeanMorris\Ids\Mysql;
abstract class WhereStatement extends Statement
{
	protected
		$namedParams = false
		, $conditions = []
		, $valueWrappers = []
		, $valueNames = []
		, $valueRequired = []
	;

	public function execute(...$args)
	{
		$queryStartTime = microtime(TRUE);

		$argsUsed = [];

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

				$argsUsed[] = $argName;
				
				$argsDist[] = $args[0][$argName];
			}

			$args = $argsDist;
		}
		else
		{
			$queryObject = $this->prepare();
		}
				

		$args = array_map(
			function($value, $wrapper)
			{
				if($wrapper)
				{
					if(is_array($value))
					{
						return array_map(
							function($v) use($wrapper) {
								return sprintf($wrapper, $v);
							}
							, $value
						);
					}
					return sprintf($wrapper, $value);
				}

				return $value;
			}
			, $args
			, $this->valueWrappers
		);
				

		\SeanMorris\Ids\Log::debug('Args:', $args);
		
		if($nonscalar = array_filter($args, function($a) {
			return !is_scalar($a)
				&& !is_null($a)
				&& !is_array($a);
		})) {
			\SeanMorris\Ids\Log::debug('Nonscalar argument supplied.');
			\SeanMorris\Ids\Log::debug($nonscalar);
			\SeanMorris\Ids\Log::trace();
			die;	
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

		\SeanMorris\Ids\Log::debug(
			'Queries Run: ' . static::$queryCount
			, sprintf('Query ran in %f seconds.', $queryTime)
			, sprintf('Total time waiting on database: %f seconds.', parent::$queryTime)
		);
		
		$errorCode = $queryObject->errorCode();

		if($errorCode !== '00000')
		{
			\SeanMorris\Ids\Log::error($queryObject->errorInfo());
			\SeanMorris\Ids\Log::trace();
			die;
		}
		
		return $queryObject;
	}

	public function conditions($conditions)
	{
		// \SeanMorris\Ids\Log::debug($this->conditions);

		if(is_numeric(key($conditions)))
		{
			$conditions = ['AND' => $conditions];
		}

		if($this->conditions)
		{
			foreach($conditions as $key => $subconditions)
			{
				if(isset($this->conditions[$key]))
				{
					foreach($subconditions as $subkey => $condition)
					{
						$this->conditions[$key][] = $condition;
					}
				}
				else
				{
					$this->conditions[$key] = [$condition ?? NULL];
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
			}

			if($key === 'AND' || $key === 'OR')
			{
				if(is_numeric($key))
				{
					$key = 'AND';
				}

				$strings[] = sprintf(
					'%s'
					, $this->conditionTree($condition, $key, $alias, $namedArgs)
				);
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
				$this->valueNames[] = $name;

				// \SeanMorris\Ids\Log::trace();
				\SeanMorris\Ids\Log::debug(array(
					'column'    => $column
					, 'value'   => $value
					, 'compare' => $compare
					, 'name'    => $name
				));

				if($alias)
				{
					$column = $alias . '.' . $column;
				}

				if(!$required && !isset($namedArgs[$name]))
				{
					// var_dump($name, $namedArgs);
					continue;
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

				if(!preg_match('/\?/', $value))
				{
					continue;
				}

				$this->valueWrappers[] = $wrapper;
			}
		}

		return implode(
			sprintf(' %s ', $operator)
			, $strings
		);
	}
}