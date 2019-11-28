<?php
namespace SeanMorris\Ids\Test\Route;
class HomeRoute implements \SeanMorris\Ids\Routable
{
	public function index($router)
	{
		$this->login([[
			'username'   => 'sean'
			, 'password' => 'secret'
		]], 'testing');

		return 'Welcome to Ids.';
	}

	protected function login($username, $password)
	{
		// doesntExist();
	}
}
