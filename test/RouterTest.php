<?php
namespace SeanMorris\Ids\Test;
chdir(dirname(__FILE__));
$composer = require '../source/init.php';

class RouterTest extends \UnitTestCase
{
	public function testRouting()
	{
		$urisToValues = [
			'/' => 'index',
			'/index' => 'index',
			'/otherPage' => 'not index',
			'/notFound' => FALSE,
			'/foo' => 'subindex',
			'/foo/index' => 'subindex',
			'/foo/more' => 'more stuff',
		];

		foreach($urisToValues as $uri => $value)
		{
			$request = new \SeanMorris\Ids\Request([
				'uri' => $uri
			]);

			$routes = new TestRoute;
			$router = new \SeanMorris\Ids\Router($request, $routes);
			$response = $router->route();

			var_dump($uri);

			$this->assertEqual($response, $value);
		}
	}
}

class TestRoute implements \SeanMorris\Ids\Routable
{
	public
		$routes = [
			'foo' => 'SeanMorris\Ids\Test\OtherTestRoute'
		];
 	function index($router)
	{
		return 'index';
	}

	function otherPage($router)
	{
		return 'not index';
	}
}

class OtherTestRoute implements \SeanMorris\Ids\Routable
{
	function index($router)
	{
		return 'subindex';
	}

	function more($router)
	{
		return 'more stuff';
	}
}

$test = new RouterTest('Testing Unit Test');
$test->run(new \TextReporter());