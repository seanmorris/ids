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

	public function runTests($router)
	{
		$args = $router->path()->consumeNodes();

		if(!$packageName = array_shift($args))
		{
			return 'No package specified.';
		}

		$packageName = str_replace('/', '\\', $packageName);

		foreach($args as $test)
		{
			echo PHP_EOL;
			$testClass = $packageName . '\\Test\\' . $test;
			$test = new $testClass;
			$test->run(new \TextReporter());
		}
	}

	public function applySchema($router)
	{
		$args = $router->path()->consumeNodes();

		if(!$packageName = array_shift($args))
		{
			echo "No package supplied.\n";
			return;
		}

		$real = array_shift($args);

		$package = $this->_getPackage($packageName);

		$result = $package->applySchema($real);

		if(!$result)
		{
			echo "No schema changes detected.\n";
		}

		if(!$real && $result)
		{
			while(1)
			{
				echo 'Changes have not yet been applied.' . PHP_EOL;

				foreach($result as $query)
				{
					print $query;
					print PHP_EOL;
				}

				$answer = \SeanMorris\Ids\Idilic\Cli::question(
					'Apply the above changes to the schema? (y/n)'
				);

				if($answer === 'y')
				{
					$package->applySchema(true);
					break;
				}

				if($answer === 'n')
				{
					break;
				}
			}
		}
	}

	public function storeSchema($router)
	{
		$args = $router->path()->consumeNodes();

		if(!$packageName = array_shift($args))
		{
			return;
		}

		$package = $this->_getPackage($packageName);

		return $package->storeSchema();
	}

	public function _getPackageFromClass($class)
	{
		$splitClass = preg_split('/\//', $class);
		
		$packageName = '';
		$packageName .= array_shift($splitClass);
		$packageName .= '/';
		$packageName .= array_shift($splitClass);

		return $packageName;
	}

	public function exportModels($router)
	{
		$args = $router->path()->consumeNodes();

		if(!$modelClass = array_shift($args))
		{
			return;
		}

		$packageName = $this->_getPackageFromClass($modelClass);

		$generator = array_shift($args);

		$package = $this->_getPackage($packageName);
		$modelClass = preg_replace('/\//', '\\', $modelClass);

		$models = $package->exportModels($modelClass, $generator, $args);
		$header = false;
		$out = \SeanMorris\Ids\Idilic\Cli::outHandle();

		foreach($models as $model)
		{
			$arModel = $model->unconsume();

			if(!$header)
			{
				fputcsv($out, array_keys($arModel));
				$header = true;
			}

			fputcsv($out, array_values($arModel));
		}
	}

	public function importModels($router)
	{
		$args = $router->path()->consumeNodes();

		if(!$modelClass = array_shift($args))
		{
			return;
		}

		$in = \SeanMorris\Ids\Idilic\Cli::inHandle();
		
		$header = [];

		$modelClass = preg_replace('/\//', '\\', $modelClass);

		if(!class_exists($modelClass))
		{
			return;
		}

		while($line = fgetcsv($in))
		{
			if(!$header)
			{
				$header = $line;
				continue;
			}

			if(count($line) !== count($header))
			{
				continue;
			}

			$line = array_combine($header, $line);

			
		}
	}

	public function _getPackage($packageName)
	{
		$packageName = \SeanMorris\Ids\Package::name($packageName);

		return \SeanMorris\Ids\Package::get($packageName);		
	}

	public function listPackages($router)
	{
		return implode(
			PHP_EOL
			, \SeanMorris\Ids\Package::listPackages($router->contextGet('composer'))
		);
	}
}