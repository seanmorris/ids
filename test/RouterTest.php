<?php
namespace SeanMorris\Ids\Test;
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

			$routes = new \SeanMorris\Ids\Test\Route\RootRoute;
			$router = new \SeanMorris\Ids\Router($request, $routes);
			$response = $router->route();

			$this->assertEqual(
				$response
				, $value
				, sprintf(
					"Unexpected response for %s\n%s"
					, $uri
					, $response
				)
			);
		}
	}
}
