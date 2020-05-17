<?php
namespace SeanMorris\Ids;

class Session
{
	protected static $_SESSION;

	// \SeanMorris\Ids\
	public static function &local($depth = 0)
	{
		if(session_status() === PHP_SESSION_NONE
			&& php_sapi_name() !== 'cli'
			&& !\SeanMorris\Ids\Http\Http::disconnected()
		){
			$sessionSave = new \SeanMorris\Ids\___\SessionHandler;
			session_set_save_handler($sessionSave, TRUE);
			var_dump($sessionSave);

			session_start();
		}

		if(!isset($_SESSION['meta']))
		{
			$_SESSION['meta'] = [];
			$_SESSION['sess_id'] = uniqid();
		}

		static::$_SESSION =& $_SESSION['meta'];

		if($objectStack = Meta::getObjectStack(1, false))
		{
			$class = $objectStack[0];

			if(!is_string($class))
			{
				$class = get_class($objectStack[0]);
			}

			while($depth-- > 0)
			{
				$class = get_parent_class($class);
			}

			if(!isset(static::$_SESSION[$class]))
			{
				static::$_SESSION[$class] = [];
			}

			return static::$_SESSION[$class];
		}

		return false;
	}
}
