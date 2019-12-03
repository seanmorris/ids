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
		$args = $router->path()->consumeNodes();

		while($packageName = array_shift($args))
		{
			$packageName = str_replace('/', '\\', $packageName);
			$package = $this->_getPackage($packageName);
			$tests = $package->testDir();

			while($test = $tests->read())
			{
				if(!preg_match('/(\w+?Test)\.php/', $test->name(), $m))
				{
					continue;
				}
				$testClass = $packageName . '\\Test\\' . $m[1];
				$test = new $testClass;
				$test->run(new \TextReporter());
				echo PHP_EOL;
			}
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

		$rootPackage->setVar('linker:inheritance', $inheritance);

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

	/** Print a minimal apache config for a given domain. */

	public function apacheConfig($router)
	{
		if(!$domain = $router->request()->switches('d', 'domain', NULL))
		{
			if(file_exists(getenv("HOME") . '/.idilicProfile.json'))
			{
				$userFile = new \SeanMorris\Ids\Disk\File(
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

	/** Multiota specific. */

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

	/** Multiota specific. */

	public function batchProcess($router)
	{

		$processor = $router->path()->consumeNode();
		$child = $router->path()->consumeNode();
		$max = $router->path()->consumeNode();
		$timeout = $router->path()->consumeNode();

		$processor = new $processor($child, $max, $timeout);

		$processor->spin();
	}

	/** Multiota specific. */

	public function countJob()
	{
		$job = new \SeanMorris\Multiota\Test\Count\CountJob(
			'SeanMorris\Multiota\RemotePool'
			, [
				'servers' => ['localhost']
			]
		);
		$job->start();
	}

	/** Multiota specific. */

	public function capitalizeJob()
	{
		$job = new \SeanMorris\Multiota\Job(
			'SeanMorris\Multiota\Test\Capitalize\CapitalizeProcessor'
			, 'SeanMorris\Multiota\RemotePool'
			, [
				'servers' => ['thewhtrbt.com', 'buzzingbeesalon.com']
			]
		);
		$job->start();
	}

	/** Multiota specific. */

	public function letterCountMap()
	{
		$job = new \SeanMorris\Multiota\Job(
			'SeanMorris\Multiota\Test\LetterCount\Mapper'
			//, 'SeanMorris\Multiota\RemotePool'
			, [
				'servers' => ['seantop', 'localhost']
			]
		);
		$job->start();
	}

	/** Multiota specific. */

	public function letterCountReduce()
	{
		$job = new \SeanMorris\Multiota\Job(
			'SeanMorris\Multiota\Test\LetterCount\Reducer'
			//, 'SeanMorris\Multiota\RemotePool'
			, [
				'servers' => ['seantop', 'localhost']
			]
		);
		$job->start();
	}

	/** Start a RePL. */

	public function repl()
	{
		stream_set_blocking(STDIN, FALSE);
		exec('stty -icanon min 0 time 0');
		system('stty -echo');
		register_shutdown_function(function(){
			system('stty echo');
		});
		set_error_handler(function($errno, $errstr, $errfile, $errline)
		{
			printf("Error %d: %s\n%s:%s", $errno, $errstr, $errfile, $errline);
		});
		set_exception_handler(function($exception)
		{
			print \SeanMorris\Ids\Log::renderException($exception);
			fwrite(STDERR, PHP_EOL . 'Goodbye.' . PHP_EOL);
		});
		print "Welcome to iREPL v0.1\n>";
		$line = $input = NULL;
		$lines = [];
		$offset = 0;
		while (TRUE)
		{
			sleep(0.1);
			$char = fread(STDIN,16);
			$byte = bin2hex($char);
			if(!strlen($char))
			{
				continue;
			}
			if($offset < 0)
			{
				$offset = 0;
			}
			if($offset > strlen($line))
			{
				$offset = strlen($line);
			}
			if($byte == '7f')
			{
				if(strlen($line) && $offset < strlen($line))
				{
					print $this->backspace($line, $offset);
					$line = substr($line, 0, strlen($line)-($offset+1)) . substr($line, strlen($line)-$offset);
					print $line;
				}
				if(!$line)
				{
					$offset = 0;
				}
				continue;
			}
			if(!preg_match('/\n/', $char) && strlen($byte) == 2)
			{
				print $this->backspace($line, $offset);
				$line = substr($line, 0, strlen($line)-$offset) . $char . substr($line, strlen($line)-$offset);
				print $line;
				continue;
			}
			else if(strlen($byte) > 2)
			{
				if($byte == '1b5b41')
				{
					print $this->backspace($line, $offset);
					$line = current($lines);
					print $line;
					if(next($lines) === FALSE)
					{
						reset($lines);
					}
					continue;
				}
				else if($byte == '1b5b42')
				{
					print $this->backspace($line);
					$line = current($lines);
					print $line;
					if(prev($lines) === FALSE)
					{
						reset($lines);
					}
					continue;
				}
				else if(in_array($byte, ['1b5b43', '1b5b44']))
				{
					if($byte == '1b5b44')
					{
						$offset++;
					}
					else
					{
						$offset--;
					}
					continue;
				}
			}
			$line = trim($line);
			array_unshift($lines, $line);
			if (substr($line, 0, 1) === '/')
			{
				$line = Ltrim($line, '/');
				if ($line === 'export') {
					/*
					file_put_contents(
						getenv("HOME") . '/.idilic/replVars.dat',
						serialize(array_diff_key(
							get_defined_vars(),
							array_flip(array('line', 'input', 'output'))
						))
					);
					*/
				}
				else if ($line === 'import') {
				}
			}
			else if (substr($line, -1, 1) === '\\')
			{
				$line = rtrim($line, '\\');
				$input .= PHP_EOL . $line;
				print "|";
				continue;
			}
			else if ($line && substr($line, -1, 1) !== ';')
			{
				$input .= sprintf("print PHP_EOL; print_r(%s);", $line);
			}
			else if ($line)
			{
				$input .= $line;
			}
			ob_start();
			eval($input . ';');
			$output = ob_get_contents();
			ob_end_flush();
			$input = $line = NULL;
			$offset = 0;
			print "\n>";
		}
	}

	/** Execute PHP with the project initialized. */

	public function run($router)
	{
		$args = $router->path()->consumeNodes();

		if($code = array_shift($args))
		{
			eval($code);
		}

		// \SeanMorris\Ids\Settings::findSettingsFile('thruput', 80);
	}

	protected function backspace($len, $offset = 0)
	{
		$len = strlen($len);

		//$string = $this->jump($offset);
		$string = NULL;

		//$len += $offset;

		$string .= str_repeat("\x08", $len)
			. str_repeat(" ", $len)
			. str_repeat("\x08", $len);

		return $string;
	}

	protected function jump($offset)
	{
		$string = NULL;

		if($offset < 0)
		{
			$string .= str_repeat("\x1b\x5b\x43", abs($offset));
		}
		else if($offset)
		{
			$string .= str_repeat("\x1b\x5b\x44", abs($offset));
		}
		return $string;
	}

	/** Print project info. */

	public function info()
	{
		$databases = \SeanMorris\Ids\Settings::read('databases');

		$dbs = NULL;

		foreach($databases as $name => $database)
		{
			$dbs .= sprintf(
				"Database: %s\n%sHost: %s\n"
				, $name
				, str_repeat(' ', 4)
				, $database->hostname
			);
		}

		return sprintf(
"Domain:\t\t%s
Root:\t\t%s
Entrypoint:\t%s
Log Level:\t%s
Root Package:\t%s
"
			, $_SERVER['HTTP_HOST']
			, IDS_ROOT
			, \SeanMorris\Ids\Settings::read('entrypoint')
			, \SeanMorris\Ids\Settings::read('logLevel')
			, \SeanMorris\Ids\Package::getRoot()->packageSpace()
		) . $dbs;
	}

	/** Watch project logs. Ctrl+c to exit. */

	public function log()
	{
		ignore_user_abort(true);
		popen('less -RSXMI +G /home/sean/letsvue/temporary/log.txt', 'w');
	}

	public static function parseDoc()
	{

	}
}
