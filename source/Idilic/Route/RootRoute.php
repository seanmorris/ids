<?php
namespace SeanMorris\Ids\Idilic\Route;
/**
 * Application core library.
 * 	Passing a package with no command will return the root directory of that package.
 * Switches:
 * 	-d= or --domain=
 * 		Set the domain for the current command.
 */
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
				// $args = $router->path()->consumeNodes();

				$packageName = $commands->$packageName;
				$packageName = str_replace('/', '\\', $packageName);

				$routes = $packageName . '\Idilic\Route\RootRoute';
			}

			$command = $packageName;
		}

		try
		{
			$package = \SeanMorris\Ids\Package::get($packageName);
		}
		catch(\Exception $e)
		{
			$candidatePackages = array_values(array_filter(
				\SeanMorris\Ids\Meta::classes()
				, function($class) use($command){

					if(!preg_match('/Idilic\\\Route\\\RootRoute$/', $class))
					{
						return FALSE;
					}

					$methods = get_class_methods($class);

					$methods = array_filter(
						$methods
						, function($method) use($class)
						{
							$method = new \ReflectionMethod(
								$class, $method
							);
							return $method->isPublic();
						}
					);

					// var_dump($command, $methods);

					if(!in_array($command, $methods))
					{
						return FALSE;
					}

					return TRUE;
				}
			));

			if(count($candidatePackages) == 1)
			{
				$candidatePackage = \SeanMorris\Ids\Package::fromClass(
					current($candidatePackages)
				);

				\SeanMorris\Ids\Idilic\Cli::error(
					\SeanMorris\Ids\Idilic\Cli::color(
						'Using package '
							. $candidatePackage->cliName()
						, 'black'
						, 'lightGrey'
					)
					. PHP_EOL
				);
				if($routes = current($candidatePackages))
				{
					$routeParts  = explode('\\', $routes);
					$packageName = sprintf('%s/%s', ...$routeParts);
					array_unshift($args, $command);
				}
			}
			else if(!count($candidatePackages))
			{
				throw new \Exception(sprintf(
					'No packages supply command "%s".'
					, $command
				));

				return;
			}
			else
			{
				$answer = \SeanMorris\Ids\Idilic\Cli::multiQuestion(
					sprintf(
						'These packages supply "%s", which one should run?'
						, $command
					)
					, $candidatePackages
				);

				if(!isset($candidatePackages[$answer]))
				{
					return;
				}

				if($routes = $candidatePackages[$answer])
				{
					$routeParts  = explode('\\', $routes);
					$packageName = sprintf('%s/%s', ...$routeParts);
					array_unshift($args, $command);
				}
			}

			try
			{
				$package = \SeanMorris\Ids\Package::get($packageName);
			}
			catch(\Exception $e)
			{
				var_dump($e);
				\SeanMorris\Ids\Log::logException($e);
			}
		}

		if(!$args || !class_exists($routes))
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

	/** Execute tests for given package. */

	public function runTests($router)
	{
		if(!$packageList = $router->path()->consumeNodes())
		{
			$packageList = \SeanMorris\Ids\Package::listPackages();
		}

		if(function_exists('xdebug_set_filter'))
		{
			xdebug_set_filter(
				XDEBUG_FILTER_CODE_COVERAGE
				, XDEBUG_PATH_BLACKLIST
				, [realpath(IDS_ROOT . '/vendor/')]
			);
		}

		$coverageOpts = XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE;

		$relReports = [];
		$reports    = [];
		$report     = [];

		while($packageName = array_shift($packageList))
		{
			$packageName = str_replace('/', '\\', $packageName);

			$package   = \SeanMorris\Ids\Package::get($packageName);
			$namespace = $package->packageSpace();
			$tests     = $package->testDir();

			$testsFound = [];

			while($tests->check() && $test = $tests->read())
			{
				$testsFound[] = $test;
			}

			$testsFound = array_reverse($testsFound);

			foreach($testsFound as $test)
			{
				if(!preg_match('/(\w+?Test)\.php/', $test->name(), $m))
				{
					continue;
				}

				$testClass = $namespace . '\\Test\\' . $m[1];
				$test = new $testClass;

				if(function_exists('xdebug_start_code_coverage'))
				{
					xdebug_start_code_coverage($coverageOpts);
				}

				$test->run(new \TextReporter());

				if(function_exists('xdebug_stop_code_coverage'))
				{
					xdebug_stop_code_coverage(FALSE);
				}

				echo PHP_EOL;
			}
		}

		if(function_exists('xdebug_stop_code_coverage'))
		{
			xdebug_stop_code_coverage();

			$reportFile = '/tmp/coverage-report.json';
			$reports    = xdebug_get_code_coverage();
			$relReports = [];

			foreach($reports as $filename => &$lines)
			{
				$relativePath = substr($filename, strlen(IDS_ROOT));

				foreach ($lines as $line => &$executed)
				{
					if($executed == 1)
					{
						continue;
					}

					if($executed == -2)
					{
						unset($lines[$line]);
						continue;
					}

					$executed = 0;
				}

				$relReports[$relativePath] = $lines;
			}

			$report = ['coverage' => $relReports];

			file_put_contents(
				$reportFile
				, json_encode($report, JSON_PRETTY_PRINT)
			);
		}
	}

	public function applySchemas($router)
	{
		$args = $router->path()->consumeNodes();

		$packages = \SeanMorris\Ids\Package::listPackages();

		$real = array_shift($args);

		foreach($packages as $packageName)
		{
			printf(
				'Checking "%s"...' . PHP_EOL
				, $packageName
			);
			$package = $this->_getPackage($packageName);

			$result = $package->applySchema($real);

			if(!$result)
			{
				printf(
				"\t"
				. 'No schema changes detected for "%s".' . PHP_EOL
					, $packageName
				);
			}

			if(!$real && $result)
			{
				while(1)
				{
					printf(
						'Changes  for "%s" have not yet been applied.' . PHP_EOL
						, $packageName
					);

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
	}

	/** Applies the stored schema for a package. */
	public function applySchema($router)
	{
		$args = $router->path()->consumeNodes();

		if(!$packageName = array_shift($args))
		{
			return 'No package supplied.';
		}

		$real = array_shift($args);

		$package = $this->_getPackage($packageName);

		$result = $package->applySchema($real);

		if(!$result)
		{
			echo 'No schema changes detected.' . PHP_EOL;
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

	/** Stores the current schema for a package to JSON. */
	public function storeSchema($router)
	{
		$args = $router->path()->consumeNodes();

		if(!$packageName = array_shift($args))
		{
			return;
		}

		$package = $this->_getPackage($packageName);

		$changes = $package->storeSchema();

		if(!$changes)
		{
			return 'No changes detected.' . PHP_EOL;
		}
		else
		{
			return 'Changes stored.' . PHP_EOL;
		}
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

	/** Output a list of all model types within a project, including dependencies. */
	public function listModels()
	{
		$classes = \SeanMorris\Ids\Meta::classes('SeanMorris\Ids\Model');

		$classes = array_map(
			function($class)
			{
				return str_replace('\\', '/', $class);
			}
			, $classes
		);

		print implode(PHP_EOL, $classes) . PHP_EOL;
	}

	/** Stores models to site-specific directory. */

	public function storeModels($router)
	{
		$rootPackage = \SeanMorris\Ids\Package::getRoot();
		$siteDir     = $rootPackage->localSiteDir();
		$modelDir    = $siteDir->dir('models');

		if(!$modelDir->check())
		{
			$modelDir = $siteDir->create('models', 0777, TRUE);
		}

		$classes = \SeanMorris\Ids\Meta::classes('SeanMorris\Ids\Model');

		print "Exporting models...\n";

		$files = [];

		foreach($classes as $class)
		{
			if(!$class::table() || $class == 'SeanMorris\PressKit\Cache')
			{
				continue;
			}

			$filename = sprintf(
				'%s.csv'
				, preg_replace('/\\\\/', '___', $class)
			);

			$file = $modelDir->file($filename);

			try
			{
				$models = $class::generateByNull();
			}
			catch(\Exception $e)
			{
				continue;
			}

			$header = false;

			$out = fopen($file->name(), 'w');

			$written = false;

			foreach($models() as $model)
			{
				if(!$model)
				{
					continue;
				}

				$arModel = $model->unconsume(0);

				if(!$header)
				{
					fputcsv($out, array_keys($arModel));
					$header = true;
				}

				foreach($arModel as &$value)
				{
					if(is_object($value))
					{
						$value = $value->id;
					}
				}

				fputcsv($out, array_values($arModel));

				$written = true;
			}

			if(!$written)
			{
				unlink($file->name());
			}
			else
			{
				printf("\t%s\n", $file->name());

				$files[] = $file->name();
			}
		}

		proc_open(
			sprintf(
				'tar -zcvf %s/models.tar.gz %s'
				, escapeshellarg($siteDir)
				// , date('Y-m-d-h-i-s')
				, escapeshellarg($modelDir)
			)
			, [
				['pipe', 'r']
				, ['pipe', 'w']
				, ['pipe', 'w']
			]
			, $pipes
		);
	}

	/** Export models to CSV. */

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

		foreach($models() as $model)
		{
			if(!$model)
			{
				continue;
			}

			$arModel = $model->unconsume();

			if(!$header)
			{
				fputcsv($out, array_keys($arModel));
				$header = true;
			}

			foreach($arModel as &$value)
			{
				if(is_object($value))
				{
					$value = $value->id;
				}
			}

			fputcsv($out, array_values($arModel));
		}
	}

	/** Import models from CSV via STDIN. */

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

			$model = new $modelClass;

			$line = array_map(function($cell){
				if($cell === '')
				{
					return NULL;
				}
				return $cell;
			}, $line);

			$model->consume($line, TRUE);

			try
			{
				$model->create();
			}
			catch(\Exception $exception)
			{
				\SeanMorris\Ids\Log::logException($exception);
				$model->update();
			}

			var_dump($model->id);
		}
	}

	public function _getPackage($packageName)
	{
		$packageName = \SeanMorris\Ids\Package::name($packageName);

		return \SeanMorris\Ids\Package::get($packageName);
	}

	public function package($router)
	{
		$packageName = $router->path()->consumeNode();

		return \SeanMorris\Ids\Package::get($packageName);
	}

	/** List all installed packages. */

	public function listPackages($router)
	{
		$switches = $router->request()->switches();

		$packages = implode(
			PHP_EOL
			, \SeanMorris\Ids\Package::listPackages()
		);

		if($switches['s'] ?? FALSE)
		{
			return $packages;
		}
		else
		{
			return preg_replace('/\//', '\\', $packages);
		}
	}

	/** Link static information from all packages to main project. */

	public function link($router)
	{
		\SeanMorris\Ids\Linker::link();
		$inheritance = (object)\SeanMorris\Ids\Linker::inheritance();

		$rootPackage = \SeanMorris\Ids\Package::getRoot();

		$rootPackage->setVar('linker:inheritance', $inheritance, 'global');

		print json_encode($inheritance, JSON_PRETTY_PRINT);
	}

	/** Compile CSS, JS and anything else that AssetManager can handle. */

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

		\SeanMorris\Ids\Log::debug(sprintf('Using asset manager "%s"', get_class($assetManager)));

		$assetManager::buildAssets($package);
	}

	/** Print this help text. */

	public function help($router)
	{
		$classes = \SeanMorris\Ids\Meta::classes();

		$classes = array_filter($classes, function($class) {
			return preg_match('/\\\\Idilic\\\\Route\\\\RootRoute$/', $class);
		});

		foreach($classes as $className)
		{
			$package = \SeanMorris\Ids\Package::fromClass($className);

			print \SeanMorris\Ids\Idilic\Cli::color('Package: ', 'white')
				. \SeanMorris\Ids\Idilic\Cli::color($package->cliName(), 'yellow')
				. PHP_EOL
				. PHP_EOL;

			$reflection = new \ReflectionClass($className);
			$methods = $reflection->getMethods();

			$methods = array_filter($methods, function($method) {
				if($method->isStatic())
				{
					return FALSE;
				}

				if(!$method->isPublic())
				{
					return FALSE;
				}

				return TRUE;
			});

			if($summary = $reflection->getDocComment())
			{
				// print "\t" . $summary;
				// print PHP_EOL;
				// print PHP_EOL;
			}

			if(!$methods)
			{
				print PHP_EOL;
				continue;
			}

			print \SeanMorris\Ids\Idilic\Cli::color('Commands: ', 'white') . PHP_EOL;


			foreach($methods as $method)
			{
				if($method->name[0] == '_')
				{
					continue;
				}

				if($doc = $method->getDocComment())
				{
					preg_match('/\/\*\*\s+(.+?)\s*?\*\//is', $doc, $groups);

					printf(
						"\t%s -\t%s\n"
						, \SeanMorris\Ids\Idilic\Cli::color($method->name, 'green')
						, $groups[1]
					);
				}
				else
				{
					printf(
						"\t%s\n"
						, \SeanMorris\Ids\Idilic\Cli::color($method->name, 'green')
					);
				}

			}

			print PHP_EOL;
		}

		return;

		$packages = \SeanMorris\Ids\Package::listPackages($router->contextGet('composer'));
		$idsPackage = \SeanMorris\Ids\Package::get('SeanMorris\Ids');

		foreach($packages as $packageName)
		{
			try
			{
				$package = \SeanMorris\Ids\Package::get($packageName);
			}
			catch (\Exception $e)
			{
				continue;
			}

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

	/** Set a variable in a given package. */

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

	/** Read a variable from a given package. */

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

	/** Execute PHP with the project initialized. */

	public function run($router)
	{
		$args = $router->path()->consumeNodes();

		if($code = array_shift($args))
		{
			eval($code);
		}
	}

	/** Remove a variable from a given package. */

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

	/** Start a RePL. */



	/** Print project info. */

	public function info()
	{
		$env = (object) getenv();
		$project   = $env->PROJECT_FULLNAME ?? NULL;;
		$databases = \SeanMorris\Ids\Settings::read('databases');

		$dbs = NULL;

		if($databases)
		{
			foreach($databases as $name => $database)
			{
				$dbs .= sprintf(
					"%sDatabase: %s\n%sSchema: %s\n%sHost: %s\n%sPort: %s\n%sUser: %s\n"
					, str_repeat(' ', 4)
					, $name
					, str_repeat(' ', 6)
					, $database->database
					, str_repeat(' ', 8)
					, $database->hostname
					, str_repeat(' ', 8)
					, $database->port
					, str_repeat(' ', 8)
					, $database->username
				);
			}
		}

		return sprintf(
		'        Project: %s'
			, $project
		) . PHP_EOL
		. sprintf(<<<EOT
Root Package: %s
      Domain: %s
     RootDir: %s
  Entrypoint: %s
   Log Level: %s

EOT
			, \SeanMorris\Ids\Package::getRoot()->packageSpace()
			, $_SERVER['HTTP_HOST']
			, IDS_ROOT
			, \SeanMorris\Ids\Settings::read('entrypoint')
			, \SeanMorris\Ids\Settings::read('logLevel')
		) . $dbs;
	}

	/** Start a REPL. */

	public function repl()
	{
		\SeanMorris\Ids\Repl::init();
	}

	/** Watch project logs. Ctrl+c to exit. */

	public function log()
	{
		ignore_user_abort(true);
		popen(sprintf(
			'less -RSXMI +G %s/temporary/log.txt', 'w'
			, \SeanMorris\Ids\Package::getRoot()
		));
	}

	/** Print the current environment variables available. */

	public function env()
	{
		print \SeanMorris\Ids\Log::dump(getenv());
	}

	/** Print a given configuration. */

	public function config($router)
	{
		$args   = $router->path()->consumeNodes();
		$config = \SeanMorris\Ids\Settings::read(...$args);

		if(method_exists($config, 'dumpStruct'))
		{
			$config = $config->dumpStruct();
		}

		\SeanMorris\Ids\Log::debug(
			'Args: ', $args, 'Config: ', $config
		);

		return $config;
	}

	public function pack()
	{
		$ids  = \SeanMorris\Ids\Package::get('SeanMorris\Ids');
		$root = \SeanMorris\Ids\Package::getRoot();
		$name = preg_replace('/\W/', '_', $root->name());

		$pharName = $name . '.phar';
		$pharPath = $root->packageDir() . $pharName;

		if(file_exists($pharPath))
		{
			unlink($pharPath);
		}

		$phar = new \Phar($pharPath);

		$phar->setDefaultStub(
			'source/Idilic/idilic'
			, 'public/index.php'
		);

		$phar->startBuffering();
		$files = $phar->buildFromDirectory(
			$root->packageDir()
			, '/^((?!.git).)*$/'
		);

		$phar->stopBuffering();
		$phar->compressFiles(\Phar::GZ);

		chmod($pharPath, 0777);

		return print_r($pharPath, 1);
	}

	public static function parseDoc()
	{

	}

	/** Access docker. */

	public function docker($router)
	{
		$args = $router->path()->consumeNodes();
		$args = implode(' ', $args);

		echo `docker inspect --format '{{ json .}}' $args`;
	}

	public function babel($router)
	{
		$args = $router->path()->consumeNodes();
		$args = implode(' ', $args);

		echo `env`;
	}

	/** Run a queue listener daemon. */

	public function queueDaemon($router)
	{
		[$class,] = $router->path()->consumeNodes();

		if(!is_subclass_of($class, '\SeanMorris\Ids\Queue'))
		{
			throw new Exception(sprintf(
				"Provided class does not inherit: %s\n\t%s"
				, '\SeanMorris\Ids\Queue'
				, $class
			));
		}

		$class::listen();
	}

	/** Run an SQL query*/
	public function sql($router)
	{
		$database = \SeanMorris\Ids\Database::get('main');
		$output = fopen('php://stdout', 'w');
		$input  = fopen('php://stdin', 'r');

		echo "ctrl+c to exit.\n>";

		while($queryString = fgets($input))
		{
			try{
				$query = $database->prepare($queryString);

				$query->execute();
			}
			catch(\Exception $e)
			{
				echo "\n>";
				\SeanMorris\Ids\Log::logException($e);
				continue;
			}

			$headers = FALSE;

			while($row = $query->fetch(\PDO::FETCH_ASSOC))
			{
				if(!$headers)
				{
					fputcsv($output, $headers = array_keys($row), "\t");
				}

				fputcsv($output, $row, "\t");
			}
			echo "\n>";
		}
	}



	/** Generate documentation for a given package.*/

	public function document($router)
	{
		$args = $router->path()->consumeNodes();

		if(!$packageName = array_shift($args))
		{
			return 'No package supplied.';
		}

		return json_decode(json_encode(
			\SeanMorris\Ids\Documentor::docs($packageName)
		), TRUE);
	}
}
