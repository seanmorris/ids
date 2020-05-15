<?php
namespace SeanMorris\Ids\Session;

use \SeanMorris\Ids\Log;
use \SessionHandlerInterface, \SessionIdInterface;

class Handler implements SessionHandlerInterface, SessionIdInterface
{
	public function create_sid()
	{

	}

	public function open($savePath, $sessionName)
	{
		Log::error($sessionName);

		return true;
	}

	public function close()
	{
		Log::error($this);

		return true;
	}

	public function read($sessionId)
	{
		Log::error($sessionId);

		return '';
	}

	public function write($sessionId, $userData)
	{
		Log::error($sessionId, $userData);

		return true;
	}

	public function destroy($sessionId)
	{
		Log::error($sessionId);

		return true;
	}

	public function gc($lifetime)
	{
		Log::error($lifetime);

		return 365 * 24 * 60 * 60;
	}
}
