<?php
namespace SeanMorris\Ids;

use \UnexpectedValueException;
use \SeanMorris\Ids\WrappedMethod;
use SeanMorris\Ids\Inject\FactoryMethod;
use \CallbackFilterIterator, \Countable, \Traversible, ArrayAccess;
use \Iterator, \IteratorAggregate, \AppendIterator, SplObjectStorage;

use \SeanMorris\Ids\Collection\Driver;
use \SeanMorris\Ids\Collection\RankIterator;

use \SeanMorris\Ids\___\BaseCollection;

(new class { use Injectable; })::inject([
	'FilterIterator' => CallbackFilterIterator::CLASS
	, 'RankIterator' => RankIterator::CLASS
	, 'Driver' => Driver::CLASS
	, 'store'  => SplObjectStorage::CLASS
	, 'Type'   => NULL
	, 'lookup' => NULL
], BaseCollection::CLASS);

abstract class Collection extends BaseCollection
	implements IteratorAggregate, ArrayAccess, Countable
{
	protected
		$driver, $derivedFrom = NULL, $readOnly = FALSE;

	protected static
		$Type, $store, $Driver, $lookup, $index = []
		, $Map, $Nullable, $Filter, $FilterIterator;

	public function __construct()
	{
		$this->initInjections();

		$this->driver = new static::$Driver;
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

		return $this->driver->has($item);
	}

	public function add(...$items)
	{
		if($this->derivedFrom)
		{
			return $this->derivedFrom->add(...$items);
		}

		foreach($items as $item)
		{
			if($this->has($item))
			{
				continue;
			}

			$this->driver->add($item);
		}
	}

	public function remove(...$items)
	{
		if($this->derivedFrom)
		{
			return $this->derivedFrom->remove(...$items);
		}

		foreach($items as $item)
		{
			if(!$this->driver->has($item))
			{
				continue;
			}

			$this->driver->remove($item);
		}
	}

	public function count()
	{
		if($this->derivedFrom)
		{
			return $this->derivedFrom->count();
		}

		return $this->driver->count();
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

		$mapper = WrappedMethod::wrap($callback);

		$Driver = $collectionClass::$Driver::inject([
			'map'  => $mapper
		]);

		$MappedClass = $collectionClass::inject([
			'Nullable' => $nullable
			, 'Driver' => $Driver
		]);

		$mapped = new $MappedClass;

		$mapped->driver = $Driver::derive($this->driver);
		$mapped->derivedFrom = $this;

		return $mapped;
	}

	public function filter($callback)
	{
		$FilteredClass = static::inject([
			'Filter' => WrappedMethod::wrap($callback)
		]);

		$filtered = new $FilteredClass;
		$filtered->driver = static::$Driver::derive($this->driver);

		$filtered->derivedFrom = $this;

		return $filtered;
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

	public function getIterator()
	{
		$iterator = $this->driver->getIterator();

		if(static::$Filter)
		{
			$iterator = new static::$FilterIterator($iterator, static::$Filter);
		}

		if(static::$Nullable)
		{
			$iterator = new static::$FilterIterator($iterator, function($v) {

				return $v !== NULL;

			});
		}

		return $iterator;
	}

	public function offsetExists($key)
	{
		$lookup = static::lookup($key);

		if($this->driver->has($result))
		{
			return $result;
		}

		return FALSE;
	}

	public function offsetGet($key)
	{
		$result = static::lookup($key);

		if($this->driver->has($result))
		{
			return $result;
		}

		return FALSE;
	}

	public static function lookup($key)
	{
		if(!$lookup = static::$lookup)
		{
			throw new \BadMethodCallException(sprintf(
				'Cannot lookup instace of "%s" on %s without a injected lookup function (%s).'
				, static::$Type
				, __FUNCTION__
				, __CLASS__
				, get_called_class()
			));
		}

		if(!is_scalar($key))
		{
			throw new UnexpectedValueException(
				'Offset must be scalar.'
			);
		}

		if(static::$index[ $key ] ?? FALSE)
		{
			return static::$index[ $key ];
		}

		if(!$result = $lookup($key))
		{
			return FALSE;
		}

		static::$index[ $key ] = $result;
		static::$store[ $result ] = $key;

		return $result;
	}

	public function offsetSet($offset, $value)
	{
		throw new DomainException(sprintf(
			'Cannot set define/redefine keys on %s (%s).'
			, __FUNCTION__
			, __CLASS__
			, get_called_class()
		));
	}

	public function offsetUnset($offset)
	{
		throw new DomainException(sprintf(
			'Cannot set define/redefine keys on %s (%s).'
			, __FUNCTION__
			, __CLASS__
			, get_called_class()
		));
	}
}
