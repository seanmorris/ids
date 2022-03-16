<?php
namespace SeanMorris\Ids\Test\Route;
class ApiRoute implements \SeanMorris\Ids\Routable
{
	function index($router)
	{
		header('Content-Type: text/plain');

		return fopen('/app/README.md', 'r');
	}

	public function reflectData($router)
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

	public function rebash()
	{
		return 'while read -erp "< " LINE </dev/tty; do echo -e "$(curl -s localhost:2020/door --data-binary @- <<< "$LINE")"; done;' . PHP_EOL;
	}

	public function door($router)
	{
		$request = $router->request();

		return '> ' . $request->fgets() . PHP_EOL;
	}
}
