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

			if($line === "\n" || $line === "...\n")
			{
				$source .= $line;

				if(trim($source))
				{
					yield yaml_parse($source);
				}


				$source = '';
			}
		}

		if($source)
		{
			yield yaml_parse($source);
		}
	}
}
