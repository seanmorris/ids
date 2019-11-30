<?php

namespace SeanMorris\Ids\Test\Route;
class HomeRoute implements \SeanMorris\Ids\Routable
{
	public function index($router)
	{
		2+2;

		xdebug_break();

		$this->login('username', 'testing', 3, fopen('php://stdout', 'w'));

		return 'Welcome to Ids.';
	}

	protected function login($username, $password)
	{
		$something = 2;
		// xdebug_break();
		return  1+2+3;

		// throw new Exception('This is a test.');
		// doesntExist();
	}
}
