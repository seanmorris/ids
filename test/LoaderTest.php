<?php
namespace SeanMorris\Ids\Test;

class LoaderTest extends \UnitTestCase
{
	public function setUp()
	{
		\SeanMorris\Ids\Loader::inject([

			\App\SomeInjection::class => new class{
				static $a = 1;
			}

			, \App\SomeOtherInjection::class => new class {
				const INJECTION  = \App\SomeInjection::class;

				const INJECT = [
					'injection' => self::INJECTION
				];
			}

			, \App\InjectableClass::class => new class {
				use \SeanMorris\Ids\Injectable;
			}

		]);
	}

	public function testLoader()
	{
		\App\InjectableClass::inject([

			'x' => \App\InjectedClass::class

		], \App\InjectedClass::class);

		$x = new \App\InjectedClass;

		var_dump($x);

		var_dump($x->x);
		var_dump($x->x->x);

		\App\InjectableClass::inject([

			'x' => \App\InjectedClass2::class

		], \App\InjectedClass2::class);

		$x = new \App\InjectedClass2;

		var_dump($x->x);
		var_dump($x->x->x);
	}
}
