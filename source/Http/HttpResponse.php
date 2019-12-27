<?php
namespace SeanMorris\Ids\Http;
class HttpResponse extends HttpException
{
	public function __construct($message = null, $code = 200, \Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}

	public function onCatch($router)
	{
		header(sprintf("HTTP/1.1 %s", $this->getCode()));
	}

	public function __toString()
	{
		return $this->message;
	}
}