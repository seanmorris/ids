<?php
error_reporting(-1);

$start = microtime(true);

date_default_timezone_set('GMT+0');

$dir = getcwd();

while(TRUE)
{
	print $dir . PHP_EOL;

	$autoloadPath = $dir . '/vendor/autoload.php';
	
	if(file_exists($autoloadPath))
	{
		define('IDS_VENDOR_ROOT', $dir);
		$composer = require $autoloadPath;
		break;
	}
	
	$nextDir = dirname($dir);

	if($nextDir === $dir)
	{
		break;
	}

	$dir = $nextDir;
}

ini_set("error_log", '/tmp/ids_log.txt');

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
			, 'Time' => number_format(microtime(true) - $start, 6)  . ' sec'
		]
		, PHP_EOL
	);
});
set_exception_handler(['\SeanMorris\Ids\Log', 'logException']);
$existingErrorHangler = set_error_handler(
	function($errorNumber, $errorString, $errorFile, $errorLine, $errorContext) use(&$existingErrorHangler)
	{
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

		$existingErrorHangler && $existingErrorHangler(
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
