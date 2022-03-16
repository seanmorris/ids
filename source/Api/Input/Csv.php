<?php
namespace SeanMorris\Ids\Api\Input;
class Csv extends \SeanMorris\Ids\Api\InputPump
{
	protected $header    = NULL;
	protected $delimiter = ',';
	protected $enclosure = '"';
	protected $escape    = '\\';

	public function __construct($handle, $headers = ['Ids-Input-Headers' => TRUE])
	{
		parent::__construct($handle, $headers);

		$this->hasHeader = $this->headers['Ids-Input-Headers'] === 'true';
	}

	public function pump()
	{
		$source = '';
		$header = [];

		while($line = fgetcsv($this->handle, NULL, $this->delimiter, $this->enclosure, $this->escape))
		{
			if($this->hasHeader && !$header)
			{
				$header = $line;
				continue;
			}

			if($this->hasHeader)
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
