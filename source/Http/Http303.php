<?php
namespace SeanMorris\Ids\Http;
class Http303 extends HttpException
{
	public function __construct($message = null, $code = 303, Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}

	public function onCatch($router)
	{
		\SeanMorris\Ids\Log::debug(sprintf("Redirect Location: /%s", $this->getMessage()));
		
		if(php_sapi_name() !== 'cli')
		{
			$query = NULL;
			if(isset($_GET['api']))
			{
				$query = '?api=' . $_GET['api'];
			}
			header(sprintf("HTTP/1.1 %d See Other", $this->getCode()));
			header(sprintf("Location: /%s" . $query, $this->getMessage()));
			return NULL;
		}

		$subRequest = new \SeanMorris\Ids\Request([
			'uri' => $this->getMessage()
		]);

		$subrouter = new $router($subRequest, $router->routes(), $router);
		
		return $this->message = $subrouter->route();
	}
}