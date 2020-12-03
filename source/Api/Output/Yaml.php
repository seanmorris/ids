<?php
namespace SeanMorris\Ids\Api\Output;
class Yaml extends \SeanMorris\Ids\Api\OutputParser
{
	public function parse($content)
	{
		if(is_callable($content))
		{
			$content = $content();
		}

		if($content instanceof \Traversable || $content instanceof \Generator)
		{
			foreach($content as $chunk)
			{
				if(is_callable($chunk))
				{
					$chunk = $chunk();
				}

				if(is_scalar($chunk))
				{
					fwrite($this->handle, yaml_emit((array)$chunk));
				}
				elseif(is_object($chunk) && is_callable([$chunk, '__toApi']))
				{
					fwrite($this->handle, yaml_emit($content->__toApi()));
				}
				elseif(is_object($chunk) && is_callable([$chunk, '__toString']))
				{
					fwrite($this->handle, yaml_emit($content->__toString()));
				}
				else
				{
					fwrite($this->handle, yaml_emit((array)$chunk));
				}
			}

			return;
		}

		if(is_scalar($content))
		{
			fwrite($this->handle, yaml_emit((array)$content));
		}
		elseif(is_object($content) && is_callable([$content, '__toApi']))
		{
			fwrite($this->handle, yaml_emit($content->__toApi()));
		}
		elseif(is_object($content) && is_callable([$content, '__toString']))
		{
			fwrite($this->handle, yaml_emit($content->__toString()));
		}
		else
		{
			fwrite($this->handle, yaml_emit((array)$chunk));
		}
	}
}
