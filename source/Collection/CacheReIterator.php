<?php
namespace SeanMorris\Ids\Collection;

class CacheReIterator extends \CachingIterator implements \Iterator
{
	protected $cacheIterator, $rewound = FALSE;

	public function __construct($iterator)
	{
		parent::__construct(
			new NoRewindIterator($iterator)
			, MyCachingIterator::FULL_CACHE
			| MyCachingIterator::TOSTRING_USE_KEY
		);

		$this->cacheIterator = new ArrayIterator($this->getCache());
	}

	public function rewind()
	{
		$this->cacheIterator = new ArrayIterator($this->getCache());

		if($this->rewound = !!$this->cacheIterator->count())
		{
			$this->cacheIterator->rewind();
			$this->rewound = TRUE;
		}

		parent::rewind();
	}

	public function current()
	{
		if($this->rewound && $this->cacheIterator->valid())
		{
			return $this->cacheIterator->current();
		}

		return parent::current();
	}

	public function key()
	{
		if($this->rewound && $this->cacheIterator->valid())
		{
			return $this->cacheIterator->key();
		}

		return parent::key();
	}

	public function next()
	{
		if($this->rewound && $this->cacheIterator->valid())
		{
			return $this->cacheIterator->next();
		}

		return parent::next();
	}

	public function valid()
	{
		if($this->rewound && $this->cacheIterator->valid())
		{
			return $this->cacheIterator->valid();
		}

		$this->rewound = FALSE;

		return parent::valid();
	}
}

// function generator() : Generator {
// 	foreach(range(0,9) as $x)
// 	{
// 		yield (object) [ 'int' => $x];//rand(0,10) ];
// 	}
// };

// $iterator = new MyCachingIterator(generator());

// foreach($iterator as $k=>$v){ printf('%d -> %d'."\n",$k,$v->int); if($k > 4) break; };
// foreach($iterator as $k=>$v){ printf('%d -> %d'."\n",$k,$v->int); };
