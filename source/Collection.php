<?php

namespace SeanMorris\Ids;

(new class { use Injectable; })::inject([

	'content' => \SplObjectStorage::class

	, 'Type'  => \stdClass::class

], \SeanMorris\Ids\___\BaseCollection::class);

abstract class Collection extends \SeanMorris\Ids\___\BaseCollection
{
	protected $content;
	protected static $Type;

	public static function of(string $type, string $name = null)
	{
		return static::inject(['Type' => $type], $name);
	}

	public function add($item)
	{
		if(!is_object($item) || !($item instanceof static::$Type))
		{
			return FALSE;
		}

		$this->content[$item] = $item;

		return $this->content->getHash($item);
	}
}
