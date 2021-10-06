<?php
namespace SeanMorris\Ids\Test\Model;
class GroceryList extends \SeanMorris\Ids\Model
{
	use Common;

	protected
		$id
		, $class
		, $publicId
		, $groceries = []
	;

	protected static
		$table = 'GroceryList'

		, $hasMany = [
			'groceries' => \SeanMorris\Ids\Test\Model\Grocery::class
		]

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
