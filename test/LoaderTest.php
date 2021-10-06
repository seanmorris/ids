<?php
namespace SeanMorris\Ids\Test;

use \SeanMorris\Ids\Loader;
use \___\Heap, SplHeap, SplMaxHeap;

use \___\GlobalAliasedDatetime;
use \SeanMorris\Ids\___\ScopedAliasedDatetime;

use \SeanMorris\Ids\Inject\FactoryMethod;
use \SeanMorris\Ids\Inject\SingletonMethod;

class LoaderTest extends \UnitTestCase
{
	public function testClassAlias()
	{
		Loader::define([ GlobalAliasedDatetime::class => \Datetime::class ]);

		$datetime = new GlobalAliasedDatetime;

		$this->assertTrue(
			$datetime instanceof \Datetime
			, 'GlobalAliasedDatetime is not an instance of Datetime.'
		);

		Loader::define([ ScopedAliasedDatetime::class => \Datetime::class ]);

		$datetime = new ScopedAliasedDatetime;

		$this->assertTrue(
			$datetime instanceof \Datetime
			, 'ScopedAliasedDatetime is not an instance of Datetime.'
		);
	}

	public function testClassOverride()
	{
		Loader::define([
			Heap::class => SplHeap::class
		]);

		Loader::define([
			Heap::class => SplMaxHeap::class
		]);

		$this->assertTrue(
			is_a(Heap::class, SplHeap::class, TRUE)
			, 'Heap::class is not an SplHeap.'
		);

		$this->assertTrue(
			is_a(Heap::class, SplMaxHeap::class, TRUE)
			, 'Heap::class is not an SplMaxHeap.'
		);
	}

	public function testFactoryMethod()
	{
		$personFactory = FactoryMethod::wrap(function($name){
			return (object)['name' => $name];
		});

		$this->assertEqual(
			'Alice'
			, $personFactory('Alice')->name
			, 'Factory did not return "Alice".'
		);

		$this->assertEqual(
			'Bob'
			, $personFactory('Bob')->name
			, 'Factory did not return "Bob".'
		);

		$this->assertEqual(
			'Charlie'
			, $personFactory('Charlie')->name
			, 'Factory did not return "Charlie".'
		);
	}

	public function testSingletonMethod()
	{
		$singletonFactory = SingletonMethod::wrap((object)[
			'a'   => 1
			, 'b' => 2
			, 'c' => 3
		]);

		$x = $singletonFactory();
		$y = $singletonFactory();

		$this->assertTrue($x, 'Singleton did not return the instance.');
		$this->assertTrue($y, 'Singleton did not return the instance.');

		$this->assertEqual($x, $y, 'Singleton did not return the same instance twice.');
	}

	// public function testLoader()
	// {
		// Loader::define([

		// 	'___\singletonObject' => Loader::one((object)[
		// 		'lmao' => 'wow'
		// 	])

		// 	, \___\SomeInjection::class      => new class {}

		// 	, \___\SomeOtherInjection::class => new class {static $a = 1; }

		// 	, \___\InjectableClass::class    => new class {
		// 		use \SeanMorris\Ids\Injectable;
		// 	}
		// ]);
		// \SeanMorris\Ids\Collection::of(
		// 	\stdClass::class
		// 	, \___\StdCollection::class
		// );

		// \SeanMorris\Ids\Collection::of(
		// 	\Datetime::class
		// 	, \___\DatetimeCollection::class
		// );

		// $std  = (object) [ 'x' => 'y' ];
		// $date = new \Datetime;

		// $stdCollection  = new \___\StdCollection;
		// $dateCollection = new \___\DatetimeCollection;

		// $stdCollection->add($std);
		// $dateCollection->add($date);

		// $stdCollection->add($date);
		// $dateCollection->add($std);

		// print_r($stdCollection);
		// print_r($dateCollection);

		// var_dump( \___\singletonObject() );
		// var_dump( \___\singletonObject() );
		// var_dump( \___\singletonObject() );
		// var_dump( \___\singletonObject() );

		// \___\InjectableClass::inject([

		// 	'x' => \___\InjectedClass::class,
		// 	'y' => \___\singletonObject::class,

		// ], \___\InjectedClass::class);

		// $x = new \___\InjectedClass;

		// var_dump($x);
		// var_dump($x->x);
		// var_dump($x->y);
		// var_dump($x->x->y);
		// var_dump($x->x->x->y);

		// var_dump($x->x->x);

		// \___\InjectableClass::inject([
		// 	'x' => \___\OtherInjectedClass::class
		// ], \___\OtherInjectedClass::class);

		// $x = new \___\OtherInjectedClass;

		// var_dump($x);
		// var_dump($x->x);
		// var_dump($x->x->x);
	// }
}
