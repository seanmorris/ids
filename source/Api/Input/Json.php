<?php
namespace SeanMorris\Ids\Api\Input;
class Json extends \SeanMorris\Ids\Api\InputPump
{
	public function pump()
	{
		$source = '';

		while($line = fgets($this->handle))
		{
			$source .= $line;

			if($line === "\n")
			{
				if(trim($source))
				{
					yield json_decode($source, FALSE);
				}

				$source = '';
			}
		}

		if(trim($source))
		{
			yield json_decode($source, TRUE);
		}
	}
}
