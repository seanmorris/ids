<?php
namespace SeanMorris\Ids\Http;
class Http201 extends HttpException
{
	public function __construct($message = null, $code = 201, \Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}

	public function onCatch($router)
	{
		header(sprintf("HTTP/1.1 %d Created", $this->getCode()));
	}
}
