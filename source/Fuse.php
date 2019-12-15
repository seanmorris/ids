<?php
namespace SeanMorris\Ids;
class Fuse
{
	public static function retry(...$args)
	{
		$tries    = (int) array_shift($args);
		$delay    = (int) array_shift($args) ?: 5;

		$callback = array_pop($args);
		$type     = array_pop($args) ?: \Exception::class;

		while($tries > 0)
		{
			$tries--;

			Log::debug('Trying...');

			try
			{
				$r = $callback();

				Log::debug('Success!');

				return $r;
			}
			catch(\Exception $exception)
			{
				if(!($exception instanceof $type) || $tries < 0)
				{
					throw $exception;
				}
			}

			Log::debug('Waiting...');

			sleep($delay);
		}
	}
}