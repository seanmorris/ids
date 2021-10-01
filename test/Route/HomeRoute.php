<?php

namespace SeanMorris\Ids\Test\Route;
class HomeRoute implements \SeanMorris\Ids\Routable
{
	public function index($router)
	{
	    $this->login('username', 'testing', 3, fopen('php://stdout', 'w'));

		return 'Welcome to Ids.';
	}

	protected function login($username, $password)
	{
		return TRUE;
	}
}
