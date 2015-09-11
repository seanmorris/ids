<?php
namespace SeanMorris\Ids\Storage\Mysql;
class SelectStatement extends WhereStatement
{
	protected
		$table
		, $alias
		, $aliases
		, $tableAliases
		, $columnAliases = []
		, $master
		, $order = []
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

	public function assemble()
	{
		$args = func_get_args();

		$namedArgs = [];

		if(isset($args[0]))
		{
			$namedArgs = $args[0];
		}

		$this->alias = $this->master->aliasTableName($this->table);

		$columnString = implode(', ', $this->aliasColumns());

		$tableString = $this->table;

		if($this->alias)
		{
			$tableString .= ' AS ' . $this->alias;
		}

		$conditionString = $this->conditionTree(
			$this->conditions
			, 'AND'
			, $this->alias
			, $namedArgs
		);

		foreach($this->joins as $join)
		{
			list($sub, $superCol, $subCol, $type) = $join;

			$superCol = $this->alias . '.' . $superCol;

			$sub->conditions(['AND' => [
				[$subCol => $superCol]
			]]);

			list($subTableString, $subColString, $joinConditionString) = $sub->assembleJoin($type, $namedArgs);

			$tableString .= ' ' . $subTableString;
			$columnString .= $columnString
				? (', ' . $subColString)
				: NULL;
			if(!$conditionString)
			{
				$conditionString = 1;
			}

			$conditionString .= PHP_EOL . 'AND ' . $joinConditionString;
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
				$orderString = ' ORDER BY ' . implode(', ', $orderStrings);			
			}
		}

		return sprintf(
			"SELECT\n%s\nFROM\n%s\nWHERE\n%s"
			, $columnString
			, $tableString
			, $conditionString ?: 1
		) . $orderString;
	}

	protected function assembleJoin($type = null, $args = null)
	{
		$columnString = implode(', ', $this->aliasColumns());

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

		foreach($this->joins as $join)
		{
			list($sub, $superCol, $subCol, $subType) = $join;

			$superCol = $this->alias . '.' . $superCol;

			$sub->conditions(['AND' => [
				[$subCol => $superCol]
			]]);

			list($subJoinString, $subColString) = $sub->assembleJoin($subType, $args);

			$joinString .= ' ' . $subJoinString;
			$columnString .= ', ' . $subColString;
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

		$join->alias = $join->master->aliasTableName($join->table);

		$this->joins[] = [$join, $superCol, $subCol, $type, $operator, $superWrapper, $subWrapper];
	}

	public function order($order)
	{
		$this->order = $order;

		return $this;
	}

	public function subjugate($join)
	{
		$join->master = $this->master;
		$join->superior = $this;
	}

	protected function aliasTableName($tableName)
	{
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

	public function generate()
	{
  		$closure = function(...$args)
		{
			try{
				$queryObject = $this->execute(...$args);
			} catch(\Exception $e) {
				\SeanMorris\Ids\Log::error($e);
				\SeanMorris\Ids\Log::trace();
				die;
			}

			while($row = $queryObject->fetch())
			{
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

			\SeanMorris\Ids\Log::debug('DONE!');
		};

		return $closure;
	}
}