<?php
use \SeanMorris\Ids\Log;
use \SeanMorris\Ids\Router;
use \SeanMorris\Ids\Request;
use \SeanMorris\Ids\Settings;

// $composer = require '../vendor/seanmorris/ids/source/init.php';
$composer = require '../source/init.php';

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

$request->contextSet('composer', $composer);

$routes = new $entrypoint();
$router = new Router($request, $routes);
$router->contextSet('composer', $composer);

ob_start();

$response = $router->route();

$debug = ob_get_contents();

ob_get_level() && ob_end_clean();

if(is_callable($response) && !($response instanceof Generator))
{
	$response = $response();
}

if($response instanceof \SeanMorris\Ids\Api\Response)
{
	$response = $response->send();
}

if($response instanceof Traversable || is_array($response))
{
	ob_flush();
	ob_end_flush();

	foreach($response as $chunk)
	{
		Log::debug('Sending', $chunk);
		echo $chunk;
		flush();
	}
}
else if(is_resource($response) && 'stream' === get_resource_type($response))
{
	stream_copy_to_stream($response, fopen('php://output', 'w'));
}
else
{
	print $response;
}

if(Settings::read('devmode') && $debug)
{
	printf('<pre>%s</pre>', $debug);
}
