<?php
namespace SeanMorris\Ids;
abstract class FactoryMethod extends WrappedMethod
{
	public static function wrap(callable $closure)
	{
		return new class($closure) extends FactoryMethod {};
	}
}
