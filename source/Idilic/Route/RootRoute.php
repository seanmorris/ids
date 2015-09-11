<?php
namespace SeanMorris\Ids\Idilic\Route;
class RootRoute implements \SeanMorris\Ids\Routable
{
	function _dynamic($router)
	{
		$args = $router->path()->consumeNodes();

		$packageName = $router->match();

		if(!$packageName)
		{
			echo "Error: no command/package supplied";
			echo PHP_EOL;

			return;
		}

		$idsPackage = \SeanMorris\Ids\Package::get('SeanMorris/Ids');

		$commands = $idsPackage->getVar('idilic:commands', (object)[]);

		$packageName = str_replace('/', '\\', $packageName);

		$routes = $packageName . '\Idilic\Route\RootRoute';

		if(!class_exists($routes))
		{
			if(isset($commands->$packageName))
			{
				$router->path()->reset();
				$args = $router->path()->consumeNodes();

				$packageName = $commands->$packageName;
				$packageName = str_replace('/', '\\', $packageName);

				$routes = $packageName . '\Idilic\Route\RootRoute';
			}

			if(!class_exists($routes))
			{
				echo "Error: no such package: " . $packageName;
				echo PHP_EOL;
				echo "No Idilic RootRoute: " . $routes;
				echo PHP_EOL;

				return;
			}
		}

		$request = new \SeanMorris\Ids\Request([
			'path' => new \SeanMorris\Ids\Path(...$args)
		]);

		$subRouter = new $router(
			$request
			, new $routes
			, $router
		);

		return $subRouter->route();
	}
}