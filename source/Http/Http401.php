<?php
namespace SeanMorris\Ids\Http;
class Http401 extends HttpException
{
	public function __construct($message = null, $code = 401, \Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}

	public function onCatch($router)
	{
		header(sprintf("HTTP/1.1 %d Unauthorized", $this->getCode()));
	}
}
