<?php
namespace SeanMorris\Ids\Http;
class Http
{
	protected static $disconnect = [], $disconnected = FALSE;
	public static function disconnected()
	{
		return static::$disconnected;
	}
	public static function onDisconnect(callable $function)
	{
		if(!static::$disconnect)
		{
			$disconnect =& static::$disconnect;

			ob_start();
			register_shutdown_function(function() use(&$disconnect){
				$contentLength = ob_get_length();
				\SeanMorris\Ids\Log::info('Post-Response Execution Starting.');
				\SeanMorris\Ids\Log::info(sprintf("Content-Length: %s\r\n", $contentLength));
				if(php_sapi_name() !== 'cli')
				{
					ignore_user_abort(TRUE);
					header("Content-Encoding: none\r\n");
					header("Connection: close\r\n");
					header(sprintf("Content-Length: %s\r\n", $contentLength));
				}
				try
				{
					$obStat = ob_get_status();
					while($obStat['level'])
					{
						$obStat = ob_get_status();
						ob_end_flush();
						flush();
					}
				}
				catch (\ErrorException $e)
				{

				}
				session_write_close();
				static::$disconnected = TRUE;
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