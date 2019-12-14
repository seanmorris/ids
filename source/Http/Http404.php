<?php
namespace SeanMorris\Ids\Http;
class Http404 extends HttpException
{
	public function __construct($message = null, $code = 404, \Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}

	public function onCatch($router)
	{
		header(sprintf("HTTP/1.1 %d Not Found", $this->getCode()));
	}
}