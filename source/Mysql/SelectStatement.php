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
		, $index = []
		, $order = []
		, $limit = NULL
		, $offset = NULL
		, $conditions = []
		, $groups = []
		, $joins  = []
		, $superior
		, $preArgs = []
	;

	const RETURNS = TRUE;

	public function __construct($table)
	{
		$this->master = $this;

		return parent::__construct($table);
	}

	protected function databaseTier()
	{
		if(!static::isAltered($this->table)
			&& $database = \SeanMorris\Ids\Database::get('slave')
		){
			return 'slave';
		}

		return parent::databaseTier();
	}

	public function tableAlias()
	{
		if(!$this->alias && $this->master)
		{
			$this->alias = $this->master->aliasTableName($this->table, $this);
		}
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

		$columnString = implode(PHP_EOL . ', ', $this->aliasColumns());

		$tableString = $this->table;

		if($this->alias)
		{
			$tableString .= ' AS ' . $this->alias;
		}

		if($this->index)
		{
			$tableString .= sprintf(
				"\n  FORCE INDEX(`%s`)"
				, implode('`,`', $this->index)
			);
		}

		//$tableString .= '--';

		$conditionString = $this->conditionTree(
			$this->conditions
			, 'AND'
			, $this->alias
			, $namedArgs
		);

		$conditionString = '('
			. (trim($conditionString)
				? $conditionString
				: 1
			)
			. ')';

		$joinOrderStrings = [];

		foreach($this->joins() as $join)
		{
			list($sub, $superCol, $subCol, $type) = $join;

			$superCol = $this->alias . '.' . $superCol;

			// $sub->conditions(['AND' => [
			// 	[$subCol => $superCol]
			// ]]);

			list($subTableString, $subColString, $joinConditionString) = $sub->assembleJoin($type, $namedArgs, $superCol, $subCol);

			if($sub->tableAliases)
			{
				$this->master->tableAliases += $sub->tableAliases;
			}

			if($sub->columnAliases)
			{
				$this->master->columnAliases += $sub->columnAliases;
			}

			//$subTableString .= "\t" . 'ON ' . '((' . $joinConditionString . '))';
			//$subTableString .= "--";

			$this->valueRequired += array_merge($this->valueRequired, $sub->valueRequired);
			$this->valueNames += array_merge($this->valueNames, $sub->valueNames);

			if(!$this->master instanceOf CountStatement)
			{
				$columnString .= ($columnString && $subColString)
					? (PHP_EOL . ', ' . $subColString)
					: NULL;
			}

			if(!$conditionString)
			{
				$conditionString = 1;
			}

			$joinConditionString = '('
				. trim($joinConditionString)
					? $joinConditionString
					: 1
				. ')'
			;

			$tableString .= ' ' . $subTableString;

			if($joinConditionString)
			{
				// if($tableString)
				// {
				// 	$tableString .= PHP_EOL . '    AND ';
				// }

				// $tableString .= $joinConditionString;

				if($conditionString)
				{
					$conditionString .= PHP_EOL . '    AND ';
				}

				$conditionString .= $joinConditionString;
			}


			foreach($sub->order as $column => $direction)
			{
				$columnName = $sub->aliasColumnName($column, $sub->alias);

				$joinOrderStrings[] = $columnName . ' ' . $direction;
			}
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
		}

		$orderStrings = array_merge($orderStrings, $joinOrderStrings);

		if($orderStrings)
		{
			$orderString = PHP_EOL . PHP_EOL . 'ORDER BY ' . implode(', ', $orderStrings);
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

		$groupCols = [];

		if($this->groups)
		{
			foreach($this->groups as $group)
			{
				$groupCols[] = sprintf(
					'%s.%s'
					, $this->alias
					, $group
				);
			}
		}

		$groupString = NULL;

		if($groupCols && !($this instanceOf CountStatement))
		{
			$groupString = PHP_EOL . PHP_EOL . ' GROUP BY '
				. implode(',', $groupCols)
				. ' ';
		}


		return sprintf(
			"SELECT\n%s\n\nFROM\n%s\n\nWHERE\n%s"
			, $columnString
			, $tableString
			, $conditionString ?: 1
		) . $groupString . $orderString . $limitString;
	}

	protected function assembleJoin($type = null, $args = null, $col, $joinCol)
	{
		$columnString = implode(PHP_EOL . ', ', $this->aliasColumns());

		//\SeanMorris\Ids\Log::debug($this->conditions);

		$tableString = $this->table;

		$conditionString = NULL;

		$joinString = sprintf(
			PHP_EOL . '%sJOIN %s as %s'
			, $type
				? $type . ' '
				: null
			, $tableString
			, $this->alias
		);

		$joinString .= sprintf(
			PHP_EOL . '  ON (%s = %s.%s)'
			, $col
			, $this->alias
			, $joinCol
		);

		foreach($this->joins() as $join)
		{
			list($sub, $superCol, $subCol, $subType) = $join;

			$superCol = $this->alias . '.' . $superCol;

			// $sub->conditions(['AND' => [
			// 	[$subCol => $superCol]
			// ]]);

			list($subJoinString, $subColString, $subConditionString) = $sub->assembleJoin($subType, $args, $superCol, $subCol);

			$this->valueRequired += array_merge($this->valueRequired, $sub->valueRequired);
			$this->valueNames += array_merge($this->valueNames, $sub->valueNames);

			if($subColString)
			{
				$columnString .= PHP_EOL . ', ' . $subColString;
			}

			// @TODO: Why is $subConditionString sometimes empty?
			if($subConditionString)
			{
				if(trim($conditionString))
				{
					if($subConditionString)
					{
						$conditionString = sprintf(
							"(%s\n    AND ( %s ))"
							, $conditionString
							, $subConditionString
						);
					}
				}
				else
				{
					$conditionString = $subConditionString;
				}
			}

			$joinString .= ' ' . $subJoinString;
		}

		$localConditionString = $this->conditionTree(
			$this->conditions
			, 'AND'
			, $this->alias
			, $args
		);

		if(trim($conditionString))
		{
			if($localConditionString)
			{
				$conditionString = sprintf(
					"(%s\n    AND ( %s ))"
					, $conditionString
					, $localConditionString
				);

			}
		}
		else
		{
			$conditionString = $localConditionString;
		}

		if(!trim($conditionString))
		{
			$conditionString = 1;
		}

		return [$joinString, $columnString, $conditionString];
	}

	public function join(SelectStatement $join, $superCol, $subCol, $type = null, $operator = null, $superWrapper = null, $subWrapper = null, $tag = null)
	{
		if($join === $this || $join === $this->master)
		{
			throw new \Exception('Idiot.');
		}

		$this->subjugate($join);

		if(!$type)
		{
			$type = 'INNER';
		}

		if(!$this->alias)
		{
			$this->alias = $this->master->aliasTableName($this->table, $this);
		}

		$parentAliases = NULL;
		$current       = $this;
		/*
		while($parent = $current->superior)
		{

		}
		*/

		$join->alias = $join->master->aliasTableName(
			$join->table
			, $join
			, $this->aliasColumnName($superCol, $this->alias)
		);

		$this->joins[] = [$join, $superCol, $subCol, $type, $operator, $superWrapper, $subWrapper];
	}

	public function order($order)
	{
		$this->order = $order;

		return $this;
	}

	public function index($index)
	{
		$this->index = $index;

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
		$join->master   = $this->master;
		$join->superior = $this;
	}

	protected function aliasTableName($tableName, $select = NULL, $tag = NULL)
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

		if($tag)
		{
			$alias = $tag . '__' . $alias;
		}

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

	public function execute(...$args)
	{
		foreach($this->joins() as $join)
		{
			list($sub, $superCol, $subCol, $subType) = $join;

			$this->valueWrappers += array_merge($this->valueWrappers, $sub->valueWrappers);
		}

		return parent::execute(...$args);
	}

	public function fetchColumn(...$args)
	{
		try
		{
			$query = $this->execute(...$args);
		}
		catch(\Exception $e)
		{
			\SeanMorris\Ids\Log::error($e);
			\SeanMorris\Ids\Log::trace();
			die;
		}

		if($col = $query->fetchColumn())
		{
			\SeanMorris\Ids\Log::debug('Fetching column...', $col);
			return $col;
		}

		\SeanMorris\Ids\Log::debug('DONE FETCHING COLUMNS!');
	}

	public function fetch(...$args)
	{
		try
		{
			$query = $this->execute(...$args);
		}
		catch(\Exception $e)
		{
			\SeanMorris\Ids\Log::error($e);
			\SeanMorris\Ids\Log::trace();
			die;
		}

		if($row = $query->fetch())
		{
			\SeanMorris\Ids\Log::debug('Fetching row...', $row);

			return $row;
		}

		\SeanMorris\Ids\Log::debug('DONE FETCHING ROWS!');
	}

	public function generate()
	{
		$queryStartTime = microtime(TRUE);

		$closure = function(...$args) use($queryStartTime)
		{
			$queryObject = $this->execute(...$args);
			// try
			// {
			// }
			// catch(\Exception $e)
			// {
			// 	\SeanMorris\Ids\Log::error($e);
			// 	\SeanMorris\Ids\Log::trace();
			// 	die;
			// }

			$first = FALSE;

			while($row = $queryObject->fetch(\PDO::FETCH_ASSOC))
			{
				if(!$first)
				{
					$first = TRUE;

					$this->complete($queryObject, $queryStartTime, $args);
				}
				\SeanMorris\Ids\Log::debug(
					'Generating row...'
					, $row
					, $this->tableAliases
					, $this->columnAliases
				);

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

				yield $result;
			}

			if(!$first)
			{
				$this->complete($queryObject, $queryStartTime, $args);
			}

			\SeanMorris\Ids\Log::debug('DONE GENERATING ROWS!');
		};

		return $closure;
	}

	protected function complete($queryObject, $queryStartTime, $args)
	{
		$queryTime = microtime(TRUE) - $queryStartTime;

		\SeanMorris\Ids\Log::query('Query executed.', new \SeanMorris\Ids\LogMeta([
			'query'              => $queryObject->queryString
			, 'query_time'       => $queryTime * 1000
			, 'querty_tier'      => $this->databaseTier()
			, 'query_type'       => get_called_class()
			, 'query_args'       => $args
			, 'query_table'      => $this->table
		]));
	}

	public function group(...$groups)
	{
		foreach ($groups as $group)
		{
			$this->groups[] = $group;
		}
	}
}
