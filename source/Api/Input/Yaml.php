<?php
namespace SeanMorris\Ids\Api\Input;
class Yaml extends \SeanMorris\Ids\Api\InputParser
{
	protected $handle;

	public function __construct($handle)
	{
		$this->handle = $handle;
	}

	public function parse()
	{
		$source = '';

		while($line = fgets($this->handle))
		{
			$source .= $line;

			if($line === "\n")
			{
				yield yaml_parse($source);

				$source = '';
			}
		}

		yield yaml_parse($source);
	}
}
