<?php
namespace SeanMorris\Ids\Http;
class Http303 extends HttpException
{
	public function __construct($message = null, $code = 303, \Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}

	public function onCatch($router)
	{
		\SeanMorris\Ids\Log::debug(sprintf("Redirect Location: %s", $this->getMessage()));

		if(php_sapi_name() !== 'cli')
		{
			$url = $this->getMessage();

			$parsedUrl = parse_url($url);

			if(!isset($parsedUrl['host']) && $url && $url[0] !== '/')
			{
				$url = '/' . $url;
			}

			$params = $router->request()->get();

			if(
				$params
				&& empty($parsedUrl['query'])
				&& empty($parsedUrl['host'])
				&& substr($url, -1, 1) !== '?'
			){
				$url = sprintf(
					'/%s?%s'
					, $parsedUrl['path']
					, http_build_query($params)
				);
			}

			header(sprintf("HTTP/1.1 %d See Other", $this->getCode()));
			header(sprintf("Location: %s", $url));
			return NULL;
		}

		$subRequest = new \SeanMorris\Ids\Request([
			'uri' => $this->getMessage()
		]);

		$subrouter = new $router($subRequest, $router->routes(), $router);

		return $this->message = $subrouter->route();
	}
}
