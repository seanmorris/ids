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
			'/bar' => 'subindex',
			'/bar/index' => 'subindex',
			'/bar/more' => 'more stuff',
		];

		foreach($urisToValues as $uri => $value)
		{
			$request = new \SeanMorris\Ids\Request([
				'uri' => $uri
			]);

			$routes = new \SeanMorris\Ids\Test\Route\RootRoute;
			$router = new \SeanMorris\Ids\Router($request, $routes);
			try
			{
				$response = $router->route();
			}
			catch(\SeanMorris\Ids\Http\HttpResponse $e)
			{
				echo "CAUGHT!";
			}

			$this->assertEqual(
				$response
				, $value
				, sprintf(
					"Unexpected response for %s (got '%s', expected '%s')"
					, $uri
					, $response
					, $value
				)
			);
		}
	}
}
