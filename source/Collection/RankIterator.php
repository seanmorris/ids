<?php
namespace SeanMorris\Ids\Collection;

use \SeanMorris\Ids\Injectable;
use \AppendIterator, \Iterator;

class RankIterator extends AppendIterator
{
	use Injectable;

	protected static $map;

	public function __construct(Iterator ...$iterators)
	{
		parent::__construct();

		if($iterators)
		{
			$this->append(...$iterators);
		}
	}

	public function append(Iterator $iterator, Iterator ...$iterators)
	{
		array_unshift($iterators, $iterator);

		foreach($iterators as $iterator)
		{
			parent::append($iterator);
		}
	}

	public function key()
	{
		if(static::$map)
		{
			return $this->getInnerIterator()->current();
		}

		return $this->getArrayIterator()->key();
	}

	public function current()
	{
		$value = $this->getInnerIterator()->current();

		if(static::$map)
		{
			$mapper = static::$map;
			$value  = $mapper($value, $this->key());
		}

		return $value;
	}
}
