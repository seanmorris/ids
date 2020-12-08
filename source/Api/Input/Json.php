<?php
namespace SeanMorris\Ids\Api\Input;
class Json extends \SeanMorris\Ids\Api\InputParser
{
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
					yield json_decode($source, TRUE);
				}

				$source = '';
			}
		}

		if($source)
		{
			yield json_decode($source, TRUE);
		}
	}
}
