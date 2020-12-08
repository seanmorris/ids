<?php
namespace SeanMorris\Ids\Api\Output;
class Json extends \SeanMorris\Ids\Api\OutputParser
{
	public function parse($content)
	{
		if(is_callable($content))
		{
			$content = $content();
		}

		if($content instanceof \Traversable || $content instanceof \Generator)
		{
			foreach($content as $key => $chunk)
			{
				if(!is_integer($key))
				{
					fwrite($this->handle, sprintf("%s: ", $key));
				}

				if(is_callable($chunk))
				{
					$chunk = $chunk();
				}

				if(is_scalar($chunk) || is_null($chunk))
				{
					fwrite($this->handle, json_encode($chunk));
				}
				elseif(is_object($chunk) && is_callable([$chunk, '__toApi']))
				{
					fwrite($this->handle, json_encode($content->__toApi()));
				}
				elseif(is_object($chunk) && is_callable([$chunk, '__toString']))
				{
					fwrite($this->handle, json_encode($content->__toString()));
				}
				else
				{
					fwrite($this->handle, json_encode($chunk));
				}

				fwrite($this->handle, PHP_EOL . PHP_EOL);
			}

			return;
		}

		if(is_scalar($content) || is_null($content))
		{
			fwrite($this->handle, json_encode($content));
		}
		elseif(is_object($content) && is_callable([$content, '__toApi']))
		{
			fwrite($this->handle, json_encode($content->__toApi()));
		}
		elseif(is_object($content) && is_callable([$content, '__toString']))
		{
			fwrite($this->handle, json_encode($content->__toString()));
		}
		else
		{
			fwrite($this->handle, json_encode($content));
		}

		fwrite($this->handle, PHP_EOL . PHP_EOL);
	}
}
