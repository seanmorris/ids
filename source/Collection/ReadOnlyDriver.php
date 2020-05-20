<?php
namespace SeanMorris\Ids\Collection;

use \BadMethodCallException;

class RankedDriver extends Driver
{
	public function add($item)
	{
		throw new BadMethodCallException(sprintf(
			'Cannot call "%s" on instace of "%s" (%s).'
			, __FUNCTION__
			, __CLASS__
			, get_called_class()
		));
	}

	public function remove($item)
	{
		throw new BadMethodCallException(sprintf(
			'Cannot call "%s" on instace of "%s" (%s).'
			, __FUNCTION__
			, __CLASS__
			, get_called_class()
		));
	}
}
