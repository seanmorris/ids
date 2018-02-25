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
			printf("Error: Cannot find package/command '%s'\n", $packageName);
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

		\SeanMorris\Ids\Log::debug(sprintf('Using asset manager "%s"', get_class($assetManager)));

		$assetManager::buildAssets($package);
	}

	public function help($router)
	{
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
		$timeout = $router->path()->consumeNode();

		$processor = new $processor($child, $max, $timeout);

		$processor->spin();
	}

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

	public function info()
	{
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
		);
	}
}
