<?php
namespace SeanMorris\Ids\Api\Output;
class Plain extends \SeanMorris\Ids\Api\OutputPump
{
	public function pump($content)
	{
		if(is_callable($content))
		{
			$content = $content();
		}

		if(!($content instanceof \Traversable))
		{
			$content = function() { yield $content; };
		}

		foreach($content as $key => $chunk)
		{
			if(is_callable($chunk))
			{
				$chunk = $chunk();
			}

			if(is_scalar($chunk))
			{
				yield $chunk;

			}
			elseif(is_object($chunk) && is_callable([$chunk, '__toApi']))
			{
				yield print_r($chunk->__toApi(), 1);
			}
			elseif(is_object($chunk) && is_callable([$chunk, '__toString']))
			{
				yield $chunk;
			}
			else
			{
				yield print_r($chunk, 1);
			}
		}
	}
}
