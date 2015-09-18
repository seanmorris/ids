<?php
namespace SeanMorris\Ids\Test\Model;
class Foozle extends \SeanMorris\Ids\Model
{
	protected
		$id
		, $class
		, $publicId
		, $value
	;

	protected static
		$table = 'Foozle'
		, $createColumns = [
			'publicId' => 'UNHEX(REPLACE(UUID(), "-", ""))'
		]
		, $readColumns = [
			'publicId' => 'HEX(%s)'
		]
		, $updateColumns = [
			'publicId' => 'UNHEX(%s)'
		]
	;
}