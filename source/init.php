<?php
error_reporting(-1);
ini_set('display_errors', TRUE);

define('START', microtime(TRUE));

date_default_timezone_set('GMT+0');

$dir = getcwd();

if($dir !== '/')
{
	$dir .= '/';
}

$profileDir = $dir;

while(TRUE)
{
	$autoloadPath = $dir . 'vendor/autoload.php';

	if(file_exists($autoloadPath))
	{
		break;
	}

	$nextDir = dirname(realpath($dir)) . '/';

	if(!file_exists($autoloadPath) && $nextDir === $dir)
	{
		$autoloadPath = NULL;
		break;
	}

	$dir = $nextDir;
}

$userFile = NULL;

if(php_sapi_name() === 'cli')
{
	while(TRUE)
	{
		$userFile = $profileDir . '/.idilicProfile.json';

		if(file_exists($userFile))
		{
			break;
		}

		$nextDir = dirname($profileDir);

		if($nextDir === $profileDir)
		{
			break;
		}

		$profileDir = $nextDir;

	}

	if(!file_exists($userFile))
	{
		$userFile = getenv("HOME") . '/.idilicProfile.json';
	}

	if(!isset($_SERVER['HTTP_HOST']) && file_exists($userFile))
	{
		$userSettings = json_decode(file_get_contents($userFile));

		if($userSettings->root == '.')
		{
			$userSettings->root = dirname($userFile);
		}

		$autoloadPath = $userSettings->root . '/vendor/autoload.php';

		$_SERVER['HTTP_HOST'] = $userSettings->domain;
	}
}

if(!$autoloadPath)
{
	throw new \ErrorException(
		'Unable to locate autoloader. ' . (
			(php_sapi_name() === 'cli')
			 	? 'Run idilic inside the project directory or configure ~/.idilicProfile.json'
			 	: 'Check your directory structure.'
		 )
	);
}

$composer = FALSE;

if($autoloadPath)
{
	if(!file_exists($autoloadPath))
	{
		$error = sprintf(
			'Cannot find autoloader specified at path: %s'
			, $autoloadPath
		);
		if(php_sapi_name() == 'cli')
		{
			print $error . PHP_EOL;
		}
		throw new Exception($error);
	}

	define('IDS_VENDOR_ROOT', dirname($autoloadPath));
	define('IDS_ROOT'       , dirname(IDS_VENDOR_ROOT));

	$composer = require $autoloadPath;

	\SeanMorris\Ids\Loader::register();
}
else
{
	print 'Cannot locate project directory.' . PHP_EOL;
	exit(1);
}

if(!$errorPath = \SeanMorris\Ids\Settings::read('log'))
{
	$errorPath = IDS_VENDOR_ROOT . '/../temporary/log.txt';

	if(!is_writable($errorPath))
	{
		$errorPath = 'php://stderr';
	}
}

if(!file_exists($errorPath))
{
	touch($errorPath);
}

ini_set("error_log", $errorPath);

