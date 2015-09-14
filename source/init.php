<?php
$start = microtime(true);

define('IDS_ROOT', dirname(__FILE__) . '/');
define('IDS_ASSET_ROOT', dirname(IDS_ROOT) . '/asset/');
define('IDS_VENDOR_ROOT', dirname(IDS_ROOT) . '/vendor/');
define('IDS_PUBLIC_ROOT', dirname(IDS_ROOT) . '/public/');
define('IDS_PUBLIC_DYNAMIC', 'Static/Dynamic/');
define('IDS_PRIVATE_DYNAMIC', 'Static/Private/');
define('IDS_LOCAL_DATA_ROOT', dirname(IDS_ROOT) . '/data/local/');
define('IDS_GLOBAL_DATA_ROOT', dirname(IDS_ROOT) . '/data/global/');
//define('IDS_TEMPORARY_ROOT', dirname(IDS_ROOT) . '/temporary/');
define('IDS_TEMPORARY_ROOT', '/tmp/ids/');
define('IDS_LOG_PATH', IDS_TEMPORARY_ROOT . 'log.txt');

date_default_timezone_set('GMT+0');

ini_set("error_log", IDS_LOG_PATH);

$composer = require IDS_VENDOR_ROOT . 'autoload.php';

register_shutdown_function(function() use($start){
	$error = error_get_last();
    if ($error['type'] === E_ERROR)
    {
    	\SeanMorris\Ids\Log::error(
    		'FATAL ERROR OCCURRED.'
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
