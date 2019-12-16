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

		]);
	}

	public function testLoader()
	{
		$x = new \App\SomeInjection;
	}
}
