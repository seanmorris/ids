<?php
namespace SeanMorris\Ids\Api;
abstract class InputParser
{
	public function __construct($handle)
	{
		$this->handle = $handle;
	}

	abstract function parse();
}
