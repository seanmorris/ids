<?php
error_reporting(-1);

$start = microtime(true);

date_default_timezone_set('GMT+0');

$dir = getcwd();

while(TRUE)
{
	$autoloadPath = $dir . '/vendor/autoload.php';

	if(file_exists($autoloadPath))
	{
		break;
	}

	$nextDir = dirname($dir);

	if($nextDir === $dir)
	{
		$autoloadPath = NULL;
		break;
	}

	$dir = $nextDir;
}

if(!$autoloadPath)
{
	$userFile = getenv("HOME") . '/.idilicProfile.json';

	if(file_exists($userFile))
	{
		$userSettings = json_decode(file_get_contents($userFile));
		$autoloadPath = $userSettings->root . '/vendor/autoload.php';
	}
	else
	{
		throw new \ErrorException(
			'Unable to locate autoloader. ' . (
				(php_sapi_name() === 'cli')
				 	? 'Run idilic inside the project directory or configure ~/.idilicProfile.json'
				 	: 'Check your directory structure.'
			 )
		);
	}
}

if($autoloadPath)
{
	define('IDS_VENDOR_ROOT', dirname($autoloadPath));
	$composer = require $autoloadPath;
}
else
{
	print 'Cannot locate project directory.' . PHP_EOL;
	exit(1);
}

$idsPackage = \SeanMorris\Ids\Package::get('SeanMorris/Ids');

if(!isset($_SERVER['HTTP_HOST']))
{
	$_SERVER['HTTP_HOST'] =  $idsPackage->getVar('idilic:defaultDomain', NULL);
}

$errorPath = IDS_VENDOR_ROOT . '/../temporary/log.txt';

if(!file_exists($errorPath))
{
	touch($errorPath);
}

ini_set("error_log", $errorPath);

register_shutdown_function(function() use($start){
	$error = error_get_last();
    if ($error['type'] === E_ERROR)
    {
    	\SeanMorris\Ids\Log::error(
    		'FATAL ERROR OCCURRED.'
    		, $error
    	);
    }
	\SeanMorris\Ids\Log::info(
		'Response Complete.'
		, [
			'Space' => memory_get_peak_usage(true) / (1024*1024) . ' MB'
			, 'Time' => number_format(microtime(true) - $start, 2)  . ' sec'
		]
		, PHP_EOL
	);
});
set_exception_handler(['\SeanMorris\Ids\Log', 'logException']);
$existingErrorHandler = set_error_handler(
	function($errorNumber, $errorString, $errorFile, $errorLine, $errorContext) use(&$existingErrorHandler)
	{
		$errorContextContent = NULL;

		/*
		ob_start();
		$errorContextContent = ob_get_contents();
		ob_end_clean();
		*/

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

if($db = \SeanMorris\Ids\Settings::read('databases'))
{
	\SeanMorris\Ids\Database::registerMulti($db);
}

if(!\SeanMorris\Ids\Settings::read('devmode'))
{
	\SeanMorris\Ids\Log::suppress();
}

return $composer;
