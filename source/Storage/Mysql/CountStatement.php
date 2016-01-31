<?php
namespace SeanMorris\Ids\Storage\Mysql;
class CountStatement extends SelectStatement
{
	protected
		$column
		, $unique;
	public function aliasColumns()
	{
		$this->order = [];
		
		return [sprintf('COUNT(%s.%s) AS count', $this->alias, $this->column)];
	}

	public function joins()
	{
		return array_map(
			function($join)
			{
				list($sub, $superCol, $subCol, $type) = $join;
				
				$sub = clone $sub;

				$sub->columns = [];

				return [$sub, $superCol, $subCol, $type];
			}
			, $this->joins
		);
	}
}