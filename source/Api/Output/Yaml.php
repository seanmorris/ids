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

		$toArray = function($x) use(&$toArray)
		{
		    return is_scalar($x) ? $x : array_map($toArray, (array) $x);
		};

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
					fwrite($this->handle, yaml_emit($chunk));
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
					$chunk = $toArray($chunk);

					fwrite($this->handle, yaml_emit($chunk));
				}

				fwrite($this->handle, PHP_EOL);
			}


			return;
		}

		if(is_scalar($chunk) || is_null($content))
		{
			fwrite($this->handle, yaml_emit($content));
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
			$chunk = $toArray($content);

			fwrite($this->handle, yaml_emit((array)$content));
		}

		fwrite($this->handle, PHP_EOL);
	}
}
