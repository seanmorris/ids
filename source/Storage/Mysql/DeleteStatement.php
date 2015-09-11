<?php
namespace SeanMorris\Ids\Storage\Mysql;
class DeleteStatement extends WhereStatement
{
	public function assemble()
	{
		$args = func_get_args();

		$namedArgs = [];

		if(isset($args[0]))
		{
			$namedArgs = $args[0];
		}
		
		$conditionString = $this->conditionTree(
			$this->conditions
			, 'AND'
			, $this->table
			, $namedArgs
		);

		return sprintf(
			'DELETE FROM %s WHERE %s'
			, $this->table
			, $conditionString ?: 1
		);
	}
}