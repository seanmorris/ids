<?php
namespace SeanMorris\Ids;
class Collection
{
	use Injectable;

	protected static $type;

	public static function of(string $type, string $name = null)
	{
		return static::inject(['type' => $type], $name);
	}

	public function __construct(array $contents = [])
	{

	}
}
