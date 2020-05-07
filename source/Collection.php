<?php
namespace SeanMorris\Ids;

use \UnexpectedValueException;
use \SeanMorris\Ids\WrappedMethod;
use SeanMorris\Ids\Inject\FactoryMethod;
use \Iterator, \IteratorAggregate, \AppendIterator;
use \CallbackFilterIterator, \Countable, \Traversible;

use \SeanMorris\Ids\Collection\RankIterator;
use \SeanMorris\Ids\Collection\CacheReIterator;

use \SeanMorris\Ids\___\BaseCollection;

(new class { use Injectable; })::inject([

	'FilterIterator' => CallbackFilterIterator::class
	, 'RankIterator' => RankIterator::class

	, 'Store' => \SplObjectStorage::class
	, 'Type'  => NULL

], \SeanMorris\Ids\___\BaseCollection::class);

abstract class Collection extends BaseCollection implements IteratorAggregate, Countable
{
	protected
		$index , $ranked = [] , $derivedFrom = NULL, $readOnly = FALSE;

	protected static
		$Type, $Store, $Rank, $RankIterator
		, $Map, $Filter, $Nullable, $FilterIterator;

	public function __construct(iterable ...$seedLists)
	{
		$this->initInjections();

		$this->index = new static::$Store;

		foreach($seedLists as $seedList)
		{
			foreach($seedList as $seed)
			{
				$this->add($seed);
			}
		}
	}

	public static function of(string $type, string $name = null)
	{
		return static::inject(['Type' => $type], $name);
	}

	public function has($item)
	{
		if(!is_object($item))
		{
			throw new UnexpectedValueException(sprintf(
				'Collection of type %s cannot contain scalar values.'
				, get_called_class()
			));
		}

		if(static::$Type && !($item instanceof static::$Type))
		{
			throw new UnexpectedValueException(sprintf(
				'Collection of type %s cannot contain object of type %s.'
				, get_class($item)
				, get_called_class()
			));
		}

		return $this->index->contains($item);
	}

	public function add(...$items)
	{
		if($this->derivedFrom)
		{
			$this->derivedFrom->add(...$items);
			return;
		}

		foreach($items as $item)
		{
			if($this->has($item))
			{
				continue;
			}

			$rank = $this->rank($item);

			if(!isset($this->ranked[$rank]))
			{
				$this->ranked[$rank] = new static::$Store;
			}

			$this->ranked[$rank][$item] = $item;

			$this->index[$item] = (object)['rank' => $rank];
		}
	}

	public function remove(...$items)
	{
		if($this->derivedFrom)
		{
			$this->derivedFrom->remove(...$items);
			return;
		}

		foreach($items as $item)
		{
			if(!$this->has($item))
			{
				continue;
			}

			$index = $this->index[$item];

			unset($this->ranked[$index->rank][$item]);

			unset($this->index[$item]);
		}
	}

	public function count()
	{
		if($this->derivedFrom)
		{
			$this->derivedFrom->count();
			return;
		}

		return array_sum(array_map('count', $this->ranked));
	}

	public function map($callback)
	{
		$collectionClass = static::class;

		$scalars  = FALSE;
		$nullable = FALSE;

		$reflection = new \ReflectionFunction($callback);

		if($returnType = $reflection->getReturnType())
		{
			$collectionClass = self::of($returnType->getName());

			$scalars  = $returnType->isBuiltin();
			$nullable = $returnType->allowsNull();
		}

		$mapped = $collectionClass::inject([

			'RankIterator' => $collectionClass::$RankIterator::inject([
				'map' => WrappedMethod::wrap($callback)
			])

			, 'Nullable' => $nullable

		]);

		$collection = new $mapped;

		$collection->derivedFrom = $this;

		$collection->index  =& $this->index;
		$collection->ranked =& $this->ranked;

		return $collection;
	}

	public function filter($callback)
	{
		$filtered = static::inject([
			'Filter' => WrappedMethod::wrap($callback)
		]);

		$collection = new $filtered;

		$collection->derivedFrom = $this;

		$collection->index  =& $this->index;
		$collection->ranked =& $this->ranked;

		return $collection;
	}

	public function reduce($callback)
	{
		$reduced = NULL;

		foreach($this as $item)
		{
			$reduced = $callback($reduced, $item);
		}

		return $reduced;
	}

	protected function rank($item)
	{
		return static::$Rank
			? static::$Rank($item)
			: 0;
	}

	public function getIterator()
    {
    	$ranks = new static::$RankIterator(...$this->ranked);

		if(static::$Filter)
		{
			$ranks = new static::$FilterIterator($ranks, static::$Filter);
		}

		if(static::$Nullable)
		{
			$ranks = new static::$FilterIterator($ranks, function($v) {

				return $v !== NULL;

			});
		}

    	return $ranks;
    }
}
