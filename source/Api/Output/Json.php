<?php
namespace SeanMorris\Ids\Api\Output;
class Json extends \SeanMorris\Ids\Api\OutputPump
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

			if(is_object($chunk) && is_callable([$chunk, '__toApi']))
			{
				$content = $content->__toApi();
			}

			if(is_object($chunk) && is_callable([$chunk, '__toString']))
			{
				$content = $content->__toString();
			}

			yield json_encode($chunk) . PHP_EOL . PHP_EOL;
		}
	}
}
