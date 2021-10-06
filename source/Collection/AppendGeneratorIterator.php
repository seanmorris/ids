<?php
namespace SeanMorris\Ids\Collection;

use \Closure, \Generator,\AppendIterator, \NoRewindIterator;

class AppendGeneratorIterator extends AppendIterator
{
	public function __construct(...$initial)
	{
		parent::__construct();

		array_map([$this, 'append'], $initial);
	}

	protected static $GeneratorWrapper = NoRewindIterator::class;

	public function append($iterator)
	{
		if($iterator instanceof Closure)
		{
			$first = $iterator();

			if(is_object($first) && $first instanceof \Generator)
			{
				$iterator = new static::$GeneratorWrapper($first);
			}
			else if($first !== NULL)
			{
				throw new Exception('Closure passed does not yield or return NULL!');
			}


			$iterator = new static::$GeneratorWrapper($iterator);
		}

		parent::append($iterator);
	}
}
