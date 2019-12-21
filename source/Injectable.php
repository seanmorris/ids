<?php
namespace SeanMorris\Ids;
trait Injectable
{
	public function __get($name)
	{
		if(!isset(static::$_injections))
		{
			return;
		}

		if(isset(static::$_injections[$name]))
		{
			return new static::$_injections[$name];
		}

		if(!is_callable('parent::__get')
		{
			return;
		}

		return parent::__get($name);
	}
}
