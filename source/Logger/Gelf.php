<?php
namespace SeanMorris\Ids\Logger;
class Gelf implements \SeanMorris\Ids\Logger
{
	public static function start($logBlob)
	{
		static::log($logBlob);
	}

	public static function log($logBlob)
	{
		static::send(static::gelfize($logBlob));
	}

	protected static function gelfize($logBlob)
	{
		$logBlob->short_message = preg_replace(
			'/\e\[[0-9;]*m(?:\e\[K)?/i'
			, ''
			, $logBlob->shortMessage ?? ''
		);

		$logBlob->full_message = preg_replace(
			'/\e\[[0-9;]*m(?:\e\[K)?/i'
			, ''
			, $logBlob->fullMessage ?? ''
		);

		unset($logBlob->fullMessage, $logBlob->shortMessage);

		$logBlob->trace = preg_replace(
			'/\e\[[0-9;]*m(?:\e\[K)?/'
			, ''
			, $logBlob->trace ?? ''
		);

		$gelf = [
			'version'         => '1.1'
			, 'host'          => $_SERVER['SERVER_NAME'] ?? gethostname()
			, 'short_message' => 'short_message'
			, 'full_message'  => 'full_message'
			, 'timestamp'     => microtime(true)
			, 'level'         => 0
		];

		$blob = [];

		foreach($logBlob as $k => $v)
		{
			if(is_string($v))
			{
				$v = substr($v, 0, 32766);
			}

			if(!isset($gelf[$k]))
			{
				$blob['_' . $k] = $v;
				continue;
			}

			if(!$v)
			{
			    continue;
			}

			$blob[$k] = $v;
		}

		return (object) ($blob + $gelf);
	}

	protected static function send($gelf)
	{
		if($socket = static::socket())
		{
			$payload = json_encode($gelf);

			try
			{
				socket_write($socket, $payload . "\0", strlen($payload) + 1);
			}
			catch(\Throwable $e)
			{
				return;
			}
		}
	}

	protected static function socket()
	{
		static $socket;

		$graylogConfig = \SeanMorris\Ids\Settings::read('graylog');

		try
		{
			if(!isset($graylogConfig->host, $graylogConfig->port))
			{
				return;
			}

			if($socket)
			{
				return $socket;
			}

			$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

			socket_connect($socket, $graylogConfig->host, $graylogConfig->port);

			return $socket;
		}
		catch(\Throwable $e)
		{
			return;
		}
	}
}
