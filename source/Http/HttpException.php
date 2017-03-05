<?php
namespace SeanMorris\Ids\Http;
class HttpException extends \Exception
{
	public function __construct($message = null, $code = 200, Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}

	public function onCatch($router)
	{

	}

	public function __toString()
	{
		return '<pre>' . htmlspecialchars(parent::__toString()) . '</pre>';
	}

	public function indentedTrace()
	{
		return preg_replace(
			['/^/m', '/\:\s(.+)/']
			, ["\t", "\n\t\t\$1\n"]
			, $this->getTraceAsString()
		);
	}
}