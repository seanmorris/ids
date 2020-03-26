<?php
namespace SeanMorris\Ids;

use UnexpectedValueException;
use \SeanMorris\Ids\WrappedMethod;
use SeanMorris\Ids\Inject\FactoryMethod;
use Iterator, IteratorAggregate, AppendIterator, CallbackFilterIterator, Countable;

use FilterIterator;

use \SeanMorris\Ids\Collection\CacheReIterator;
use \SeanMorris\Ids\Collection\RankOuterIterator;

use \SeanMorris\Ids\___\BaseCollection;

(new class { use Injectable; })::inject([
	'RankOuterIterator' => RankOuterIterator::class
	, 'Store' => \SplObjectStorage::class
	, 'Type'  => NULL

], \SeanMorris\Ids\___\BaseCollection::class);

abstract class Collection extends BaseCollection implements IteratorAggregate, Countable
{
	protected
		$index , $ranked = [] , $tagged = [];

	protected static
		$Type, $Store, $RankOuterIterator
		, $Map, $Filter, $Nullable;

	public function __construct(traversible ...$seedLists)
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
		$ofType   = static::$Type;
		$scalars  = FALSE;
		$nullable = FALSE;

		$reflection = new \ReflectionFunction($callback);

		if($returnType = $reflection->getReturnType())
		{
			$ofType   = $returnType->getName();
			$scalars  = $returnType->isBuiltin();
			$nullable = $returnType->allowsNull();
		}

		$collectionClass = self::of($ofType);

		$mapper = WrappedMethod::wrap($callback);
		$mapped = $collectionClass::inject([

			'RankOuterIterator' => $collectionClass::$RankOuterIterator::inject([
				'map'    => $mapper
			])

			, 'Nullable' => $nullable

		]);

		$collection = new $mapped;

		$collection->index  =& $this->index;
		$collection->ranked =& $this->ranked;
		$collection->tagged =& $this->tagged;

		return $collection;

		// $of = static::$Type;

		// $scalars  = FALSE;
		// $nullable = FALSE;

		// $reflection = new \ReflectionFunction($callback);

		// if($returnType = $reflection->getReturnType())
		// {
		// 	$of = $returnType->getName();

		// 	$scalars  = $returnType->isBuiltin();
		// 	$nullable = $returnType->allowsNull();
		// }

		// $newCollection = NULL;

		// if($scalars)
		// {
		// 	$newCollection = [];
		// }
		// else
		// {
		// 	$collectionClass = self::of($of);
		// 	$newCollection = new $collectionClass;
		// }

		// if(is_array($newCollection))
		// {
		// 	foreach($this->ranked as $rank => $items)
		// 	{
		// 		foreach($items as $item)
		// 		{
		// 			if($nullable)
		// 			{
		// 				if(NULL !== $result = $callback($item, $rank))
		// 				{
		// 					$newCollection[] = $result;
		// 				}

		// 				continue;
		// 			}

		// 			$newCollection[] = $callback($item, $rank);
		// 		}
		// 	}
		// }
		// else
		// {
		// 	foreach($this->ranked as $rank => $items)
		// 	{
		// 		foreach($items as $item)
		// 		{
		// 			if($nullable)
		// 			{
		// 				if($result = $callback($item, $rank))
		// 				{
		// 					$newCollection->add($result);
		// 				}

		// 				continue;
		// 			}

		// 			$newCollection->add($callback($item, $rank));
		// 		}
		// 	}
		// }

		// return $newCollection;
	}

	public function filter($callback)
	{
		$newCollection = new static;

		foreach($this as $item)
		{
			if($callback($item, $rank))
			{
				$newCollection->add($item, $rank);
			}
		}

		return $newCollection;
	}

	public function reduce($callback)
	{
		$reduced = NULL;

		foreach($this->ranked as $rank => $items)
		{
			foreach($items as $item)
			{
				$reduced = $callback($reduced, $item);
			}
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

    	if(static::$Nullable)
    	{
    		return new CallbackFilterIterator($ranks, function($v) {

				return $v !== NULL;

			});
    	}

    	return $ranks;
    }

}
