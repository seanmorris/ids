<?php
use \SeanMorris\Ids\Log;
use \SeanMorris\Ids\Router;
use \SeanMorris\Ids\Request;
use \SeanMorris\Ids\Settings;

$composer = require '../vendor/seanmorris/ids/source/init.php';

if(isset($argv))
{
	$args = $argv;
	$script = array_shift($args);
}
else
{
	$request = new Request();
}

if(!$entrypoint = Settings::read('entrypoint'))
{
	print('No entrypoint specified. Please check local settings.');
	Log::error('No entrypoint specified. Please check local settings.');
	die;
}

$routes = new $entrypoint();
$router = new Router($request, $routes);
$router->contextSet('composer', $composer);

ob_start();
$response = $router->route();
$debug = ob_get_contents();
ob_end_clean();

print $response;

if(Settings::read('devmode') && $debug)
{
	printf('<pre>%s</pre>', $debug);
}
