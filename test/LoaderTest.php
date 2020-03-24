<?php
namespace SeanMorris\Ids\Test;

class LoaderTest extends \UnitTestCase
{
	public function setUp()
	{
		\SeanMorris\Ids\Loader::define([

			\App\singletonObject::class      => \SeanMorris\Ids\Loader::one((object)[
				'lmao' => 'wow'
			])
			, \App\SomeInjection::class      => new class{ static $a = 1; }
			, \App\SomeOtherInjection::class => new class {}
			, \App\InjectableClass::class    => new class { use \SeanMorris\Ids\Injectable; }
		]);
	}

	public function testLoader()
	{
		var_dump( \App\singletonObject() );
		var_dump( \App\singletonObject() );
		var_dump( \App\singletonObject() );
		var_dump( \App\singletonObject() );

		\App\InjectableClass::inject([

			'x' => \App\InjectedClass::class,
			'y' => \App\singletonObject::class,

		], \App\InjectedClass::class);

		$x = new \App\InjectedClass;

		var_dump($x);
		var_dump($x->x);
		var_dump($x->y);
		var_dump($x->x->y);
		var_dump($x->x->x->y);

		// var_dump($x->x->x);

		// \App\InjectableClass::inject([
		// 	'x' => \App\OtherInjectedClass::class
		// ], \App\OtherInjectedClass::class);

		// $x = new \App\OtherInjectedClass;

		// var_dump($x);
		// var_dump($x->x);
		// var_dump($x->x->x);
	}
}