register_shutdown_function(function() {
	if($error = error_get_last())
	{
		if ($error['type'] === E_COMPILE_ERROR)
		{
			\SeanMorris\Ids\Log::error(
				\SeanMorris\Ids\Log::color('COMPILER ERROR OCCURRED.', 'black', 'red')
				, (object) $error
				);
		}

		if ($error['type'] === E_ERROR)
		{
			\SeanMorris\Ids\Log::error(
				\SeanMorris\Ids\Log::color('FATAL ERROR OCCURRED.', 'black', 'red')
				, (object) $error
				);
		}

		if(php_sapi_name() !== 'cli' && \SeanMorris\Ids\Settings::read('show', 'errors'))
		{
			header('Content-type: text/plain');
			print 'FATAL ERROR OCCURRED.';
			print \SeanMorris\Ids\Log::dump($error, [], FALSE);
		}
	}

	$queryTimeMs   = \SeanMorris\Ids\Mysql\Statement::queryTime() * 1000;
	$requestTimeMs = (microtime(true) - START) * 1000;

	\SeanMorris\Ids\Log::info(
		'Response Complete.'
		, memory_get_peak_usage(true)
		, [
			'Space'        => number_format(
				memory_get_peak_usage(true) / (1024*1024), 2
			) . sprintf(
				' MB (%s bytes, %s real)'
				, memory_get_peak_usage()
				, memory_get_peak_usage(TRUE)
			)
			, 'Time'       => number_format(microtime(true) - START, 4)  . ' sec'
			, 'Queries'    => \SeanMorris\Ids\Mysql\Statement::queryCount()
			, 'Query Time' => number_format(
				\SeanMorris\Ids\Mysql\Statement::queryTime()
				, 4
			) . ' sec'
		]
		, PHP_EOL
		, new \SeanMorris\Ids\LogMeta([
			'request_space'      => memory_get_peak_usage(true)
			, 'request_time'     => $requestTimeMs
			, 'query_count'      => \SeanMorris\Ids\Mysql\Statement::queryCount()
			, 'query_total_time' => $queryTimeMs
		])
	);
});

set_exception_handler(function($exception)
{
	global $switches;

	if(php_sapi_name() == 'cli' && ($switches['vv'] ?? FALSE))
	{
		$renderedException = \SeanMorris\Ids\Log::renderException($exception, FALSE);

		fwrite(STDERR, $renderedException);
	}
	else if(php_sapi_name() !== 'cli' && \SeanMorris\Ids\Settings::read('show', 'errors'))
	{
		$renderedException = \SeanMorris\Ids\Log::renderException($exception, FALSE);

		header('Content-type: text/plain');
		print $renderedException;
	}

	\SeanMorris\Ids\Log::logException($exception);

	if(function_exists('xdebug_break'))
	{
		xdebug_break();
	}

	exit(1);
});

$existingErrorHandler = set_error_handler(
	function($errorNumber, $errorString, $errorFile, $errorLine, $errorContext) use(&$existingErrorHandler)
	{
		if(function_exists('xdebug_break'))
		{
			xdebug_break();
		}

		$errorContextContent = NULL;

		ob_start();
		$errorContextContent = ob_get_contents();
		ob_end_clean();

		$line = sprintf(
			'(%d)"%s" thrown in %s:%d'
				.  PHP_EOL
				. '%s'
			, $errorNumber
			, $errorString
			, $errorFile
			, $errorLine
			, $errorContextContent
		);

		$existingErrorHandler && $existingErrorHandler(
			$errorNumber
			, $errorString
			, $errorFile
			, $errorLine
			, $errorContext
		);

		throw new \ErrorException($line, $errorNumber, 0, $errorFile, $errorLine);
	}
);

if($dbs = \SeanMorris\Ids\Settings::read('databases'))
{
		\SeanMorris\Ids\Database::registerMulti($dbs);

	foreach($dbs as $name => $creds)
	{
		\SeanMorris\Ids\Log::debug(
			sprintf('Setting SQL mode for %s...', $name)
		);

		$dbHandle = \SeanMorris\Ids\Database::get($name);

		$query = $dbHandle->prepare("SET SESSION sql_mode=(
			SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY','')
		)");

		$query->execute();

		$query = $dbHandle->prepare("SET SESSION sql_mode=(
			SELECT REPLACE(@@sql_mode,'NO_ZERO_DATE','')
		)");
	}
}

if(php_sapi_name() !== 'cli')
{
	session_set_cookie_params(
		\SeanMorris\Ids\Settings::read('session', 'lifetime')
		, \SeanMorris\Ids\Settings::read('session', 'path')
		, \SeanMorris\Ids\Settings::read('session', 'domain')
		, ($_SERVER['HTTPS'] ?? FALSE) === 'on'
		, TRUE
	);
}

if(!\SeanMorris\Ids\Settings::read('devmode'))
{
	\SeanMorris\Ids\Log::suppress();
}

return $composer;
