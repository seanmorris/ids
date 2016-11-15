<?php
namespace SeanMorris\Ids\Storage\Mysql;
class InsertStatement extends Statement
{
	public function assemble()
	{
		$format = "INSERT INTO %s (%s) VALUES (%s)";
		$columns = [];
		$tokens = [];

		foreach($this->columns as $column)
		{
			$columns[] = $column;

			if(isset($this->wrappers[$column]))
			{
				if($this->hasReplacements($this->wrappers[$column]))
				{
					$tokens[] = sprintf(
						$this->wrappers[$column]
						, '?'
					);
				}
				else
				{
					$tokens[] = $this->wrappers[$column];
				}
			}
			else
			{
				$tokens[] = '?';
			}
		}

		$queryString = sprintf(
			$format
			, $this->table
			, implode(', ', $columns)
			, implode(', ', $tokens)
		);

		return $queryString;
	}

	public function execute(...$args)
	{
		$result = parent::execute(...$args);

		if($result)
		{
			$database = \SeanMorris\Ids\Database::get('main');

			if($insertId = $database->lastInsertId())
			{
				return $insertId;
			}

			return $result;
		}

		return false;
	}
}