<?php
namespace SeanMorris\Ids\Http;
class Event
{
	protected $id, $message, $type;

	public function __construct($message = NULL, $id = NULL, $type = NULL)
	{
		$this->message = $message;
		$this->type    = $type;
		$this->id      = $id;
	}

	public function toString()
	{
		$format = "%s: %s\ndata: %s\n";

		if(isset($this->id))
		{
			$format .= "id: %s\n";
		}

		$format .= "\n";

		return sprintf(
			$format
			, $this->type ?? 'ServerEvent'
			, !$this->message || is_scalar($this->message)
				? $this->message
				: json_encode($this->message)
			, $this->id
		);
	}

	public function __toString()
	{
		return $this->toString();
	}
}
