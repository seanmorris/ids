<?php
namespace SeanMorris\Ids\Api\Input;
class Csv extends \SeanMorris\Ids\Api\InputParser
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

	public function parse()
	{
		$source = '';
		$header = [];

		while($line = fgetcsv($this->handle, NULL, $this->delimiter, $this->enclosure, $this->escape))
		{
			if($this->header && !$header)
			{
				$header = $line;
				continue;
			}

			if($this->header)
			{
				$lineHeader = $header + array_keys($line);
				$lineValues = array_filter($line, 'is_scalar') + array_fill(0, count($lineHeader), NULL);

				yield array_combine($lineHeader, $lineValues);
			}
			else
			{
				yield $line;
			}
		}
	}
}
