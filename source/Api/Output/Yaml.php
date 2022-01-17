<?php
namespace SeanMorris\Ids\Api\Output;
class Yaml extends \SeanMorris\Ids\Api\OutputPump
{
	public function pump($content)
	{
		if(is_callable($content))
		{
			$content = $content();
		}

		$toArray = function($x) use(&$toArray)
		{
		    return is_scalar($x) ? $x : array_map($toArray, (array) $x);
		};

		if(!($content instanceof \Traversable))
		{
			$content = function() { yield $content; };
		}

		foreach($content as $key => $chunk)
		{
			$temp = fopen('php://temp', 'w+');

			if(!is_integer($key))
			{
				fwrite($temp, sprintf("%s: ", $key));
			}

			if(is_callable($chunk))
			{
				$chunk = $chunk();
			}

			if(is_scalar($chunk) || is_null($chunk))
			{
				fwrite($temp, yaml_emit($chunk));
			}
			elseif(is_object($chunk) && is_callable([$chunk, '__toApi']))
			{
				fwrite($temp, yaml_emit($chunk->__toApi()));
			}
			elseif(is_object($chunk) && is_callable([$chunk, '__toString']))
			{
				fwrite($temp, yaml_emit($chunk->__toString()));
			}
			else
			{
				$chunk = $toArray($chunk);

				fwrite($temp, yaml_emit($chunk));
			}

			rewind($temp);

			$ymlChunk = '';

			while(!feof($temp))
			{
				$line = fgets($temp);

				if($line === "...\n")
				{
					yield $ymlChunk;
					break;
				}

				$ymlChunk .= $line;

				// fwrite($this->handle, $line);
			}
		}

		yield '...';

		// fwrite($this->handle, "...\n");
	}

	protected function filter()
	{
		// php://temp
	}
}
