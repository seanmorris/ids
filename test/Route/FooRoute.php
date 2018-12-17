<?php
namespace SeanMorris\Ids\Test\Route;
class FooRoute implements \SeanMorris\Ids\Routable
{
	function index($router)
	{
		return 'subindex';
	}

	function more($router)
	{
		return 'more stuff';
	}
}