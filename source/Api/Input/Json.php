<?php
namespace SeanMorris\Ids\Api\Input;
class Json extends \SeanMorris\Ids\Api\InputParser
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
				if($source)
				{
					yield json_decode($source);
				}

				$source = '';
			}
		}

		if($source)
		{
			yield json_decode($source);
		}
	}
}
