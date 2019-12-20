<?php
namespace SeanMorris\Ids\Test\Model;
trait Common
{
	public static function fill($amount, $callback = NULL)
	{
		return array_map(
			function($index) use($callback)
			{
				$instance = new static;

				if($callback)
				{
					$instance = $callback($index, $instance);
				}

				return $instance;
			}
			, array_keys(array_fill(0, $amount, NULL))
		);
	}
}
