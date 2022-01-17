<?php
namespace SeanMorris\Ids\Test\Route;
class ApiRoute implements \SeanMorris\Ids\Routable
{
	function index($router)
	{
		header('Content-Type: text/plain');

		return fopen('/app/README.md', 'r');
	}

	function reflectData($router)
	{
		$request = $router->request();

		$response = new \SeanMorris\Ids\Api\Response($request);

		return $response->send($request->read());
	}

	public function events()
	{
		session_write_close();

		header('Cache-Control: no-cache');
		header('Content-Type: text/event-stream');

		static $i = 0;

		while($i++ < 3)
		{
			yield new \SeanMorris\Ids\Http\Event(
				'Latest random number: ' . mt_rand()
			);
		}

		yield "\n\n";
	}
}
