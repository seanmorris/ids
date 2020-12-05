<?php
namespace SeanMorris\Ids\Api\Output;
class Csv extends \SeanMorris\Ids\Api\OutputParser
{
	protected $header    = NULL;
	protected $delimiter = ',';
	protected $enclosure = '"';
	protected $escape    = '\\';

	public function __construct($handle, $header = TRUE)
	{
		parent::__construct($handle);

		$this->header = $header;
	}

	public function parse($content)
	{
		if(is_callable($content))
		{
			$content = $content();
		}

		if($content instanceof \Traversable || $content instanceof \Generator)
		{
			$header = [];

			if($this->header)
			{
				$buffer = fopen('php://temp', 'r+');
			}
			else
			{
				$buffer = $this->handle;
			}

			foreach($content as $chunk)
			{
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
			}

			if($this->header)
			{
				rewind($buffer);

				fputcsv($this->handle, $header, $this->delimiter, $this->enclosure, $this->escape);
			}

			stream_copy_to_stream($buffer, $this->handle);

			return;
		}

		if(is_scalar($content))
		{
			fputcsv($this->handle, ['' => $content], $this->delimiter, $this->enclosure, $this->escape);
		}
		elseif(is_object($content) && is_callable([$content, '__toApi']))
		{
			$line = (array) $content->__toApi();

			fputcsv($this->handle, array_keys($line), $this->delimiter, $this->enclosure, $this->escape);
			fputcsv($this->handle, $line, $this->delimiter, $this->enclosure, $this->escape);
		}
		elseif(is_object($content) && is_callable([$content, '__toString']))
		{
			fputcsv($this->handle, ['' => $content->__toString()], $this->delimiter, $this->enclosure, $this->escape);
		}
		else
		{
			$line = (array) $content;

			fputcsv($this->handle, array_keys($line), $this->delimiter, $this->enclosure, $this->escape);
			fputcsv($this->handle, $line, $this->delimiter, $this->enclosure, $this->escape);
		}
	}
}
