<?php
namespace SeanMorris\Ids\Api\Input;
class Plain extends \SeanMorris\Ids\Api\InputParser
{
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
