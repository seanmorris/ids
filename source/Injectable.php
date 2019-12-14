<?php
namespace SeanMorris\Ids;
trait Loader
{
	public function __get($name)
	{
		define('static::LOL', 1);

		if(!isset(static::$_injections))
		{
			return;
		}

		if(isset(static::$_injections[$name]))
		{
			return new static::$_injections[$name];
		}

		if(!is_callable('parent::__get'))
		{
			return;
		}

		return parent::__get($name);
	}
}
