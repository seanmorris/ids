<?php
namespace SeanMorris\Ids;
abstract class SingletonMethod extends FactoryMethod
{
	public static function wrap($instance)
	{
		return new class( function() use($instance) {

			return $instance;

		}) extends SingletonMethod {};
	}
}
