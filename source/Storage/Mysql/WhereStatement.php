<?php
namespace SeanMorris\Ids\Storage\Mysql;
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
					return sprintf($wrapper, $value);
				}

				return $value;
			}
			, $args
			, $this->valueWrappers
		);

		\SeanMorris\Ids\Log::debug('Args:', $args);
		
		if($nonscalar = array_filter($args, function($a){return !is_scalar($a) && !is_null($a);}))
		{
			\SeanMorris\Ids\Log::debug('Nonscalar argument supplied.');
			\SeanMorris\Ids\Log::debug($nonscalar);
			\SeanMorris\Ids\Log::trace();
			die;	
		}

		$queryObject->execute($args);
		static::$queryCount++;
		\SeanMorris\Ids\Log::debug('Queries Run: ' . static::$queryCount);
		
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
					$this->conditions[$key] = [$condition];
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

		foreach($tree as $key => $condition)
		{
			if(!is_array($condition))
			{
				throw new \Exception('Malformed condition.' . PHP_EOL . print_r($tree, 1));
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
				$column = key($condition);
				$value = current($condition);
				$compare  = isset($condition[0]) ? $condition[0] : '=';
				$wrapper  = isset($condition[1]) ? $condition[1] : '%s';
				$name     = isset($condition[2]) ? $condition[2] : $column;
				$required = isset($condition[3]) ? $condition[3] : TRUE;

				$this->valueRequired[] = $required;
				$this->valueNames[] = $name;

				/*
				\SeanMorris\Ids\Log::debug(array(
					'column' => $column
					, 'value' => $value
					, 'name' => $name
				));
				*/

				if($alias)
				{
					$column = $alias . '.' . $column;
				}

				if(!$required && !isset($namedArgs[$name]))
				{
					// var_dump($name, $namedArgs);
					continue;
				}

				$this->valueWrappers[] = $wrapper;

				$strings[] = sprintf(
					'%s %s %s'
					, $column
					, $compare
					, $value
				);
			}
		}

		return implode(
			sprintf(' %s ', $operator)
			, $strings
		);
	}
}