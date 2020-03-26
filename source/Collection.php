<?php
namespace SeanMorris\Ids;

use \UnexpectedValueException;
use \SeanMorris\Ids\WrappedMethod;
use SeanMorris\Ids\Inject\FactoryMethod;
use \Iterator, \IteratorAggregate, \AppendIterator;
use \CallbackFilterIterator, \Countable, \Traversible;

use FilterIterator;

use \SeanMorris\Ids\Collection\CacheReIterator;
use \SeanMorris\Ids\Collection\RankOuterIterator;

use \SeanMorris\Ids\___\BaseCollection;

(new class { use Injectable; })::inject([
	'CallbackFilterIterator' => CallbackFilterIterator::class
	, 'RankOuterIterator'    => RankOuterIterator::class

	, 'Store' => \SplObjectStorage::class
	, 'Type'  => NULL

], \SeanMorris\Ids\___\BaseCollection::class);

abstract class Collection extends BaseCollection implements IteratorAggregate, Countable
{
	protected
		$index , $ranked = [] , $tagged = [];

	protected static
		$Type, $Store, $RankOuterIterator
		, $CallbackFilterIterator
		, $Map, $Filter, $Nullable;

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
		foreach($items as $item)
		{
			if($this->has($item))
			{
				continue;
			}

			$rank = $this->rank($item);
			$tags = $this->tag($item);

			if(!isset($this->ranked[$rank]))
			{
				$this->ranked[$rank] = new static::$Store;
			}

			$this->ranked[$rank][$item] = $item;

			foreach($tags as $tag)
			{
				if(!isset($this->tagged[$tag]))
				{
					$this->tagged[$tag] = new static::$Store;
				}

				$this->tagged[$tag][$item] = $item;
			}

			$this->index[$item] = (object)['rank' => $rank, 'tags' => $tags];
		}
	}

	public function remove(...$items)
	{
		foreach($items as $item)
		{
			if(!$this->has($item))
			{
				continue;
			}

			$index = $this->index[$item];

			unset($this->ranked[$index->rank][$item]);

			foreach($index->tags as $tag)
			{
				unset($this->tagged[$tag][$item]);
			}

			unset($this->index[$item]);
		}
	}

	public function count()
	{
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

			'RankOuterIterator' => $collectionClass::$RankOuterIterator::inject([
				'map'    => WrappedMethod::wrap($callback)
			])

			, 'Nullable' => $nullable

		]);

		$collection = new $mapped;

		$collection->index  =& $this->index;
		$collection->ranked =& $this->ranked;
		$collection->tagged =& $this->tagged;

		return $collection;
	}

	public function filter($callback)
	{
		$filtered = static::inject([
			'Filter' => WrappedMethod::wrap($callback)
		]);

		$collection = new $filtered;

		$collection->index  =& $this->index;
		$collection->ranked =& $this->ranked;
		$collection->tagged =& $this->tagged;

		return $collection;
	}

	public function reduce($callback, $initial = NULL)
	{
		$reduced = $initial;

		foreach($this as $item)
		{
			$reduced = $callback($reduced, $item);
		}

		return $reduced;
	}

	protected function rank($item)
	{
		return 0;
	}

	protected function tag($item)
	{
		return [];
	}

	public function getIterator()
    {
    	$ranks = new static::$RankOuterIterator(...$this->ranked);


		if(static::$Filter)
		{
			$ranks = new static::$CallbackFilterIterator($ranks, static::$Filter);
		}

		if(static::$Nullable)
		{
			$ranks = new static::$CallbackFilterIterator($ranks, function($v) {

				return $v !== NULL;

			});
		}

    	return $ranks;
    }
}
