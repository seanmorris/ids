<?php
namespace SeanMorris\Ids\Api\Output;
class Csv extends \SeanMorris\Ids\Api\OutputPump
{
	protected $header    = NULL;
	protected $delimiter = ',';
	protected $enclosure = '"';
	protected $escape    = '\\';

	public function __construct($settings = ['Ids-Output-Headers' => TRUE])
	{
		$this->header = ($settings['Ids-Output-Headers'] ?? '') === 'true';
	}

	public function pump($content)
	{
		if(is_callable($content))
		{
			$content = $content();
		}

		$header = [];

		$buffer = fopen('php://temp', 'r+');

		if(!($content instanceof \Traversable))
		{
			$content = function() { yield $content; };
		}

		foreach($content as $key => $chunk)
		{
			if(!is_integer($key))
			{
				fwrite($buffer, sprintf("%s: ", $key));
			}

			if(is_callable($chunk))
			{
				$chunk = $chunk();
			}

			if(is_scalar($chunk))
			{
				fputcsv($buffer, ['' => $chunk], $this->delimiter, $this->enclosure, $this->escape);
			}
			elseif(is_object($chunk) && is_callable([$chunk, '__toApi']))
			{
				fputcsv($buffer, $content->__toApi(), $this->delimiter, $this->enclosure, $this->escape);
			}
			elseif(is_object($chunk) && is_callable([$chunk, '__toString']))
			{
				fputcsv($buffer, ['' => $content->__toString()], $this->delimiter, $this->enclosure, $this->escape);
			}
			else
			{
				$header = array_unique(array_merge($header, array_keys((array)$chunk)));
				$empty  = array_map(function(){return NULL; }, array_flip($header));
				$values = array_replace($empty, array_filter((array)$chunk, 'is_scalar'));

				fputcsv($buffer, $values, $this->delimiter, $this->enclosure, $this->escape);
			}

			if(!$this->header)
			{
				yield fgets($buffer);
			}
		}

		if($this->header)
		{
			$headerBuffer = fopen('php://memory', 'r+');

			fputcsv($headerBuffer, $header, $this->delimiter, $this->enclosure, $this->escape);

			rewind($headerBuffer);

			yield fgets($headerBuffer);

		}

		rewind($buffer);

		while(!feof($buffer))
		{
			yield fgets($buffer);
		}
	}
}
