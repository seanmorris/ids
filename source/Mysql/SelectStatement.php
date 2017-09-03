<?php
namespace SeanMorris\Ids\Mysql;
class SelectStatement extends WhereStatement
{
	protected
		$table
		, $alias
		, $aliases
		, $tableAliases
		, $columnAliases = []
		, $aliasedSelects = []
		, $master
		, $order = []
		, $limit = NULL
		, $offset = NULL
		, $conditions = []
		, $joins = []
		, $superior
		, $preArgs = []
	;

	public function __construct($table)
	{
		$this->master = $this;

		return parent::__construct($table);
	}

	public function tableAlias()
	{
		return $this->alias;
	}

	public function countStatement($column, $unique = FALSE)
	{
		$count = new CountStatement($this->table);

		foreach($this as $property => $value)
		{
			$count->{$property} = $value;
		}

		$count->column = $column;
		$count->unique = $unique;

		$count->limit = NULL;
		$count->offset = NULL;

		return $count;
	}

	public function aliasColumns()
	{
		$alias = $this->alias;

		return array_map(
			function($column)
			{
				$columnString = $this->alias
					. '.'
					. $column;

				if(isset($this->wrappers[$column]))
				{
					$columnString = sprintf(
						$this->wrappers[$column]
						, $this->alias
							. '.'
							. $column
					);
				}

				return $columnString
					. ' AS '
					. $this->master->aliasColumnName(
						$column
						, $this->alias
					)
				;
			}
			, $this->columns
		);
	}

	public function joins()
	{
		return $this->joins;
	}

	public function assemble()
	{
		$args = func_get_args();

		$namedArgs = [];

		if(isset($args[0]))
		{
			$namedArgs = $args[0];
		}

		if(!$this->alias)
		{
			$this->alias = $this->master->aliasTableName($this->table, $this);
		}

		$columnString = implode(', ', $this->aliasColumns());

		$tableString = $this->table;

		if($this->alias)
		{
			$tableString .= ' AS ' . $this->alias;
		}

		//$tableString .= '--';

		$conditionString = $this->conditionTree(
			$this->conditions
			, 'AND'
			, $this->alias
			, $namedArgs
		);

		$conditionString = '('
			. ($conditionString
				? $conditionString
				: 1
			)
			. ')'
		;

		foreach($this->joins() as $join)
		{
			list($sub, $superCol, $subCol, $type) = $join;

			$superCol = $this->alias . '.' . $superCol;

			$sub->conditions(['AND' => [
				//[$subCol => $superCol]
			]]);

			list($subTableString, $subColString, $joinConditionString) = $sub->assembleJoin($type, $namedArgs, $superCol, $subCol);

			//$subTableString .= "\t" . 'ON ' . '((' . $joinConditionString . '))';
			//$subTableString .= "--";

			$this->valueRequired += array_merge($this->valueRequired, $sub->valueRequired);
			$this->valueNames += array_merge($this->valueNames, $sub->valueNames);

			$columnString .= ($columnString && $subColString)
				? (', ' . $subColString)
				: NULL;

			if(!$conditionString)
			{
				$conditionString = 1;
			}

			$joinConditionString = '('
				. $joinConditionString
					? $joinConditionString
					: 1
				. ')'
			;

			if($joinConditionString)
			{
				if($conditionString)
				{
					$conditionString .= PHP_EOL . 'AND ';
				}

				$conditionString .= $joinConditionString;
			}

			$tableString .= ' ' . $subTableString;
		}

		$orderStrings = [];
		$orderString = null;

		if($this->order)
		{
			foreach($this->order as $column => $direction)
			{
				$columnName = $this->aliasColumnName($column, $this->alias);

				$orderStrings[] = $columnName . ' ' . $direction;
			}

			if($orderStrings)
			{
				$orderString = PHP_EOL . PHP_EOL . 'ORDER BY ' . implode(', ', $orderStrings);
			}
		}

		$limitString = NULL;

		if($this->limit)
		{
			$limitString = PHP_EOL . PHP_EOL . sprintf('LIMIT %d', $this->limit);

			if($this->offset)
			{
				$limitString .= sprintf(' OFFSET %d', $this->offset);
			}
		}

		$limitString = NULL;

		if($this->limit)
		{
			$limitString = PHP_EOL . PHP_EOL . sprintf('LIMIT %d', $this->limit);

			if($this->offset)
			{
				$limitString .= sprintf(' OFFSET %d', $this->offset);
			}
		}

		return sprintf(
			"SELECT\n%s\n\nFROM\n%s\n\nWHERE\n%s"
			, $columnString
			, $tableString
			, $conditionString ?: 1
		) . $orderString . $limitString;
	}

