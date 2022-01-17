<?php
namespace SeanMorris\Ids\Api\Input;
class Yaml extends \SeanMorris\Ids\Api\InputPump
{
	public function pump()
	{
		$source = '';

		while($line = fgets($this->handle))
		{
			if($line === "...\n" || ($source && $line === "---\n" ))
			{
				yield yaml_parse($source);
				$source = '';
			}
			else
			{
				$source .= $line;
			}
		}

		$source = trim($source);

		if($source && $source !== '...')
		{
			yield yaml_parse($source);
		}
	}
}
