<?php
namespace SeanMorris\Ids\Test\Model;
class Foobar extends Foozle
{
	use Common;

	protected
		$barValue
	;

	protected static
		$table = 'Foobar'
	;
}
