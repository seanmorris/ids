<?php
namespace SeanMorris\Ids\Collection;

use \Countable;
use \IteratorAggregate;
use \SplObjectStorage;
use \SeanMorris\Ids\Log;
use \SeanMorris\Ids\Injectable;
use \SeanMorris\Ids\Collection;
use \SeanMorris\Ids\Collection\RankIterator as FlatIterator;

use \SeanMorris\Ids\___\BaseDriver;

// (new class {
// 	use Injectable;
// 	protected $ranked = [], $store, $iterator;
// 	protected static $Rank, $Store, $FlatIterator, $map, $filter;
// })::inject([
// 	'FlatIterator' => FlatIterator::CLASS
// 	, 'Store'      => \SplObjectStorage::CLASS
// ], BaseDriver::CLASS);

class RankedDriver extends Driver
{
	protected function rank($item)
	{
		return static::$Rank
			? static::$Rank($item)
			: 0;
	}

	public function add($item)
	{
		$rank = $this->rank($item);

		if(!isset($this->ranked[$rank]))
		{
			$this->ranked[$rank] = new static::$Store;
		}

		$this->ranked[$rank][$item] = $item;

		$this->store[$item] = (object)['rank' => $rank];
	}

	public function remove($item)
	{
		foreach($items as $item)
		{
			$index = $this->store[$item];

			unset($this->ranked[$index->rank][$item]);

			unset($this->store[$item]);
		}
	}

	public function getIterator() : FlatIterator
	{
		$iteratorClass = static::getIteratorClass();

		return $this->iterator = new $iteratorClass($this->store);
	}

	public static function derive($from)
	{
		$new = parent::derive($from);

		$new->ranked =& $from->ranked;

		return $new;
	}
}
