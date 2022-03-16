<?php
namespace SeanMorris\Ids\Api\Input;
class Plain extends \SeanMorris\Ids\Api\InputPump
{
	public function pump()
	{
		$source = '';
		$header = [];

		// stream_set_read_buffer($this->handle, 0);

		while($line = fgets($this->handle))
		{
			\SeanMorris\Ids\Log::debug('Got', $line);
			yield trim($line);
		}
	}
}
