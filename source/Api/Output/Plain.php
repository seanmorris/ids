<?php
namespace SeanMorris\Ids\Api\Output;
class Plain extends \SeanMorris\Ids\Api\OutputParser
{
	public function parse($content)
	{
		if(is_callable($content))
		{
			$content = $content();
		}

		if($content instanceof \Traversable || $content instanceof \Generator || is_array($content))
		{
			foreach($content as $chunk)
			{
				if(is_callable($chunk))
				{
					$chunk = $chunk();
				}

				if(is_scalar($chunk))
				{
					fwrite($this->handle, $chunk);
				}
				elseif(is_object($chunk) && is_callable([$chunk, '__toApi']))
				{
					fwrite($this->handle, print_r($chunk->__toApi(), 1));
				}
				elseif(is_object($chunk) && is_callable([$chunk, '__toString']))
				{
					fwrite($this->handle, $chunk);
				}
				else
				{
					fwrite($this->handle, print_r($chunk, 1));
				}
			}

			return;
		}

		if(is_scalar($content))
		{
			fwrite($this->handle, $content);
		}
		elseif(is_object($content) && is_callable([$content, '__toApi']))
		{
			fwrite($this->handle, $content->__toApi());
		}
		elseif(is_object($content) && is_callable([$content, '__toString']))
		{
			fwrite($this->handle, $content);
		}
		else
		{
			fwrite($this->handle, print_r($content, 1));
		}
	}
}
