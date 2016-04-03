<?php
namespace SeanMorris\Ids\Http;
class Http303 extends HttpException
{
	public function __construct($message = null, $code = 303, Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}

	public function onCatch()
	{
		\SeanMorris\Ids\Log::debug(sprintf("Redirect Location: /%s", $this->getMessage()));

		header(sprintf("HTTP/1.1 %d See Other", $this->getCode()));
		header(sprintf("Location: /%s", $this->getMessage()));
		die;
	}
}