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
				ignore_user_abort(TRUE);
				header(sprintf('Content-Length: %s', ob_get_length()));
				header('Connection: close');
				ob_end_flush();
				flush();

				ob_start();
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
				ob_end_clean();
			});
		}

		static::$disconnect[] = $function;		
	}

	public static function disconnect($url)
	{
		set_time_limit(0);
		ignore_user_abort(1);

		header(
			sprintf(
				"Location: %s",
				$url
			)
		);

		header(
			sprintf(
			   "Content-Length: %s",
			   ob_get_length()
			)
		);

		header('Connection: close');

		\Base\Log::file(ob_get_level());

		ob_flush();
		ob_end_flush();
		flush();

		session_write_close();

		\Base\Log::file('Output sent.');
	}
}