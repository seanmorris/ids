<?php
namespace SeanMorris\Ids\Http;
class Event
{
	protected $id, $message, $eventType;
	protected static $sequence = 0;

	public function __construct($message = NULL, $id = NULL, $type = 'ServerEvent')
	{
		$this->eventType = $type;
		$this->message = $message;
		$this->id = $id ?? intval(microtime(true)*10000) . '.' . ++static::$sequence;
	}

	public function toString()
	{
		$format = "event: %s\n%s\n";

		if(isset($this->id))
		{
			$format .= "id: %s\n";
		}

		$format .= "\n";

		$message = sprintf(
			$format
			, $this->eventType
			, !$this->message || is_scalar($this->message)
				? preg_replace('/^/m', "data: ", $this->message)
				: json_encode($this->message)
			, $this->id
		);

		$padding = \SeanMorris\Ids\Settings::read('eventPadding') ?: 0;

		return str_pad($message, $padding) . "\n";
	}

	public function __toString()
	{
		return $this->toString();
	}
}
