<?php
namespace SeanMorris\Ids\Mysql;
class CountStatement extends SelectStatement
{
	protected
		$column
		, $unique
	;

	public function aliasColumns()
	{
		$this->order = [];
		$this->columns = ['count'];

		$countAlias = $this->aliasColumnName('count', $this->alias);

		return [sprintf('COUNT(DISTINCT %s.%s) AS %s', $this->alias, $this->column, $countAlias)];
	}

	public function joins()
	{
		$cleanJoin = function($join) use(&$cleanJoin)
		{
			list($sub, $superCol, $subCol, $type) = $join;
			
			$sub = clone $sub;

			$sub->columns = [];
			$sub->order   = [];
			$sub->joins   = array_map($cleanJoin, $sub->joins);

			return [$sub, $superCol, $subCol, $type];
		};

		return $this->joins = array_map($cleanJoin, $this->joins);
	}

	public function generate()
	{
		return parent::generate();
	}
}