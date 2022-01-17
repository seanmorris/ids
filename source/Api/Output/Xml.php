<?php
namespace SeanMorris\Ids\Api\Output;
class Xml extends \SeanMorris\Ids\Api\OutputPump
{
	public function __construct($settings = [])
	{
		$this->atomize = ($settings['Ids-Output-Atomize'] ?? '') === 'true';
	}

	public function pump($content)
	{
		if(is_callable($content))
		{
			$content = $content();
		}

		$writer = new \XMLWriter();
		$writer->openMemory();
		$writer->setIndent(true);

		if(!($content instanceof \Traversable))
		{
			$content = function() { yield $content; };
		}

		if(!$this->atomize)
		{
			$writer->startDocument('1.0', 'utf-8');
			$writer->startElement('items');
		}

		foreach($content as $key => $chunk)
		{
			if($this->atomize)
			{
				$writer->startDocument('1.0', 'utf-8');
			}

			$writer->startElement('item');

			$this->convertToXml($chunk, $writer);

			$writer->endElement();

			if($this->atomize)
			{
				$writer->endDocument();
			}

			yield $writer->flush() . PHP_EOL;
		}

		if(!$this->atomize)
		{
			$writer->endElement();
			$writer->endDocument();
			yield $writer->flush() . PHP_EOL;
		}

	}

	protected function convertToXml($chunk, $writer, $key = NULL)
	{
		if(is_callable($chunk))
		{
			$chunk = $chunk();
		}

		if(is_object($chunk) && is_callable([$chunk, '__toApi']))
		{
			$chunk = $chunk->__toApi();
		}

		if(is_object($chunk) && is_callable([$chunk, '__toString']))
		{
			$chunk = $chunk->__toString();
		}

		if(is_object($chunk))
		{
			$writer->startElement('object');

			if($key !== NULL)
			{
				$writer->writeAttribute('key', $key);
			}

			foreach($chunk as $key => $value)
			{
				$this->convertToXml($value, $writer, $key);
			}

			$writer->endElement();
		}
		else if(is_array($chunk))
		{
			$writer->startElement('array');

			if($key !== NULL)
			{
				$writer->writeAttribute('key', $key);
			}

			foreach($chunk as $key => $value)
			{
				$this->convertToXml($value, $writer, $key);
			}

			$writer->endElement();
		}
		else if(is_scalar($chunk) || is_null($chunk))
		{
			$writer->startElement('scalar');
			$writer->writeAttribute('type', gettype($chunk));
			$writer->writeAttribute('key', $key);
			$writer->writeAttribute('value', $chunk);
			$writer->endElement();
		}
	}
}