	protected function assembleJoin($type = null, $args = null, $col, $joinCol)
	{
		$columnString = implode(', ', $this->aliasColumns());

		// \SeanMorris\Ids\Log::debug($this->conditions);

		$tableString = $this->table;

		$conditionString = $this->conditionTree(
			['AND' => $this->conditions]
			, null
			, $this->alias
			, $args
		);

		$joinString = sprintf(
			PHP_EOL . '%sJOIN %s as %s'
			, $type
				? $type . ' '
				: null
			, $tableString
			, $this->alias
		);

		$joinString .= sprintf(' ON (%s = %s.%s)', $col, $this->alias, $joinCol);

		foreach($this->joins as $join)
		{
			list($sub, $superCol, $subCol, $subType) = $join;

			$superCol = $this->alias . '.' . $superCol;

			$sub->conditions(['AND' => [
				//[$subCol => $superCol]
			]]);

			list($subJoinString, $subColString, $subConditionString) = $sub->assembleJoin($subType, $args, $superCol, $subCol);

			$this->valueRequired += array_merge($this->valueRequired, $sub->valueRequired);
			$this->valueNames += array_merge($this->valueNames, $sub->valueNames);

			$joinString .= ' ' . $subJoinString;
			$columnString .= ', ' . $subColString;
			// @TODO: Why is $subConditionString sometimes empty?
			if($subConditionString)
			{
				$conditionString = sprintf('( %s ) AND ( %s )', $conditionString, $subConditionString);
			}
		}

		return [$joinString, $columnString, $conditionString];
	}

	public function join(SelectStatement $join, $superCol, $subCol, $type = null, $operator = null, $superWrapper = null, $subWrapper = null)
	{
		if($join === $this || $join === $this->master)
		{
			throw new \Exception('Idiot.');
		}

		if(!$type)
		{
			$type = 'INNER';
		}

		$join->alias = $join->master->aliasTableName($join->table, $join);

		$this->joins[] = [$join, $superCol, $subCol, $type, $operator, $superWrapper, $subWrapper];
	}

	public function order($order)
	{
		$this->order = $order;

		return $this;
	}

	public function limit($limit, $offset = NULL)
	{
		$this->limit = $limit;

		if($offset !== NULL)
		{
			$this->offset = $offset;
		}
	}

	public function subjugate($join)
	{
		$join->master = $this->master;
		$join->superior = $this;
	}

	protected function aliasTableName($tableName, $select = NULL)
	{
		$this->aliasedSelects = [];

		if(FALSE !== $index = array_search($select, $this->aliasedSelects))
		{
			return $tableName . '_' . $index;
		}

		if(isset($this->aliases[$tableName]))
		{
			$this->aliases[$tableName]++;
		}
		else
		{
			$this->aliases[$tableName] = 0;
		}

		$alias = $tableName . '_' . $this->aliases[$tableName];

		$this->tableAliases[$alias] = $tableName;

		if($select)
		{
			$aliasedSelects[] = $select;
		}

		return $alias;
	}

	protected function aliasColumnName($columnName, $tableAlias)
	{
		if(!isset($this->columnAliases[$tableAlias][$columnName]))
		{
			$this->columnAliases[$tableAlias][$columnName] = $tableAlias . '_' . $columnName;
		}

		return $this->columnAliases[$tableAlias][$columnName];
	}

	public function fetchColumn(...$args)
	{
		static $queryObject;

		if(!$queryObject)
		{
			try
			{
				$queryObject = $this->execute(...$args);
			}
			catch(\Exception $e)
			{
				\SeanMorris\Ids\Log::error($e);
				\SeanMorris\Ids\Log::trace();
				die;
			}
		}

		if($col = $queryObject->fetchColumn())
		{
			\SeanMorris\Ids\Log::debug('Fetching column...', $col);
			return $col;
		}

		\SeanMorris\Ids\Log::debug('DONE FETCHING COLUMNS!');		
	}

	public function fetch(...$args)
	{
		static $queryObject;

		if(!$queryObject)
		{
			try
			{
				$queryObject = $this->execute(...$args);
			}
			catch(\Exception $e)
			{
				\SeanMorris\Ids\Log::error($e);
				\SeanMorris\Ids\Log::trace();
				die;
			}
		}

		if($row = $queryObject->fetch())
		{
			\SeanMorris\Ids\Log::debug('Fetching row...', $row);

			return $row;
		}

		\SeanMorris\Ids\Log::debug('DONE FETCHING ROWS!');
	}

	public function generate()
	{
  		$closure = function(...$args)
		{
			try
			{
				$queryObject = $this->execute(...$args);
			}
			catch(\Exception $e)
			{
				\SeanMorris\Ids\Log::error($e);
				\SeanMorris\Ids\Log::trace();
				die;
			}

			while($row = $queryObject->fetch())
			{
				\SeanMorris\Ids\Log::debug('Generating row...', $row);

				$result = [];

				foreach($this->columnAliases as $tableAlias => $columnAliases)
				{
					$tableName = $this->tableAliases[$tableAlias];

					foreach($columnAliases as $columnName => $columnAlias)
					{
						if(array_key_exists($columnAlias, $row))
						{
							$result[$tableName][$tableAlias][$columnName] = $row[$columnAlias];
						}
					}

					if(isset($result[$tableName]))
					{
						$result[$tableName] = array_reverse($result[$tableName]);
					}
				}

				// \SeanMorris\Ids\Log::debug($this->tableAliases, $result);

				yield $result;
			}

			\SeanMorris\Ids\Log::debug('DONE GENERATING ROWS!');
		};

		return $closure;
	}
}
