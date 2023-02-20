<?php
namespace SeanMorris\Ids;

#[AllowDynamicProperties]
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
