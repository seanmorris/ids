<?php
namespace SeanMorris\Ids\Test\Model;
class Octopus extends \SeanMorris\Ids\Model
{
	use Common;

	protected
		$id
		, $class
		, $publicId
		, $tentacleA
		, $tentacleB
		, $tentacleC
		, $tentacleD
		, $tentacleE
		, $tentacleF
		, $tentacleG
		, $tentacleH
	;

	protected static
		$table = 'Octopus'

		, $byNull = [
		    'with' => [
		        'tentacleA'
		        , 'tentacleB'
		        , 'tentacleC'
		        , 'tentacleD'
		        , 'tentacleE'
		        , 'tentacleF'
		        , 'tentacleG'
		        , 'tentacleH'
		    ]
		]

		, $hasOne = [
			'tentacleA' => 'SeanMorris\Ids\Test\Model\Tentacle'
			, 'tentacleB' => 'SeanMorris\Ids\Test\Model\Tentacle'
			, 'tentacleC' => 'SeanMorris\Ids\Test\Model\Tentacle'
			, 'tentacleD' => 'SeanMorris\Ids\Test\Model\Tentacle'
			, 'tentacleE' => 'SeanMorris\Ids\Test\Model\Tentacle'
			, 'tentacleF' => 'SeanMorris\Ids\Test\Model\Tentacle'
			, 'tentacleG' => 'SeanMorris\Ids\Test\Model\Tentacle'
			, 'tentacleH' => 'SeanMorris\Ids\Test\Model\Tentacle'
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

	public function stretch()
	{

	}
}
