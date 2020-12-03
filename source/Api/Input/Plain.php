<?php
namespace SeanMorris\Ids\Api\Input;
class Plain extends \SeanMorris\Ids\Api\InputParser
{
	protected $handle, $header;

	public function __construct($handle)
	{
		$this->handle = $handle;
	}

	public function parse()
	{
		$source = '';
		$header = [];

		while($line = fgets($this->handle))
		{
			yield $line;
		}
	}
}
