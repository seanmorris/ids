<?php
namespace SeanMorris\Ids\Test\Model;
class Grocery extends \SeanMorris\Ids\Model
{
	use Common;

	protected
		$id
		, $class
		, $publicId
	;

	protected static
		$table = 'Grocery'

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
