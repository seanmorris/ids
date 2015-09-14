<?php
namespace SeanMorris\Ids\Test\Route;
class RootRoute implements \SeanMorris\Ids\Routable
{
	public
		$routes = [
			'foo' => 'SeanMorris\Ids\Test\Route\FooRoute'
		]
		, $alias = [
			'bar' => 'foo'
		];

 	function index($router)
	{
		return 'index';
	}

	function otherPage($router)
	{
		return 'not index';
	}
}