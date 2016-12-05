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

		return [sprintf('COUNT(%s.%s) AS %s', $this->alias, $this->column, $countAlias)];
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

	public function generate()
	{
		return parent::generate();
	}
}