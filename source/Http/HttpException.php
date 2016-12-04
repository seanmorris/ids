<?php
namespace SeanMorris\Ids\Http;
class HttpException extends \Exception
{
	public function __construct($message = null, $code = 200, Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}

	public function onCatch()
	{

	}

	public function __toString()
	{
		return '<pre>' . parent::__toString() . '<pre>';
	}
}