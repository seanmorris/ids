<?php
namespace SeanMorris\Ids\Http;
class Http
{
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