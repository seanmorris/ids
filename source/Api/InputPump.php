<?php
namespace SeanMorris\Ids\Api;
abstract class InputPump
{
	public function __construct($handle, $headers)
	{
		$this->handle  = $handle;
		$this->headers = $headers;
	}

	abstract function pump();
}
