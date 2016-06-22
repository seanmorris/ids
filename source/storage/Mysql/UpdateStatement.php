<?php
namespace SeanMorris\Ids\Storage\Mysql;
class UpdateStatement extends WhereStatement
{
	protected
		$conditions = []
	;

	public function assemble()
	{
		$format = "UPDATE %s SET %s WHERE %s";
		$columns = [];
		$tokens = [];

		foreach($this->columns as $column)
		{
			$columns[] = $column;

			if(isset($this->wrappers[$column]))
			{
				if($this->hasReplacements($this->wrappers[$column]))
				{
					$tokens[] = $column . ' = ' . sprintf(
						$this->wrappers[$column]
						, '?'
					);
				}
				else
				{
					$tokens[] = $column . ' = ' . $this->wrappers[$column];
				}
			}
			else
			{
				$tokens[] = $column . ' = ' . '?';
			}
		}

		$queryString = sprintf(
			$format
			, $this->table
			, implode(', ', $tokens)
			, $this->conditionTree($this->conditions, NULL, $this->table)
		);

		return $queryString;
	}
}