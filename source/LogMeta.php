<?php

namespace SeanMorris\Ids;
class LogMeta
{
	public function __construct($data)
	{
		foreach($data as $k => $v)
		{
			$this->$k = $v;
		}
	}
}
