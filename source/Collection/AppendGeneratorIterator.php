<?php
namespace SeanMorris\Ids\Collection;

use \Generator;

class AppendGeneratorIterator
{
	protected static $GeneratorWrapper = NoRewindIterator::class;

	public function append(Iterator $iterator)
	{
		if($iterator instanceof Generator)
		{
			$iterator = new static::$GeneratorWrapper($iterator);
		}

		parent::append($iterator);
	}
}
