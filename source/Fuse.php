<?php
namespace SeanMorris\Ids;
class Fuse
{
	public static function retry(...$args)
	{
		$tries    = (int) array_shift($args) ?: 1;
		$delay    = (int) array_shift($args) ?: 5;

		$callback = array_pop($args);
		$type     = array_pop($args) ?: \Exception::class;

		do
		{
			--$tries;

			Log::debug(sprintf('Retries left #%d...', $tries));

			try
			{
				$r = $callback();

				Log::debug('Success!');

				return $r;
			}
			catch(\Exception $exception)
			{
				if(!($exception instanceof $type) || $tries <= 0)
				{
					throw $exception;
				}
			}

			Log::debug(sprintf(
				'Waiting %s seconds...'
				, $delay
			));

			sleep($delay);

		} while($tries > 0);
	}
}
