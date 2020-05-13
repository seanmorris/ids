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

(new class {
	use Injectable;
	protected $ranked = [], $store, $iterator;
	protected static $Rank, $Store, $FlatIterator, $map, $filter;
})::inject([
	'FlatIterator' => FlatIterator::CLASS
	, 'Store'      => \SplObjectStorage::CLASS
], BaseDriver::CLASS);

class Driver extends BaseDriver implements IteratorAggregate, Countable
{
	public function __construct()
	{
		$this->initInjections();

		$this->store = new static::$Store;

		$rc = new \ReflectionClass(BaseDriver::CLASS);

		Log::debug('Driver initialized');
	}

	protected function rank($item)
	{
		return static::$Rank
			? static::$Rank($item)
			: 0;
	}

	public function has($item)
	{
		return $this->store->contains($item);
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

	public function count()
	{
		return array_sum(array_map('count', $this->ranked));
	}

	public function getIterator()
	{
		if($this->iterator)
		{
			return $this->iterator;
		}

		$iteratorClass = static::$FlatIterator::inject([
			'map' => static::$map
		]);

		return $this->iterator = new $iteratorClass(...$this->ranked);
	}

	public static function derive($from)
	{
		$new = new static;

		$new->store  =& $from->store;
		$new->ranked =& $from->ranked;

		return $new;
	}
}
