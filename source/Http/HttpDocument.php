<?php
namespace SeanMorris\Ids\Http;
class HttpDocument extends HttpResponse
{
	public function __construct($message = null, $code = 200, Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}

	public function onCatch()
	{
		header(sprintf("HTTP/1.1 %s", $this->getCode()));
	}
}