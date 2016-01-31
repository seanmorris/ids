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
			echo 'Error: no command/package supplied';
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
		}

		try
		{
			$package = \SeanMorris\Ids\Package::get($packageName);
		}
		catch(\Exception $e)
		{
			printf("Error: Cannot find package/command %s\n", $packageName);
			return;
		}

		if(!$args)
		{
			return $package->packageDir()->name();
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
			$testClass = $packageName . '\\Test\\' . $test;
			$test = new $testClass;
			$test->run(new \TextReporter());
			echo PHP_EOL;
		}
	}

	public function applySchema($router)
	{
		$args = $router->path()->consumeNodes();

		if(!$packageName = array_shift($args))
		{
			echo 'No package supplied.\n';
			return;
		}

		$real = array_shift($args);

		$package = $this->_getPackage($packageName);

		$result = $package->applySchema($real);

		if(!$result)
		{
			echo 'No schema changes detected.\n';
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

	public function remoteJob()
	{
		$job = new \SeanMorris\Kommie\ControlJob;
		$job->start();
	}

	public function countJob()
	{
		$job = new \SeanMorris\Multiota\Test\Count\CountJob;
		$job->start();
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
			, \SeanMorris\Ids\Package::listPackages()
		);
	}

	public function link($router)
	{
		\SeanMorris\Ids\Linker::link();
	}

	public function buildAssets($router)
	{
		$args = $router->path()->consumeNodes();

		if(!$packageName = array_shift($args))
		{
			return;
		}

		$package = \SeanMorris\Ids\Package::get($packageName);
		$assetManager = $package->assetManager();

		if(!$assetManager)
		{
			$assetManager = 'SeanMorris\Ids\AssetManager';
		}

		$assetManager::buildAssets($package);
	}

	public function help($router)
	{
		$packages = \SeanMorris\Ids\Package::listPackages($router->contextGet('composer'));
		$idsPackage = \SeanMorris\Ids\Package::get('SeanMorris\Ids');

		foreach($packages as $packageName)
		{
			$package = \SeanMorris\Ids\Package::get($packageName);

			if(!$help = $package->getVar('idilic:help', [], 'global'))
			{
				continue;	
			}

			print \SeanMorris\Ids\Idilic\Cli::color('Package: ', 'white')
				. \SeanMorris\Ids\Idilic\Cli::color($packageName, 'yellow') . PHP_EOL;

			if(isset($help->summary))
			{
				print "\t" . $help->summary;
				print PHP_EOL;
				print PHP_EOL;
			}

			if(!isset($help->commands))
			{
				continue;
			}

			print \SeanMorris\Ids\Idilic\Cli::color('Commands: ', 'white') . PHP_EOL;

			foreach($help->commands as $command => $commandHelp)
			{
				$commandPackage = $idsPackage->getVar('idilic:commands:' . $command);

				$indicator = NULL;
				
				if($commandPackage)
				{
					$indicator = '*';
				}

				$usage = NULL;

				if(isset($commandHelp->usage))
				{
					printf(
						"\t%s%s %s\n"
						, $indicator
						, \SeanMorris\Ids\Idilic\Cli::color($command, 'green')
						, $commandHelp->usage
					);
				}
				else
				{
					printf(
						"\t%s%s\n"
						, $indicator
						, \SeanMorris\Ids\Idilic\Cli::color($command, 'green')
					);
				}

				if(isset($commandHelp->description))
				{
					printf("\t\t%s\n", $commandHelp->description);
				}

				print "\n";
			}
		}
	}

	public function set($router)
	{
		$args = $router->path()->consumeNodes();

		if(!$packageName = array_shift($args))
		{
			return;
		}

		if(!$var = array_shift($args))
		{
			return;
		}

		if(!$val = array_shift($args))
		{
			return;
		}

		$package = \SeanMorris\Ids\Package::get($packageName);

		return $package->setVar($var, $val);
	}

	public function get($router)
	{
		$args = $router->path()->consumeNodes();

		if(!$packageName = array_shift($args))
		{
			return;
		}

		if(!$var = array_shift($args))
		{
			return;
		}

		$package = \SeanMorris\Ids\Package::get($packageName);

		return $package->getVar($var);
	}

	public function delete($router)
	{
		$args = $router->path()->consumeNodes();

		if(!$packageName = array_shift($args))
		{
			return;
		}

		if(!$var = array_shift($args))
		{
			return;
		}

		$package = \SeanMorris\Ids\Package::get($packageName);

		return $package->deleteVar($var);
	}

	public function apacheConfig($router)
	{
		if(!$domain = $router->request()->switches('d', 'domain', NULL))
		{
			if(file_exists(getenv("HOME") . '/.idilicProfile.json'))
			{
				$userFile = new \SeanMorris\Ids\Storage\Disk\File(
					getenv("HOME") . '/.idilicProfile.json'
				);
				$userSettings = json_decode($userFile->slurp());

				$domain = $userSettings->domain;
			}
		}

		return sprintf(<<<'EOF'
<VirtualHost *:80>
  ServerName %s
  DocumentRoot %s

  <Directory %s>
    Options Indexes FollowSymLinks
    AllowOverride All
    Order allow,deny
    Allow from all
    Require all granted
  </Directory>

  # Possible values: debug, info, notice, warn, error, crit,
  # alert, emerg.
  LogLevel warn
  ErrorLog ${APACHE_LOG_DIR}/error.log
  CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF
			, $domain
			, \SeanMorris\Ids\Settings::read('public')
			, \SeanMorris\Ids\Settings::read('public')
		);
	}

	public function batch($router)
	{
		$job = new \SeanMorris\Multiota\Test\Count\CountJob;
		$job->start();
		/*
		$pool = new \SeanMorris\Multiota\Pool(
			'\SeanMorris\Multiota\DataSource'
			, '\SeanMorris\Multiota\Processor'
		);

		$pool->start();
		*/
		exit;
	}

	public function batchProcess($router)
	{

		$processor = $router->path()->consumeNode();
		$child = $router->path()->consumeNode();
		$max = $router->path()->consumeNode();

		$processor = new $processor($child, $max);

		$processor->spin();
	}
}
