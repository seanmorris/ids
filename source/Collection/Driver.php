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
	}

	public function has($item)
	{
		return $this->store->contains($item);
	}

	public function add($item)
	{
		$this->store[$item] = $item;
	}

	public function remove($item)
	{
		foreach($items as $item)
		{
			$index = $this->store[$item];

			unset($this->store[$item]);
		}
	}

	public function count()
	{
		return count($this->store);
	}

	public static function getIteratorClass()
	{
		return static::$FlatIterator::inject([
			'map' => static::$map
		]);
	}

	public function getIterator() : FlatIterator
	{
		$iteratorClass = static::getIteratorClass();

		return $this->iterator = new $iteratorClass($this->store);
	}

	public static function derive($from)
	{
		$new = new static;

		$new->store =& $from->store;

		return $new;
	}
}
