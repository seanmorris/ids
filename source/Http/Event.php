<?php
namespace SeanMorris\Ids\Http;
class Event
{
	protected $id, $message, $eventType;

	public function __construct($message = NULL, $id = NULL, $type = NULL)
	{
		$this->message = $message;
		$this->id      = $id;
	}

	public function toString()
	{
		$format = "event: %s\ndata: %s\n";

		if(isset($this->id))
		{
			$format .= "id: %s\n";
		}

		$format .= "\n";

		$message = sprintf(
			$format
			, $this->eventType ?: 'ServerEvent'
			, !$this->message || is_scalar($this->message)
				? $this->message
				: json_encode($this->message)
			, $this->id
		);

		$padding = \SeanMorris\Ids\Settings::read('eventPadding') ?: 0;

		return str_pad($message, $padding);
	}

	public function __toString()
	{
		return $this->toString();
	}
}
