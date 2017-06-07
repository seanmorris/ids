<?php
namespace SeanMorris\Ids\Http;
class Http
{
	protected static $disconnect = [];
	public static function onDisconnect(callable $function)
	{
		if(!static::$disconnect)
		{
			$disconnect =& static::$disconnect;

			ob_start();
			register_shutdown_function(function() use(&$disconnect){
				\SeanMorris\Ids\Log::info('Post-Response Execution Starting.');
				ignore_user_abort(TRUE);
				session_write_close();
				header('Content-Encoding: none');
				header(sprintf('Content-Length: %s', ob_get_length()));
				header('Connection: close');
				ob_end_flush();
				flush();
				foreach ($disconnect as $d)
				{
					$d();
				}
				\SeanMorris\Ids\Log::info(
					'Post-Response Execution Complete.'
					, [
						'Space' => memory_get_peak_usage(true) / (1024*1024) . ' MB'
						, 'Time' => number_format(microtime(true) - START, 2)  . ' sec'
					]
					, PHP_EOL
				);
			});
		}

		static::$disconnect[] = $function;		
	}
}